<?php

namespace App\Jobs;

use App\Mail\ReminderMail;
use App\Models\EmailTemplate;
use App\Models\ReminderLog;
use App\Services\ReminderService;
use App\Services\TemplateRenderer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendReminderEmailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 10;

    public int $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $reminderLogId) {}

    /**
     * Execute the job.
     */
    public function handle(ReminderService $reminderService, TemplateRenderer $templateRenderer): void
    {
        $reminderLog = $this->reminderLog();

        try {
            $invoice = $reminderLog->invoice;
            $workspace = $invoice?->workspace;

            if (! $workspace || ! $workspace->is_active) {
                $reminderService->markFailed($reminderLog, new \RuntimeException('The workspace is no longer active.'));

                return;
            }

            $invoice->ensurePublicToken();
            [$subject, $body] = $this->renderTemplate($reminderLog, $templateRenderer);

            Mail::to($reminderLog->recipient_email)->send(new ReminderMail(
                invoice: $invoice,
                client: $invoice->client,
                reminderSchedule: $reminderLog->reminderSchedule,
                renderedSubject: $subject,
                renderedBody: $body,
            ));

            $reminderService->markSent($reminderLog);
        } catch (Throwable $exception) {
            $reminderService->markFailed($reminderLog, $exception);

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $reminderLog = ReminderLog::query()->find($this->reminderLogId);

        if ($reminderLog) {
            app(ReminderService::class)->markFailed($reminderLog, $exception);
        }
    }

    private function reminderLog(): ReminderLog
    {
        return ReminderLog::query()
            ->with([
                'invoice.client',
                'invoice.payments',
                'invoice.currency',
                'invoice.workspace.businessProfile',
                'invoice.workspace.currency',
                'reminderSchedule',
            ])
            ->findOrFail($this->reminderLogId);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function renderTemplate(ReminderLog $reminderLog, TemplateRenderer $templateRenderer): array
    {
        $invoice = $reminderLog->invoice;
        $templateType = EmailTemplate::typeForReminderSchedule($reminderLog->reminderSchedule);
        $template = EmailTemplate::query()
            ->where('workspace_id', $invoice->workspace_id)
            ->where('type', $templateType)
            ->where('is_active', true)
            ->first();

        if ($template) {
            return [
                $template->renderSubject($invoice, $templateRenderer),
                $template->renderBody($invoice, $templateRenderer),
            ];
        }

        $defaultContent = EmailTemplate::defaultContentForType($templateType);
        $reminderType = match ($templateType) {
            EmailTemplate::TYPE_DUE_TODAY => 'Due Today',
            EmailTemplate::TYPE_OVERDUE => 'Overdue',
            default => 'Before Due',
        };

        return [
            $templateRenderer->render($defaultContent['subject'], $invoice, ['reminder_type' => $reminderType]),
            $templateRenderer->render($defaultContent['body'], $invoice, ['reminder_type' => $reminderType]),
        ];
    }
}
