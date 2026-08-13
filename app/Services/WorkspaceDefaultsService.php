<?php

namespace App\Services;

use App\Models\EmailTemplate;
use App\Models\ReminderSchedule;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class WorkspaceDefaultsService
{
    public function provision(Workspace $workspace): void
    {
        DB::transaction(function () use ($workspace): void {
            $this->provisionReminderSchedules($workspace);
            $this->provisionEmailTemplates($workspace);
        });
    }

    public function provisionReminderSchedules(Workspace $workspace): void
    {
        foreach ($this->reminderScheduleDefaults() as $attributes) {
            $workspace->reminderSchedules()->firstOrCreate(
                [
                    'direction' => $attributes['direction'],
                    'days_offset' => $attributes['days_offset'],
                ],
                [
                    'name' => $attributes['name'],
                    'is_active' => true,
                    'include_invoice_pdf' => $attributes['include_invoice_pdf'],
                ],
            );
        }
    }

    public function provisionEmailTemplates(Workspace $workspace): void
    {
        foreach (EmailTemplate::TYPES as $type) {
            $content = EmailTemplate::defaultContentForType($type);

            $workspace->emailTemplates()->firstOrCreate(
                ['type' => $type],
                [
                    'name' => $this->emailTemplateName($type),
                    'subject' => $content['subject'],
                    'body' => $content['body'],
                    'is_default' => true,
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * @return array<int, array{name: string, days_offset: int, direction: string, include_invoice_pdf: bool}>
     */
    private function reminderScheduleDefaults(): array
    {
        return [
            [
                'name' => '3 Days Before Due',
                'days_offset' => 3,
                'direction' => ReminderSchedule::DIRECTION_BEFORE_DUE,
                'include_invoice_pdf' => false,
            ],
            [
                'name' => 'Due Today',
                'days_offset' => 0,
                'direction' => ReminderSchedule::DIRECTION_BEFORE_DUE,
                'include_invoice_pdf' => true,
            ],
            [
                'name' => '7 Days Overdue',
                'days_offset' => 7,
                'direction' => ReminderSchedule::DIRECTION_AFTER_DUE,
                'include_invoice_pdf' => true,
            ],
            [
                'name' => '14 Days Overdue',
                'days_offset' => 14,
                'direction' => ReminderSchedule::DIRECTION_AFTER_DUE,
                'include_invoice_pdf' => true,
            ],
        ];
    }

    private function emailTemplateName(string $type): string
    {
        return match ($type) {
            EmailTemplate::TYPE_DUE_TODAY => 'Invoice Due Today',
            EmailTemplate::TYPE_OVERDUE => 'Payment Overdue Notice',
            default => 'Invoice Reminder',
        };
    }
}
