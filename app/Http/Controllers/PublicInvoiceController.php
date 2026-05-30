<?php

namespace App\Http\Controllers;

use App\Services\InvoicePdfService;
use App\Services\PublicInvoiceService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;

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
            'pdfUrl' => $this->signedUrl('public.invoice.pdf', $token),
            'printUrl' => $this->signedUrl('public.invoice.print', $token),
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
            'pdfUrl' => $this->signedUrl('public.invoice.pdf', $token),
            'printUrl' => $this->signedUrl('public.invoice.print', $token),
        ]);
    }

    private function signedUrl(string $routeName, string $token): string
    {
        return URL::temporarySignedRoute($routeName, now()->addDays(30), ['token' => $token]);
    }
}
