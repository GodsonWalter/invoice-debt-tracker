<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

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

            // Create a few invoices per workspace (deterministic-ish but varied)
            for ($i = 1; $i <= min(5, $clients->count()); $i++) {
                $client = $clients->get($i - 1);

                $issueDate = now()->subDays(20 + $i);
                $dueDate = now()->subDays(20 + $i - 5)->addDays(20); // roughly future

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

                // Ensure unique invoice_number
                $invoiceNumber = strtoupper(
                    $workspace->slug . '-' . Str::upper(Str::random(6)) . '-' . $i
                );

                $invoice = $workspace->invoices()->create([
                    'client_id' => $client->id,
                    'invoice_number' => $invoiceNumber,
                    'issue_date' => $issueDate->toDateString(),
                    'due_date' => $dueDate->toDateString(),
                    'status' => $status,
                    'subtotal' => $subtotal,
                    'tax_amount' => $tax,
                    'discount_amount' => $discount,
                    'total_amount' => $total,
                    'notes' => 'Seeded invoice for testing workspace invoices.',
                ]);

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
        }
    }
}

