<?php

namespace App\Services;

use App\Jobs\SendInvoiceMailJob;
use App\Mail\InvoiceMail;
use App\Models\Invoice;
use App\Models\InvoiceEmailLog;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class InvoiceEmailService
{
    public function __construct(private InvoicePdfService $invoicePdfService) {}

    public function queueInvoice(Workspace $workspace, Invoice $invoice, User $sender): InvoiceEmailLog
    {
        $invoice = $this->invoiceForEmail($workspace, $invoice);

        $this->validateCanEmail($invoice);

        $log = InvoiceEmailLog::create([
            'invoice_id' => $invoice->id,
            'workspace_id' => $workspace->id,
            'sent_by' => $sender->id,
            'recipient_email' => $invoice->client->email,
            'subject' => $this->subject($invoice),
            'status' => InvoiceEmailLog::STATUS_PENDING,
        ]);

        SendInvoiceMailJob::dispatch($log->id);

        return $log;
    }

    public function sendQueuedInvoice(InvoiceEmailLog $emailLog): void
    {
        $emailLog->loadMissing([
            'invoice.client',
            'invoice.items',
            'invoice.payments',
            'invoice.currency',
            'invoice.workspace.businessProfile',
            'invoice.workspace.currency',
        ]);

        $invoice = $emailLog->invoice;
        $this->validateCanEmail($invoice, allowDraft: true);
        $invoice->ensurePublicToken();

        $pdfContent = $this->invoicePdfService->content($invoice);

        Mail::to($emailLog->recipient_email)->send(new InvoiceMail(
            invoice: $invoice,
            mailSubject: $emailLog->subject,
            pdfContent: $pdfContent,
            pdfFilename: $this->invoicePdfService->filename($invoice),
        ));

        DB::transaction(function () use ($emailLog, $invoice): void {
            $emailLog->forceFill([
                'status' => InvoiceEmailLog::STATUS_SENT,
                'error_message' => null,
                'sent_at' => now(),
            ])->save();

            if ($invoice->status === Invoice::STATUS_DRAFT) {
                $invoice->forceFill(['status' => Invoice::STATUS_SENT])->save();
            }
        });
    }

    public function markFailed(InvoiceEmailLog $emailLog, ?\Throwable $exception = null): void
    {
        $emailLog->forceFill([
            'status' => InvoiceEmailLog::STATUS_FAILED,
            'error_message' => (string) str($exception?->getMessage() ?: 'Invoice email could not be sent.')->limit(1000),
        ])->save();
    }

    public function invoiceForEmail(Workspace $workspace, Invoice $invoice): Invoice
    {
        return $workspace->invoices()
            ->with([
                'client',
                'items',
                'payments',
                'currency',
                'workspace.businessProfile',
                'workspace.currency',
            ])
            ->where('id', $invoice->id)
            ->firstOrFail();
    }

    public function subject(Invoice $invoice): string
    {
        $businessName = $invoice->businessProfile()?->display_name ?? $invoice->workspace?->name ?? config('app.name');

        return 'Invoice '.$invoice->invoice_number.' from '.$businessName;
    }

    private function validateCanEmail(Invoice $invoice, bool $allowDraft = false): void
    {
        if (! $invoice->client) {
            throw ValidationException::withMessages([
                'invoice' => 'This invoice does not have a client.',
            ]);
        }

        if (! $invoice->client->email) {
            throw ValidationException::withMessages([
                'invoice' => 'The selected client does not have an email address.',
            ]);
        }

        if ($invoice->items->isEmpty()) {
            throw ValidationException::withMessages([
                'invoice' => 'This invoice must contain at least one line item before it can be sent.',
            ]);
        }

        if (! $allowDraft && $invoice->status === Invoice::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'invoice' => 'Draft invoices cannot be emailed. Mark the invoice as sent first.',
            ]);
        }
    }
}
