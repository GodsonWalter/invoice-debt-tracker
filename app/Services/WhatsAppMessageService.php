<?php

namespace App\Services;

use App\Jobs\SendWhatsAppReminderJob;
use App\Models\Invoice;
use App\Models\ReminderSchedule;
use App\Models\WhatsAppMessageLog;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

class WhatsAppMessageService
{
    public function __construct(private readonly WhatsAppConnectionService $connectionService) {}

    public function queueReminder(Invoice $invoice, ReminderSchedule $schedule): ?WhatsAppMessageLog
    {
        if (! config('services.whatsapp.enabled')) {
            return null;
        }

        $workspace = $invoice->workspace;

        if (! $workspace?->is_active || ! $workspace->whatsapp_auto_reminders_enabled) {
            return null;
        }

        $recipientPhone = $this->normalizePhone($invoice->client?->phone);

        if (! $recipientPhone) {
            return null;
        }

        $connection = $this->connectionService->effectiveFor($workspace);
        $log = WhatsAppMessageLog::query()->firstOrCreate(
            ['idempotency_key' => 'reminder:'.$invoice->id.':'.$schedule->id],
            [
                'workspace_id' => $workspace->id,
                'whatsapp_connection_id' => $connection?->id,
                'invoice_id' => $invoice->id,
                'reminder_schedule_id' => $schedule->id,
                'recipient_phone' => $recipientPhone,
                'status' => WhatsAppMessageLog::STATUS_QUEUED,
                'message_type' => 'reminder',
            ],
        );

        if ($log->wasRecentlyCreated) {
            SendWhatsAppReminderJob::dispatch($log->id);
        }

        return $log;
    }

    public function invoiceShareUrl(Invoice $invoice): string
    {
        $recipientPhone = $this->normalizePhone($invoice->client?->phone);

        if (! $recipientPhone) {
            throw ValidationException::withMessages([
                'invoice' => 'The selected client must have a phone number in international format before it can be sent to WhatsApp.',
            ]);
        }

        $invoice->ensurePublicToken();
        $invoiceUrl = URL::temporarySignedRoute(
            'public.invoice.show',
            now()->addDays(30),
            ['token' => $invoice->public_token],
        );
        $clientName = $invoice->client?->name ?: 'there';
        $dueDate = $invoice->due_date?->format('M j, Y') ?? 'Not specified';
        $message = implode("\n", [
            'Hello '.$clientName.',',
            '',
            'Please find invoice '.$invoice->invoice_number.'.',
            'Outstanding balance: '.$invoice->formatMoney($invoice->remaining_balance),
            'Due date: '.$dueDate,
            '',
            'View your invoice here:',
            $invoiceUrl,
        ]);

        return 'https://wa.me/'.$recipientPhone.'?text='.rawurlencode($message);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    public function markSent(WhatsAppMessageLog $log, array $result, string $body): void
    {
        $log->forceFill([
            'status' => WhatsAppMessageLog::STATUS_SENT,
            'meta_message_id' => $result['message_id'] ?? null,
            'rendered_body' => $body,
            'request_payload' => $result['request'] ?? null,
            'response_payload' => $result['response'] ?? null,
            'error_message' => null,
            'attempts' => $log->attempts + 1,
            'sent_at' => now(),
        ])->save();
    }

    public function markFailed(WhatsAppMessageLog $log, ?\Throwable $exception = null): void
    {
        $log->forceFill([
            'status' => WhatsAppMessageLog::STATUS_FAILED,
            'error_message' => (string) str($exception?->getMessage() ?: 'WhatsApp reminder could not be sent.')->limit(1000),
            'attempts' => $log->attempts + 1,
        ])->save();
    }

    public function retry(WhatsAppMessageLog $log): void
    {
        $log->forceFill([
            'status' => WhatsAppMessageLog::STATUS_QUEUED,
            'error_message' => null,
        ])->save();

        SendWhatsAppReminderJob::dispatch($log->id);
    }

    public function updateDeliveryStatus(string $messageId, string $status, ?string $timestamp = null, ?string $error = null): void
    {
        $log = WhatsAppMessageLog::query()->where('meta_message_id', $messageId)->first();

        if (! $log) {
            return;
        }

        $attributes = [
            'status' => in_array($status, WhatsAppMessageLog::STATUSES, true) ? $status : $log->status,
            'error_message' => $error,
        ];

        if ($status === WhatsAppMessageLog::STATUS_DELIVERED) {
            $attributes['delivered_at'] = $timestamp ? now()->setTimestamp((int) $timestamp) : now();
        }

        if ($status === WhatsAppMessageLog::STATUS_READ) {
            $attributes['read_at'] = $timestamp ? now()->setTimestamp((int) $timestamp) : now();
        }

        $log->forceFill($attributes)->save();
    }

    private function normalizePhone(?string $phone): ?string
    {
        if (! filled($phone)) {
            return null;
        }

        $normalized = preg_replace('/[^0-9]/', '', $phone) ?? '';

        return preg_match('/^[1-9][0-9]{7,14}$/', $normalized) ? $normalized : null;
    }
}
