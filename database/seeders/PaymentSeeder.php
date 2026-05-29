<?php

namespace Database\Seeders;

use App\Models\Invoice;
use App\Services\PaymentService;
use Illuminate\Database\Seeder;

class PaymentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Invoice::query()
            ->with('payments')
            ->orderBy('id')
            ->each(function (Invoice $invoice): void {
                if (! in_array($invoice->status, ['paid', 'sent', 'partial'], true)) {
                    return;
                }

                $paymentAmount = $invoice->status === 'paid'
                    ? (float) $invoice->total_amount
                    : round((float) $invoice->total_amount * 0.4, 2);

                $invoice->payments()->updateOrCreate(
                    [
                        'payment_method' => 'Seeded demo payment',
                        'notes' => 'Seeded payment for demo data.',
                    ],
                    [
                        'workspace_id' => $invoice->workspace_id,
                        'amount' => $paymentAmount,
                        'payment_date' => now()->subDays(3)->toDateString(),
                    ],
                );

                app(PaymentService::class)->updateInvoicePaymentStatus($invoice);
            });
    }
}
