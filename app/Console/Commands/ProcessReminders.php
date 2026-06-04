<?php

namespace App\Console\Commands;

use App\Services\ReminderService;
use Illuminate\Console\Command;

class ProcessReminders extends Command
{
    protected $signature = 'reminders:process';

    protected $description = 'Process invoice reminder schedules and queue reminder emails.';

    public function handle(ReminderService $reminderService): int
    {
        $summary = $reminderService->process();

        $this->info('Reminder processing completed.');
        $this->line('Schedules processed: '.$summary['schedules_processed']);
        $this->line('Invoices found: '.$summary['invoices_found']);
        $this->line('Reminders created: '.$summary['reminders_created']);

        return self::SUCCESS;
    }
}
