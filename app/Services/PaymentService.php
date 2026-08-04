<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceEmailLog;
use App\Models\Payment;
use App\Models\Workspace;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(private readonly MoneyCalculator $money) {}

    /**
     * @param  array{amount: numeric, payment_date: string, payment_method?: ?string, reference?: ?string, notes?: ?string}  $validated
     */
    public function createPayment(Workspace $workspace, Invoice $invoice, array $validated): Payment
    {
        return DB::transaction(function () use ($workspace, $invoice, $validated) {
            $invoice = $workspace->invoices()
                ->where('id', $invoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($invoice->status === Invoice::STATUS_VOID) {
                throw ValidationException::withMessages([
                    'invoice' => 'Void invoices cannot receive payments.',
                ]);
            }

            if (! empty($validated['idempotency_key'])) {
                $existingPayment = Payment::query()
                    ->where('workspace_id', $workspace->id)
                    ->where('idempotency_key', $validated['idempotency_key'])
                    ->first();

                if ($existingPayment) {
                    if ((int) $existingPayment->invoice_id !== (int) $invoice->id) {
                        throw ValidationException::withMessages([
                            'idempotency_key' => 'This payment submission key has already been used.',
                        ]);
                    }

                    return $existingPayment;
                }
            }

            $totalPaid = $this->money->normalize($invoice->payments()->sum('amount'));
            $remainingBalance = $this->money->subtract($invoice->total_amount, $totalPaid);
            $paymentAmount = $this->money->normalize($validated['amount']);

            if ($paymentAmount->isNegativeOrZero()) {
                throw ValidationException::withMessages([
                    'amount' => 'Payment amount must be greater than zero.',
                ]);
            }

            if ($paymentAmount->isGreaterThan($remainingBalance)) {
                throw ValidationException::withMessages([
                    'amount' => 'Payment amount cannot exceed the invoice remaining balance.',
                ]);
            }

            $payment = $invoice->payments()->create([
                'workspace_id' => $workspace->id,
                'amount' => $this->money->toFloat($paymentAmount),
                'payment_date' => $validated['payment_date'],
                'payment_method' => $validated['payment_method'] ?? null,
                'reference' => $validated['reference'] ?? null,
                'idempotency_key' => $validated['idempotency_key'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            $this->updateInvoicePaymentStatus($invoice);

            return $payment;
        });
    }

    public function calculateTotalPaid(Invoice $invoice): float
    {
        if ($invoice->relationLoaded('payments')) {
            return $this->money->toFloat($this->money->normalize($invoice->payments->sum('amount')));
        }

        return $this->money->toFloat($this->money->normalize($invoice->payments()->sum('amount')));
    }

    public function calculateRemainingBalance(Invoice $invoice): float
    {
        $remaining = $this->money->subtract($invoice->total_amount, $this->calculateTotalPaid($invoice));

        return $remaining->isNegative() ? 0.0 : $this->money->toFloat($remaining);
    }

    public function updateInvoicePaymentStatus(Invoice $invoice): Invoice
    {
        $totalPaid = $this->money->normalize($invoice->payments()->sum('amount'));
        $remainingBalance = $this->money->subtract($invoice->total_amount, $totalPaid);

        $status = match (true) {
            $remainingBalance->isNegativeOrZero() && ! $this->money->normalize($invoice->total_amount)->isNegativeOrZero() => Invoice::STATUS_PAID,
            $totalPaid->isGreaterThan(0) => Invoice::STATUS_PARTIAL,
            $invoice->due_date?->isPast() => Invoice::STATUS_OVERDUE,
            default => Invoice::STATUS_SENT,
        };

        // if invoice is fully paid, set paid_at to the latest payment date, otherwise set it to null
        if ($status === Invoice::STATUS_PAID) {
            $paid_at = now();
        } else {
            $paid_at = null;
        }
        $invoice->forceFill(['status' => $status, 'paid_at' => $paid_at])->save();

        return $invoice->refresh();
    }

    /**
     * @return Collection<int, array{type: string, title: string, date: Carbon|null, badge: string, amount?: float, method?: ?string, reference?: ?string, notes?: ?string, remaining_balance?: float, recipient_email?: string, subject?: string, status?: string}>
     */
    public function paymentTimeline(Invoice $invoice): Collection
    {
        $runningPaid = $this->money->normalize(0);

        $paymentEvents = $invoice->payments
            ->sortBy([
                ['payment_date', 'asc'],
                ['created_at', 'asc'],
                ['id', 'asc'],
            ])
            ->map(function (Payment $payment) use ($invoice, &$runningPaid): array {
                $runningPaid = $runningPaid->plus($this->money->normalize($payment->amount));
                $remaining = $this->money->subtract($invoice->total_amount, $runningPaid);
                $remainingBalance = $remaining->isNegative() ? 0.0 : $this->money->toFloat($remaining);

                return [
                    'type' => 'payment',
                    'title' => $remainingBalance <= 0 ? 'Final Payment Received' : 'Payment Received',
                    'date' => $payment->payment_date,
                    'badge' => $remainingBalance <= 0 ? 'success' : 'warning',
                    'amount' => (float) $payment->amount,
                    'method' => $payment->payment_method,
                    'reference' => $payment->reference,
                    'notes' => $payment->notes,
                    'remaining_balance' => $remainingBalance,
                ];
            });

        $emailEvents = $invoice->relationLoaded('emailLogs')
            ? $invoice->emailLogs->map(function (InvoiceEmailLog $emailLog): array {
                $status = $emailLog->status;

                return [
                    'type' => 'email',
                    'title' => $status === InvoiceEmailLog::STATUS_SENT ? 'Invoice Sent' : 'Invoice Email '.ucfirst($status),
                    'date' => $emailLog->sent_at ?? $emailLog->created_at,
                    'badge' => match ($status) {
                        InvoiceEmailLog::STATUS_SENT => 'info',
                        InvoiceEmailLog::STATUS_FAILED => 'danger',
                        default => 'secondary',
                    },
                    'recipient_email' => $emailLog->recipient_email,
                    'subject' => $emailLog->subject,
                    'status' => $status,
                    'notes' => $emailLog->error_message,
                ];
            })
            : collect();

        $publicActivityEvents = collect([
            [
                'type' => 'public',
                'title' => 'Invoice Viewed',
                'date' => $invoice->viewed_at,
                'badge' => 'primary',
            ],
            [
                'type' => 'public',
                'title' => 'Invoice Downloaded',
                'date' => $invoice->downloaded_at,
                'badge' => 'info',
            ],
            [
                'type' => 'public',
                'title' => 'Invoice Printed',
                'date' => $invoice->printed_at,
                'badge' => 'secondary',
            ],
        ])->filter(fn (array $event): bool => filled($event['date']));

        $events = collect([
            [
                'type' => 'invoice',
                'title' => 'Invoice Created',
                'date' => $invoice->created_at,
                'badge' => 'secondary',
                'amount' => (float) $invoice->total_amount,
                'remaining_balance' => (float) $invoice->total_amount,
            ],
        ])->merge($emailEvents)->merge($publicActivityEvents)->merge($paymentEvents);

        if ($invoice->is_partially_paid) {
            $events->push([
                'type' => 'status',
                'title' => 'Invoice Partially Paid',
                'date' => $invoice->updated_at,
                'badge' => 'warning',
                'remaining_balance' => $invoice->remaining_balance,
            ]);
        }

        if ($invoice->is_fully_paid) {
            $events->push([
                'type' => 'status',
                'title' => 'Invoice Marked Paid',
                'date' => $invoice->updated_at,
                'badge' => 'success',
                'remaining_balance' => 0.0,
            ]);
        }

        return $events->sortByDesc('date')->values();
    }
}
