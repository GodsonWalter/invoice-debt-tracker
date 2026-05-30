<?php

namespace App\Http\Controllers;

use App\Services\InvoicePdfService;
use App\Services\PublicInvoiceService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;

class PublicInvoiceController extends Controller
{
    public function __construct(
        private PublicInvoiceService $publicInvoiceService,
        private InvoicePdfService $invoicePdfService,
    ) {}

    public function show(string $token): View
    {
        $invoice = $this->publicInvoiceService->markViewed(
            $this->publicInvoiceService->findPublicInvoice($token)
        );

        return view('public-invoices.show', [
            'invoice' => $invoice,
            'workspace' => $invoice->workspace,
            'printMode' => false,
        ]);
    }

    public function downloadPdf(string $token): Response
    {
        $invoice = $this->publicInvoiceService->findPublicInvoice($token);

        $this->publicInvoiceService->markDownloaded($invoice);

        return response($this->invoicePdfService->content($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename='.$this->invoicePdfService->filename($invoice),
        ]);
    }

    public function print(string $token): View
    {
        $invoice = $this->publicInvoiceService->markPrinted(
            $this->publicInvoiceService->findPublicInvoice($token)
        );

        return view('public-invoices.show', [
            'invoice' => $invoice,
            'workspace' => $invoice->workspace,
            'printMode' => true,
        ]);
    }
}
