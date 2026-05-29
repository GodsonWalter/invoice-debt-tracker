<?php

namespace Database\Seeders;

use App\Models\Invoice;
use App\Models\Workspace;
use Illuminate\Database\Seeder;

class InvoiceSeeder extends Seeder
{
    public function run(): void
    {
        $workspaces = Workspace::query()->get();

        foreach ($workspaces as $workspace) {
            $clients = $workspace->clients()->get();
            if ($clients->isEmpty()) {
                continue;
            }

            for ($i = 1; $i <= min(5, $clients->count()); $i++) {
                $client = $clients->get($i - 1);

                $issueDate = now()->subDays(20 + $i);
                $dueDate = $issueDate->copy()->addDays(20);

                $status = match (true) {
                    $i % 4 === 0 => 'overdue',
                    $i % 4 === 1 => 'draft',
                    $i % 4 === 2 => 'sent',
                    default => 'paid',
                };

                $items = [
                    [
                        'item_name' => 'Consulting Service',
                        'description' => 'Implementation and setup work',
                        'quantity' => 2 + ($i % 3),
                        'unit_price' => 150.00 + ($i * 10),
                    ],
                    [
                        'item_name' => 'Support Package',
                        'description' => 'Monthly support',
                        'quantity' => 1,
                        'unit_price' => 99.00 + ($i * 2),
                    ],
                ];

                $subtotal = 0.0;
                foreach ($items as &$it) {
                    $it['total_price'] = ((float) $it['quantity']) * ((float) $it['unit_price']);
                    $subtotal += (float) $it['total_price'];
                }
                unset($it);

                $tax = 0.10 * $subtotal; // 10%
                $discount = ($i % 2 === 0) ? 0.05 * $subtotal : 0.0; // 5% sometimes
                $total = $subtotal + $tax - $discount;

                $invoiceNumber = $workspace->invoice_prefix.'-'.now()->year.'-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT);

                $invoice = Invoice::updateOrCreate(
                    [
                        'workspace_id' => $workspace->id,
                        'invoice_number' => $invoiceNumber,
                    ],
                    [
                        'client_id' => $client->id,
                        'issue_date' => $issueDate->toDateString(),
                        'due_date' => $dueDate->toDateString(),
                        'status' => $status,
                        'subtotal' => $subtotal,
                        'tax_amount' => $tax,
                        'discount_amount' => $discount,
                        'total_amount' => $total,
                        'notes' => 'Seeded invoice for testing workspace invoices.',
                    ],
                );

                $invoice->items()->delete();
                foreach ($items as $it) {
                    $invoice->items()->create([
                        'item_name' => $it['item_name'],
                        'description' => $it['description'],
                        'quantity' => (int) $it['quantity'],
                        'unit_price' => (float) $it['unit_price'],
                        'total_price' => (float) $it['total_price'],
                    ]);
                }
            }

            $workspace->forceFill([
                'next_invoice_number' => max((int) $workspace->next_invoice_number, 6),
            ])->save();
        }
    }
}
