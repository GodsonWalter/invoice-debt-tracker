<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    /**
     * @param  array{amount: numeric, payment_date: string, payment_method?: ?string, notes?: ?string}  $validated
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
                'notes' => $validated['notes'] ?? null,
            ]);

            $invoice->load('payments');

            $invoice->update([
                'status' => $invoice->remaining_balance <= 0 ? 'paid' : 'sent',
            ]);

            return $payment;
        });
    }
}
