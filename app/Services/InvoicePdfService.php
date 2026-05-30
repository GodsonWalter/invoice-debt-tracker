<?php

namespace App\Services;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoicePdfService
{
    public function loadInvoiceForPdf(Invoice $invoice): Invoice
    {
        return $invoice->loadMissing([
            'client',
            'items',
            'payments',
            'workspace.businessProfile',
            'workspace.currency',
            'currency',
        ]);
    }

    public function filename(Invoice $invoice): string
    {
        return 'invoice-'.preg_replace('/[^A-Za-z0-9\-_]/', '-', $invoice->invoice_number).'.pdf';
    }

    public function content(Invoice $invoice): string
    {
        $invoice = $this->loadInvoiceForPdf($invoice);

        return Pdf::loadView('invoices.pdf', [
            'workspace' => $invoice->workspace,
            'invoice' => $invoice,
        ])->setPaper('a4')->output();
    }
}
