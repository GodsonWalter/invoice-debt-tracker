<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class PublicInvoiceService
{
    public function findPublicInvoice(string $token): Invoice
    {
        $invoice = Invoice::query()
            ->with([
                'client',
                'items',
                'payments' => fn ($query) => $query
                    ->orderByDesc('payment_date')
                    ->orderByDesc('created_at'),
                'currency',
                'workspace.businessProfile',
                'workspace.currency',
            ])
            ->where('public_token', $token)
            ->whereHas('workspace', fn ($query) => $query->where('is_active', true))
            ->firstOrFail();

        if (in_array($invoice->status, [Invoice::STATUS_DRAFT, Invoice::STATUS_VOID], true)) {
            throw (new ModelNotFoundException)->setModel(Invoice::class);
        }

        return $invoice;
    }

    public function markViewed(Invoice $invoice): Invoice
    {
        if (! $invoice->viewed_at) {
            $invoice->forceFill(['viewed_at' => now()])->save();
        }

        return $invoice->refresh()->loadMissing([
            'client',
            'items',
            'payments',
            'currency',
            'workspace.businessProfile',
            'workspace.currency',
        ]);
    }

    public function markDownloaded(Invoice $invoice): void
    {
        $invoice->forceFill(['downloaded_at' => now()])->save();
    }

    public function markPrinted(Invoice $invoice): Invoice
    {
        $invoice->forceFill(['printed_at' => now()])->save();

        return $invoice->refresh()->loadMissing([
            'client',
            'items',
            'payments',
            'currency',
            'workspace.businessProfile',
            'workspace.currency',
        ]);
    }
}
