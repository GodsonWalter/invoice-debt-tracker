<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function generateInvoiceNumber(Workspace $workspace): string
    {
        $number = str_pad(
            $workspace->next_invoice_number,
            4,
            '0',
            STR_PAD_LEFT
        );

        return $workspace->invoice_prefix.'-'.now()->year.'-'.$number;
    }

    public function createInvoice(Workspace $workspace, array $validated): Invoice
    {
        return DB::transaction(function () use ($workspace, $validated) {
            // ensure client belongs to workspace
            $client = $workspace->clients()->where('id', $validated['client_id'])->firstOrFail();

            $tax = (float) ($validated['tax_amount'] ?? 0);

            $discount = (float) ($validated['discount_amount'] ?? 0);

            $subtotal = 0;

            $itemsPayload = [];

            foreach ($validated['items'] as $item) {

                $qty = (int) $item['quantity'];

                $unit = (float) $item['unit_price'];

                $lineTotal = $qty * $unit;

                $subtotal += $lineTotal;

                $itemsPayload[] = [

                    'item_name' => $item['item_name'],

                    'description' => $item['description'] ?? '',

                    'quantity' => $qty,

                    'unit_price' => $unit,

                    'total_price' => $lineTotal,

                ];
            }

            $totalAmount = $subtotal + $tax - $discount;

            $invoiceNumber = app(InvoiceService::class)
                ->generateInvoiceNumber($workspace);

            // prevent duplicates

            if ($workspace->invoices()->where('invoice_number', $invoiceNumber)->exists()) {
                throw new \Exception(
                    'Generated invoice number already exists.'
                );
            }

            $invoice = $workspace->invoices()->create([
                'client_id' => $client->id,
                'currency_id' => $validated['currency_id'],
                'invoice_number' => $invoiceNumber,
                'issue_date' => $validated['issue_date'],
                'due_date' => $validated['due_date'],
                'status' => $validated['status'],
                'subtotal' => $subtotal,
                'tax_amount' => $tax,
                'discount_amount' => $discount,
                'total_amount' => $totalAmount,
                'notes' => $validated['notes'] ?? null,

            ]);

            foreach ($itemsPayload as $payload) {

                $invoice->items()->create($payload);
            }

            // increment invoice counter
            $workspace->increment('next_invoice_number');

            return $invoice;
        });
    }

    public function updateInvoice(Workspace $workspace, array $validated, Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($workspace, $validated, $invoice) {

            $invoice = $workspace->invoices()->where('id', $invoice->id)->firstOrFail();

            $client = $workspace->clients()->where('id', $validated['client_id'])->firstOrFail();

            $tax = (float) ($validated['tax_amount'] ?? 0);
            $discount = (float) ($validated['discount_amount'] ?? 0);

            $subtotal = 0.0;
            // Replace all items (simpler/robust)
            $invoice->items()->delete();

            foreach ($validated['items'] as $item) {
                $qty = (int) $item['quantity'];
                $unit = (float) $item['unit_price'];
                $lineTotal = $qty * $unit;
                $subtotal += $lineTotal;

                $invoice->items()->create([
                    'item_name' => $item['item_name'],
                    'description' => $item['description'] ?? '',
                    'quantity' => $qty,
                    'unit_price' => $unit,
                    'total_price' => $lineTotal,
                ]);
            }

            $totalAmount = $subtotal + $tax - $discount;

            $invoice->update([
                'client_id' => $client->id,
                'currency_id' => $validated['currency_id'],
                'issue_date' => $validated['issue_date'],
                'due_date' => $validated['due_date'],
                'status' => $validated['status'],
                'subtotal' => $subtotal,
                'tax_amount' => $tax,
                'discount_amount' => $discount,
                'total_amount' => $totalAmount,
                'notes' => $validated['notes'] ?? null,
            ]);

            return $invoice;
        });
    }
}
