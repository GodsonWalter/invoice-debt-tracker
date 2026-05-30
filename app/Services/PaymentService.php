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

            $remainingBalance = round((float) $invoice->remaining_balance, 2);
            $paymentAmount = round((float) $validated['amount'], 2);

            if ($paymentAmount > $remainingBalance) {
                throw ValidationException::withMessages([
                    'amount' => 'Payment amount cannot exceed the invoice remaining balance.',
                ]);
            }

            $payment = $invoice->payments()->create([
                'workspace_id' => $workspace->id,
                'amount' => $paymentAmount,
                'payment_date' => $validated['payment_date'],
                'payment_method' => $validated['payment_method'] ?? null,
                'reference' => $validated['reference'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            $this->updateInvoicePaymentStatus($invoice);

            return $payment;
        });
    }

    public function calculateTotalPaid(Invoice $invoice): float
    {
        if ($invoice->relationLoaded('payments')) {
            return round((float) $invoice->payments->sum('amount'), 2);
        }

        return round((float) $invoice->payments()->sum('amount'), 2);
    }

    public function calculateRemainingBalance(Invoice $invoice): float
    {
        return max(round((float) $invoice->total_amount - $this->calculateTotalPaid($invoice), 2), 0.0);
    }

    public function updateInvoicePaymentStatus(Invoice $invoice): Invoice
    {
        $invoice->load('payments');

        $totalPaid = $this->calculateTotalPaid($invoice);
        $remainingBalance = $this->calculateRemainingBalance($invoice);

        $status = match (true) {
            $remainingBalance <= 0 && (float) $invoice->total_amount > 0 => Invoice::STATUS_PAID,
            $totalPaid > 0 => Invoice::STATUS_PARTIAL,
            $invoice->due_date?->isPast() => Invoice::STATUS_OVERDUE,
            default => Invoice::STATUS_SENT,
        };

        $invoice->forceFill(['status' => $status])->save();

        return $invoice->refresh();
    }

    /**
     * @return Collection<int, array{type: string, title: string, date: Carbon|null, badge: string, amount?: float, method?: ?string, reference?: ?string, notes?: ?string, remaining_balance?: float, recipient_email?: string, subject?: string, status?: string}>
     */
    public function paymentTimeline(Invoice $invoice): Collection
    {
        $runningPaid = 0.0;

        $paymentEvents = $invoice->payments
            ->sortBy([
                ['payment_date', 'asc'],
                ['created_at', 'asc'],
                ['id', 'asc'],
            ])
            ->map(function (Payment $payment) use ($invoice, &$runningPaid): array {
                $runningPaid = round($runningPaid + (float) $payment->amount, 2);
                $remainingBalance = max(round((float) $invoice->total_amount - $runningPaid, 2), 0.0);

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
