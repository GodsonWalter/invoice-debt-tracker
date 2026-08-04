<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\ReminderLog;
use App\Models\User;
use App\Models\Workspace;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;
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
            if (! in_array($validated['status'], [Invoice::STATUS_DRAFT, Invoice::STATUS_SENT], true)) {
                throw ValidationException::withMessages([
                    'status' => 'New invoices can only be created as draft or sent.',
                ]);
            }

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

    public function updateInvoice(Workspace $workspace, array $validated, Invoice $invoice, ?User $actor = null): Invoice
    {
        return DB::transaction(function () use ($workspace, $validated, $invoice, $actor) {
            $workspace = Workspace::query()->lockForUpdate()->findOrFail($workspace->id);
            $invoice = $workspace->invoices()->where('id', $invoice->id)->lockForUpdate()->firstOrFail();

            $this->assertEditable($invoice);
            $auditEdit = in_array($invoice->status, [Invoice::STATUS_SENT, Invoice::STATUS_OVERDUE], true);

            if ($invoice->status === Invoice::STATUS_PAID) {
                $this->assertPaidInvoiceFieldsUnchanged($invoice, $validated);
                $invoice->forceFill(['notes' => $validated['notes'] ?? $invoice->notes])->save();
                $this->recordActivity($workspace, $actor, $invoice, 'invoice.updated', 'Paid invoice non-financial fields updated.');

                return $invoice->refresh();
            }

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

            $hasPayments = $invoice->payments()->exists();
            $requestedStatus = $validated['status'] ?? $invoice->status;
            $status = $hasPayments
                ? $invoice->status
                : ($requestedStatus === Invoice::STATUS_DRAFT
                    ? Invoice::STATUS_DRAFT
                    : (now()->startOfDay()->greaterThan(Carbon::parse($validated['due_date'])->startOfDay())
                        ? Invoice::STATUS_OVERDUE
                        : Invoice::STATUS_SENT));
            $invoice->update([
                'client_id' => $client->id,
                'currency_id' => $validated['currency_id'],
                'issue_date' => $validated['issue_date'],
                'due_date' => $validated['due_date'],
                'status' => $status,
                'subtotal' => $totals['subtotal'],
                'tax_amount' => $totals['tax_amount'],
                'discount_amount' => $totals['discount_amount'],
                'total_amount' => $totals['total_amount'],
                'notes' => $validated['notes'] ?? null,
            ]);

            if ($hasPayments) {
                app(PaymentService::class)->updateInvoicePaymentStatus($invoice);
            }

            if ($auditEdit) {
                $this->recordActivity($workspace, $actor, $invoice, 'invoice.updated', 'Invoice details updated.');
            }

            return $invoice->refresh();
        });
    }

    public function softDeleteDraft(Workspace $workspace, Invoice $invoice, ?User $actor = null): void
    {
        DB::transaction(function () use ($workspace, $invoice, $actor): void {
            $workspace = Workspace::query()->lockForUpdate()->findOrFail($workspace->id);
            $invoice = $workspace->invoices()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            $this->assertDraft($invoice);

            if ($invoice->payments()->exists()) {
                throw ValidationException::withMessages([
                    'invoice' => 'An invoice with payments cannot be deleted.',
                ]);
            }

            $invoice->delete();
            $this->recordActivity($workspace, $actor, $invoice, 'invoice.deleted', 'Draft invoice moved to the recycle bin.');
        });
    }

    public function restoreDraft(Workspace $workspace, int $invoiceId, ?User $actor = null): Invoice
    {
        return DB::transaction(function () use ($workspace, $invoiceId, $actor): Invoice {
            $workspace = Workspace::query()->lockForUpdate()->findOrFail($workspace->id);
            $invoice = $workspace->invoices()->onlyTrashed()->whereKey($invoiceId)->lockForUpdate()->firstOrFail();
            $this->assertDraft($invoice);

            $invoice->restore();
            $this->recordActivity($workspace, $actor, $invoice, 'invoice.restored', 'Draft invoice restored.');

            return $invoice->refresh();
        });
    }

    public function forceDeleteDraft(Workspace $workspace, int $invoiceId, ?User $actor = null): void
    {
        DB::transaction(function () use ($workspace, $invoiceId, $actor): void {
            $workspace = Workspace::query()->lockForUpdate()->findOrFail($workspace->id);
            $invoice = $workspace->invoices()->onlyTrashed()->whereKey($invoiceId)->lockForUpdate()->firstOrFail();
            $this->assertDraft($invoice);

            if ($invoice->payments()->exists()) {
                throw ValidationException::withMessages([
                    'invoice' => 'An invoice with payments cannot be permanently deleted.',
                ]);
            }

            $invoice->emailLogs()->where('workspace_id', $workspace->id)->delete();
            $invoice->reminderLogs()->where('workspace_id', $workspace->id)->delete();
            $invoice->items()->delete();
            $invoice->forceDelete();

            $this->recordActivity($workspace, $actor, $invoice, 'invoice.force_deleted', 'Draft invoice permanently deleted.');
        });
    }

    public function voidInvoice(Workspace $workspace, Invoice $invoice, string $reason, User $actor): Invoice
    {
        return DB::transaction(function () use ($workspace, $invoice, $reason, $actor): Invoice {
            $workspace = Workspace::query()->lockForUpdate()->findOrFail($workspace->id);
            $invoice = $workspace->invoices()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $this->assertCanVoid($invoice);

            if ($this->money->normalize($invoice->payments()->sum('amount'))->isGreaterThan(0)) {
                throw ValidationException::withMessages([
                    'void_reason' => 'Applied payments must be cleared before this invoice can be voided.',
                ]);
            }

            $invoice->forceFill([
                'status' => Invoice::STATUS_VOID,
                'reminder_status' => Invoice::REMINDER_STATUS_NOT_DUE,
                'voided_at' => now(),
                'voided_by' => $actor->id,
                'void_reason' => $reason,
            ])->save();

            $invoice->reminderLogs()
                ->where('workspace_id', $workspace->id)
                ->where('status', ReminderLog::STATUS_PENDING)
                ->update(['status' => ReminderLog::STATUS_FAILED, 'error_message' => 'Invoice was voided before delivery.']);

            $this->recordActivity($workspace, $actor, $invoice, 'invoice.voided', 'Invoice voided.', [
                'reason' => $reason,
            ]);

            return $invoice->refresh();
        });
    }

    public function assertCanVoid(Invoice $invoice): void
    {
        if (in_array($invoice->status, [Invoice::STATUS_DRAFT, Invoice::STATUS_PAID, Invoice::STATUS_VOID], true)) {
            throw ValidationException::withMessages([
                'invoice' => match ($invoice->status) {
                    Invoice::STATUS_DRAFT => 'Draft invoices must be deleted instead of voided.',
                    Invoice::STATUS_PAID => 'Paid invoices cannot be voided by workspace users.',
                    default => 'This invoice is already void.',
                },
            ]);
        }
    }

    public function assertCanEdit(Invoice $invoice): void
    {
        $this->assertEditable($invoice);
    }

    private function assertEditable(Invoice $invoice): void
    {
        if ($invoice->status === Invoice::STATUS_VOID) {
            throw ValidationException::withMessages(['invoice' => 'Void invoices are read-only.']);
        }
    }

    private function assertDraft(Invoice $invoice): void
    {
        if ($invoice->status !== Invoice::STATUS_DRAFT) {
            throw ValidationException::withMessages(['invoice' => 'Only draft invoices support this action.']);
        }
    }

    private function assertPaidInvoiceFieldsUnchanged(Invoice $invoice, array $validated): void
    {
        $fields = [
            'invoice_number' => (string) $invoice->invoice_number,
            'client_id' => (int) $invoice->client_id,
            'currency_id' => (int) $invoice->currency_id,
            'issue_date' => $invoice->issue_date?->toDateString(),
            'due_date' => $invoice->due_date?->toDateString(),
            'status' => $invoice->status,
            'tax_amount' => (string) $invoice->tax_amount,
            'discount_amount' => (string) $invoice->discount_amount,
            'total_amount' => (string) $invoice->total_amount,
        ];

        foreach ($fields as $field => $current) {
            if (! array_key_exists($field, $validated)) {
                continue;
            }

            $submitted = $validated[$field];
            if (in_array($field, ['client_id', 'currency_id'], true)) {
                $submitted = (int) $submitted;
            } elseif (in_array($field, ['tax_amount', 'discount_amount', 'total_amount'], true)) {
                $submitted = (string) number_format((float) $submitted, 2, '.', '');
            } elseif (in_array($field, ['issue_date', 'due_date'], true)) {
                $submitted = (string) $submitted;
            } else {
                $submitted = (string) $submitted;
            }

            if ((string) $submitted !== (string) $current) {
                throw ValidationException::withMessages([
                    $field => 'Financial fields on paid invoices cannot be changed.',
                ]);
            }
        }

        if (array_key_exists('items', $validated)) {
            $currentItems = $invoice->items()->orderBy('id')->get()->map(fn ($item): array => [
                'item_name' => $item->item_name,
                'description' => $item->description,
                'quantity' => (int) $item->quantity,
                'unit_price' => number_format((float) $item->unit_price, 2, '.', ''),
            ])->values()->all();
            $submittedItems = collect($validated['items'])->map(fn (array $item): array => [
                'item_name' => $item['item_name'] ?? '',
                'description' => $item['description'] ?? '',
                'quantity' => (int) ($item['quantity'] ?? 0),
                'unit_price' => number_format((float) ($item['unit_price'] ?? 0), 2, '.', ''),
            ])->values()->all();

            if ($submittedItems !== $currentItems) {
                throw ValidationException::withMessages([
                    'items' => 'Line items on paid invoices cannot be changed.',
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function recordActivity(
        Workspace $workspace,
        ?User $actor,
        Invoice $invoice,
        string $type,
        string $description,
        array $properties = [],
    ): void {
        ActivityLog::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $actor?->id,
            'type' => $type,
            'description' => $description,
            'properties' => array_merge(['invoice_id' => $invoice->id, 'invoice_number' => $invoice->invoice_number], $properties),
        ]);
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
