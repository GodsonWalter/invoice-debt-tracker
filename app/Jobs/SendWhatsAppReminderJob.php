<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Models\WhatsAppMessageLog;
use App\Services\ReminderContentService;
use App\Services\WhatsAppCloudApiService;
use App\Services\WhatsAppConnectionService;
use App\Services\WhatsAppMessageService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SendWhatsAppReminderJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 10;

    public int $timeout = 120;

    public function __construct(public int $whatsappMessageLogId) {}

    public function handle(
        WhatsAppMessageService $messageService,
        WhatsAppConnectionService $connectionService,
        WhatsAppCloudApiService $cloudApi,
        ReminderContentService $contentService,
    ): void {
        $log = $this->messageLog();

        try {
            $invoice = $log->invoice;
            $workspace = $log->workspace;

            if (! $workspace?->is_active) {
                $messageService->markFailed($log, new \RuntimeException('The workspace is no longer active.'));

                return;
            }

            if (! config('services.whatsapp.enabled')) {
                $messageService->markFailed($log, new \RuntimeException('WhatsApp Cloud API is disabled for this deployment.'));

                return;
            }

            if (! $workspace->whatsapp_auto_reminders_enabled) {
                $messageService->markFailed($log, new \RuntimeException('WhatsApp auto reminders are disabled for this workspace.'));

                return;
            }

            if ($invoice->status === Invoice::STATUS_VOID) {
                $messageService->markFailed($log, new \RuntimeException('The invoice is void.'));

                return;
            }

            if ($invoice->status === Invoice::STATUS_PAID || $invoice->remaining_balance <= 0) {
                $messageService->markFailed($log, new \RuntimeException('The invoice has no outstanding balance.'));

                return;
            }

            $connection = $connectionService->effectiveFor($workspace);

            if (! $connection?->isReady()) {
                $messageService->markFailed($log, new \RuntimeException('The selected WhatsApp connection is not ready.'));

                return;
            }

            $content = $contentService->render($invoice, $log->reminderSchedule);
            $result = $cloudApi->sendReminder(
                connection: $connection,
                invoice: $invoice,
                recipientPhone: $log->recipient_phone,
                renderedBody: $content['body'],
                reminderType: $content['label'],
            );
            $messageService->markSent($log, $result, $content['body']);
        } catch (Throwable $exception) {
            $messageService->markFailed($log, $exception);

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $log = WhatsAppMessageLog::query()->find($this->whatsappMessageLogId);

        if ($log) {
            app(WhatsAppMessageService::class)->markFailed($log, $exception);
        }
    }

    private function messageLog(): WhatsAppMessageLog
    {
        return WhatsAppMessageLog::query()
            ->with([
                'workspace',
                'invoice.client',
                'invoice.payments',
                'invoice.currency',
                'invoice.workspace.businessProfile',
                'invoice.workspace.currency',
                'reminderSchedule',
            ])
            ->findOrFail($this->whatsappMessageLogId);
    }
}
