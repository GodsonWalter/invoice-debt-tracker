<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Workspace;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
    public function __construct(private readonly MoneyCalculator $money) {}

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
            $workspace = Workspace::query()->lockForUpdate()->findOrFail($workspace->id);
            $client = $workspace->clients()->where('id', $validated['client_id'])->firstOrFail();
            $totals = $this->calculateTotals($validated);
            $this->ensureNonNegativeTotal($totals['total_amount']);
            $invoiceNumber = $this->generateInvoiceNumber($workspace);

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
                'subtotal' => $totals['subtotal'],
                'tax_amount' => $totals['tax_amount'],
                'discount_amount' => $totals['discount_amount'],
                'total_amount' => $totals['total_amount'],
                'notes' => $validated['notes'] ?? null,

            ]);

            foreach ($totals['items'] as $payload) {
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
            $workspace = Workspace::query()->lockForUpdate()->findOrFail($workspace->id);
            $invoice = $workspace->invoices()->where('id', $invoice->id)->lockForUpdate()->firstOrFail();
            $client = $workspace->clients()->where('id', $validated['client_id'])->firstOrFail();
            $totals = $this->calculateTotals($validated);
            $this->ensureNonNegativeTotal($totals['total_amount']);

            if ($this->money->normalize($invoice->payments()->sum('amount'))->isGreaterThan($totals['total_amount'])) {
                throw ValidationException::withMessages([
                    'discount_amount' => 'The invoice total cannot be lower than payments already recorded.',
                ]);
            }

            $invoice->items()->delete();

            foreach ($totals['items'] as $payload) {
                $invoice->items()->create($payload);
            }

            $invoice->update([
                'client_id' => $client->id,
                'currency_id' => $validated['currency_id'],
                'issue_date' => $validated['issue_date'],
                'due_date' => $validated['due_date'],
                'status' => $validated['status'],
                'subtotal' => $totals['subtotal'],
                'tax_amount' => $totals['tax_amount'],
                'discount_amount' => $totals['discount_amount'],
                'total_amount' => $totals['total_amount'],
                'notes' => $validated['notes'] ?? null,
            ]);

            if ($invoice->payments()->exists()) {
                app(PaymentService::class)->updateInvoicePaymentStatus($invoice);
            }

            return $invoice;
        });
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, subtotal: float, tax_amount: float, discount_amount: float, total_amount: float}
     */
    private function calculateTotals(array $validated): array
    {
        $subtotal = $this->money->normalize(0);
        $itemsPayload = [];

        foreach ($validated['items'] as $item) {
            $quantity = (int) $item['quantity'];
            $unitPrice = $this->money->normalize($item['unit_price']);
            $lineTotal = $this->money->multiply($unitPrice, $quantity);
            $subtotal = $subtotal->plus($lineTotal);

            $itemsPayload[] = [
                'item_name' => $item['item_name'],
                'description' => $item['description'] ?? '',
                'quantity' => $quantity,
                'unit_price' => $this->money->toFloat($unitPrice),
                'total_price' => $this->money->toFloat($lineTotal),
            ];
        }

        $subtotal = $subtotal->toScale(2, RoundingMode::HalfUp);
        $tax = $this->money->normalize($validated['tax_amount'] ?? 0);
        $discount = $this->money->normalize($validated['discount_amount'] ?? 0);
        $total = $subtotal->plus($tax)->minus($discount)->toScale(2, RoundingMode::HalfUp);

        return [
            'items' => $itemsPayload,
            'subtotal' => $this->money->toFloat($subtotal),
            'tax_amount' => $this->money->toFloat($tax),
            'discount_amount' => $this->money->toFloat($discount),
            'total_amount' => $this->money->toFloat($total),
        ];
    }

    private function ensureNonNegativeTotal(float|int|string|BigDecimal $total): void
    {
        if ($this->money->normalize($total)->isNegative()) {
            throw ValidationException::withMessages([
                'discount_amount' => 'The discount cannot exceed the invoice subtotal and tax.',
            ]);
        }
    }
}
