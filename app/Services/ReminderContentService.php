<?php

namespace App\Services;

use App\Models\EmailTemplate;
use App\Models\Invoice;
use App\Models\ReminderSchedule;

class ReminderContentService
{
    public function __construct(private readonly TemplateRenderer $templateRenderer) {}

    /**
     * @return array{subject:string, body:string, type:string, label:string}
     */
    public function render(Invoice $invoice, ReminderSchedule $schedule): array
    {
        $type = EmailTemplate::typeForReminderSchedule($schedule);

        return $this->renderForType($invoice, $type);
    }

    /**
     * @return array{subject:string, body:string, type:string, label:string}
     */
    public function renderForType(Invoice $invoice, string $type): array
    {
        $label = match ($type) {
            EmailTemplate::TYPE_DUE_TODAY => 'Due Today',
            EmailTemplate::TYPE_OVERDUE => 'Overdue',
            default => 'Before Due',
        };
        $template = EmailTemplate::query()
            ->where('workspace_id', $invoice->workspace_id)
            ->where('type', $type)
            ->where('is_active', true)
            ->first();

        if ($template) {
            return [
                'subject' => $template->renderSubject($invoice, $this->templateRenderer),
                'body' => $template->renderBody($invoice, $this->templateRenderer),
                'type' => $type,
                'label' => $label,
            ];
        }

        $default = EmailTemplate::defaultContentForType($type);

        return [
            'subject' => $this->templateRenderer->render($default['subject'], $invoice, ['reminder_type' => $label]),
            'body' => $this->templateRenderer->render($default['body'], $invoice, ['reminder_type' => $label]),
            'type' => $type,
            'label' => $label,
        ];
    }
}
