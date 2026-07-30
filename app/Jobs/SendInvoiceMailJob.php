<?php

namespace App\Jobs;

use App\Models\InvoiceEmailLog;
use App\Services\InvoiceEmailService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SendInvoiceMailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 10;

    public int $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $invoiceEmailLogId) {}

    /**
     * Execute the job.
     */
    public function handle(InvoiceEmailService $invoiceEmailService): void
    {
        $emailLog = InvoiceEmailLog::query()->findOrFail($this->invoiceEmailLogId);

        $invoiceEmailService->sendQueuedInvoice($emailLog);
    }

    public function failed(?Throwable $exception): void
    {
        $emailLog = InvoiceEmailLog::query()->find($this->invoiceEmailLogId);

        if ($emailLog) {
            app(InvoiceEmailService::class)->markFailed($emailLog, $exception);
        }
    }
}
