<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Models\SmsMessageLog;
use App\Services\SmsConnectionService;
use App\Services\SmsMessageService;
use App\Services\SmsProviderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SendSmsReminderJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 10;

    public int $timeout = 120;

    public function __construct(public int $smsMessageLogId) {}

    public function handle(
        SmsMessageService $messageService,
        SmsConnectionService $connectionService,
        SmsProviderService $provider,
    ): void {
        $log = $this->messageLog();

        try {
            $invoice = $log->invoice;
            $workspace = $log->workspace;

            if (! $workspace?->is_active) {
                $messageService->markFailed($log, new \RuntimeException('The workspace is no longer active.'));

                return;
            }

            if (! config('services.sms.enabled')) {
                $messageService->markFailed($log, new \RuntimeException('SMS sending is disabled for this deployment.'));

                return;
            }

            if ($log->message_type === 'reminder' && ! $workspace->sms_auto_reminders_enabled) {
                $messageService->markFailed($log, new \RuntimeException('SMS auto reminders are disabled for this workspace.'));

                return;
            }

            if ($invoice->status === Invoice::STATUS_VOID) {
                $messageService->markFailed($log, new \RuntimeException('The invoice is void.'));

                return;
            }

            if ($log->message_type === 'reminder'
                && ($invoice->status === Invoice::STATUS_PAID || $invoice->remaining_balance <= 0)) {
                $messageService->markFailed($log, new \RuntimeException('The invoice has no outstanding balance.'));

                return;
            }

            $connection = $connectionService->effectiveFor($workspace);

            if (! $connection?->isReady()) {
                $messageService->markFailed($log, new \RuntimeException('The selected SMS connection is not ready.'));

                return;
            }

            $content = $messageService->render($invoice, $log->reminderSchedule, $connection);
            $result = $provider->send($connection, $log->recipient_phone, $content['body']);
            $messageService->markSent($log, $result, $content['body']);
        } catch (Throwable $exception) {
            $messageService->markFailed($log, $exception);

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $log = SmsMessageLog::query()->find($this->smsMessageLogId);

        if ($log) {
            app(SmsMessageService::class)->markFailed($log, $exception);
        }
    }

    private function messageLog(): SmsMessageLog
    {
        return SmsMessageLog::query()
            ->with([
                'workspace',
                'invoice.client',
                'invoice.payments',
                'invoice.currency',
                'invoice.workspace.businessProfile',
                'invoice.workspace.currency',
                'reminderSchedule',
            ])
            ->findOrFail($this->smsMessageLogId);
    }
}
