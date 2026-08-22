<?php

namespace App\Services;

use App\Jobs\SendSmsReminderJob;
use App\Models\EmailTemplate;
use App\Models\Invoice;
use App\Models\ReminderSchedule;
use App\Models\SmsConnection;
use App\Models\SmsMessageLog;
use App\Models\SmsTemplate;
use App\Models\Workspace;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

class SmsMessageService
{
    public function __construct(
        private readonly SmsConnectionService $connectionService,
        private readonly ReminderContentService $contentService,
        private readonly TemplateRenderer $templateRenderer,
    ) {}

    public function queueReminder(Invoice $invoice, ReminderSchedule $schedule): ?SmsMessageLog
    {
        if ((int) $invoice->workspace_id !== (int) $schedule->workspace_id) {
            throw new LogicException('The invoice and reminder schedule must belong to the same workspace.');
        }

        if (! config('services.sms.enabled')) {
            return null;
        }

        $workspace = $invoice->workspace;

        if (! $workspace?->is_active || ! $workspace->sms_auto_reminders_enabled) {
            return null;
        }

        $recipientPhone = $this->normalizePhone($invoice->client?->phone);

        if (! $recipientPhone) {
            return null;
        }

        $connection = $this->connectionService->effectiveFor($workspace);
        $log = SmsMessageLog::query()->firstOrCreate(
            ['idempotency_key' => 'reminder:'.$invoice->id.':'.$schedule->id],
            [
                'workspace_id' => $workspace->id,
                'sms_connection_id' => $connection?->id,
                'invoice_id' => $invoice->id,
                'reminder_schedule_id' => $schedule->id,
                'recipient_phone' => $recipientPhone,
                'sender' => $connection?->sender,
                'status' => SmsMessageLog::STATUS_QUEUED,
                'message_type' => 'reminder',
            ],
        );

        if ($log->wasRecentlyCreated) {
            SendSmsReminderJob::dispatch($log->id);
        }

        return $log;
    }

    public function queueInvoice(Workspace $workspace, Invoice $invoice): SmsMessageLog
    {
        if ((int) $invoice->workspace_id !== (int) $workspace->id) {
            throw new LogicException('The invoice must belong to the selected workspace.');
        }

        if (! config('services.sms.enabled')) {
            throw ValidationException::withMessages(['sms' => 'SMS sending is disabled for this deployment.']);
        }

        $recipientPhone = $this->normalizePhone($invoice->client?->phone);

        if (! $recipientPhone) {
            throw ValidationException::withMessages(['sms' => 'The selected client must have a valid international phone number before an SMS can be sent.']);
        }

        $connection = $this->connectionService->effectiveFor($workspace);

        if (! $connection?->isReady()) {
            throw ValidationException::withMessages(['sms' => 'The selected SMS connection is not ready.']);
        }

        $log = SmsMessageLog::query()->create([
            'workspace_id' => $workspace->id,
            'sms_connection_id' => $connection->id,
            'invoice_id' => $invoice->id,
            'recipient_phone' => $recipientPhone,
            'sender' => $connection->sender,
            'status' => SmsMessageLog::STATUS_QUEUED,
            'idempotency_key' => 'invoice:'.$invoice->id.':'.Str::uuid(),
            'message_type' => 'invoice',
        ]);

        SendSmsReminderJob::dispatch($log->id);

        return $log;
    }

    /**
     * @return array{body:string,type:string,label:string}
     */
    public function render(Invoice $invoice, ?ReminderSchedule $schedule, SmsConnection $connection): array
    {
        $content = $schedule
            ? $this->contentService->render($invoice, $schedule)
            : $this->contentService->renderForType($invoice, $this->manualType($invoice));
        $template = $connection->templates()
            ->where('type', $content['type'])
            ->where('is_active', true)
            ->first();
        $body = $template instanceof SmsTemplate
            ? $this->templateRenderer->render($template->body, $invoice, ['reminder_type' => $content['label']])
            : $content['body'];

        return [
            'body' => $this->plainText($body),
            'type' => $content['type'],
            'label' => $content['label'],
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     */
    public function markSent(SmsMessageLog $log, array $result, string $body): void
    {
        $log->forceFill([
            'status' => SmsMessageLog::STATUS_SENT,
            'provider_message_id' => $result['message_id'] ?? null,
            'rendered_body' => $body,
            'request_payload' => $result['request'] ?? null,
            'response_payload' => $result['response'] ?? null,
            'error_message' => null,
            'failed_at' => null,
            'attempts' => $log->attempts + 1,
            'sent_at' => now(),
        ])->save();
    }

    public function markFailed(SmsMessageLog $log, ?\Throwable $exception = null): void
    {
        $log->forceFill([
            'status' => SmsMessageLog::STATUS_FAILED,
            'error_message' => (string) str($exception?->getMessage() ?: 'SMS could not be sent.')->limit(1000),
            'attempts' => $log->attempts + 1,
            'failed_at' => now(),
        ])->save();
    }

    public function retry(SmsMessageLog $log): void
    {
        $log->forceFill([
            'status' => SmsMessageLog::STATUS_QUEUED,
            'error_message' => null,
            'failed_at' => null,
        ])->save();

        SendSmsReminderJob::dispatch($log->id);
    }

    public function updateDeliveryStatus(string $messageId, string $status, ?string $error = null): void
    {
        $log = SmsMessageLog::query()->where('provider_message_id', $messageId)->first();

        if (! $log) {
            return;
        }

        $normalizedStatus = match (strtolower($status)) {
            'delivered' => SmsMessageLog::STATUS_DELIVERED,
            'failed', 'undelivered' => SmsMessageLog::STATUS_FAILED,
            'sent' => SmsMessageLog::STATUS_SENT,
            'accepted', 'queued', 'sending' => SmsMessageLog::STATUS_QUEUED,
            default => $log->status,
        };
        $attributes = [
            'status' => $normalizedStatus,
            'error_message' => $error,
        ];

        if ($normalizedStatus === SmsMessageLog::STATUS_DELIVERED) {
            $attributes['delivered_at'] = now();
        }

        if ($normalizedStatus === SmsMessageLog::STATUS_FAILED) {
            $attributes['failed_at'] = now();
        }

        $log->forceFill($attributes)->save();
    }

    public function normalizePhone(?string $phone): ?string
    {
        if (! filled($phone)) {
            return null;
        }

        $normalized = preg_replace('/[^0-9]/', '', $phone) ?? '';

        return preg_match('/^[1-9][0-9]{7,14}$/', $normalized) ? $normalized : null;
    }

    private function manualType(Invoice $invoice): string
    {
        if ($invoice->due_date?->lt(now()->startOfDay())) {
            return EmailTemplate::TYPE_OVERDUE;
        }

        if ($invoice->due_date?->isToday()) {
            return EmailTemplate::TYPE_DUE_TODAY;
        }

        return EmailTemplate::TYPE_BEFORE_DUE;
    }

    private function plainText(string $body): string
    {
        $body = preg_replace('/<\s*br\s*\/?\s*>/i', "\n", $body) ?? $body;
        $body = html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace("/[ \t]+\n/", "\n", $body));
    }
}
