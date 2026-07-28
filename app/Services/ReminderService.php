<?php

namespace App\Services;

use App\Jobs\SendReminderEmailJob;
use App\Models\Invoice;
use App\Models\ReminderLog;
use App\Models\ReminderSchedule;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

class ReminderService
{
    /**
     * @return array{schedules_processed: int, invoices_found: int, reminders_created: int}
     */
    public function process(?CarbonInterface $date = null): array
    {
        $today = $this->normalizeDate($date);
        $summary = [
            'schedules_processed' => 0,
            'invoices_found' => 0,
            'reminders_created' => 0,
        ];

        ReminderSchedule::query()
            ->where('is_active', true)
            ->orderBy('workspace_id')
            ->orderBy('direction')
            ->orderBy('days_offset')
            ->each(function (ReminderSchedule $schedule) use (&$summary, $today): void {
                $summary['schedules_processed']++;

                $invoices = $this->matchingInvoices($schedule, $today);
                $summary['invoices_found'] += $invoices->count();

                foreach ($invoices as $invoice) {
                    $reminderLog = $this->createReminderLog($invoice, $schedule);

                    if (! $reminderLog) {
                        continue;
                    }

                    SendReminderEmailJob::dispatch($reminderLog->id);
                    $this->markInvoiceScheduled($invoice, $today);

                    $summary['reminders_created']++;
                }
            });

        return $summary;
    }

    public function markSent(ReminderLog $reminderLog): void
    {
        $reminderLog->loadMissing('invoice');

        DB::transaction(function () use ($reminderLog): void {
            $reminderLog->forceFill([
                'status' => ReminderLog::STATUS_SENT,
                'sent_at' => now(),
                'error_message' => null,
            ])->save();

            $this->updateInvoiceReminderStatus(
                invoice: $reminderLog->invoice,
                status: $this->sentStatusForInvoice($reminderLog->invoice),
            );
        });
    }

    public function markFailed(ReminderLog $reminderLog, ?\Throwable $exception = null): void
    {
        $reminderLog->forceFill([
            'status' => ReminderLog::STATUS_FAILED,
            'error_message' => (string) str($exception?->getMessage() ?: 'Reminder email could not be sent.')->limit(1000),
        ])->save();
    }

    /**
     * @return Collection<int, Invoice>
     */
    private function matchingInvoices(ReminderSchedule $schedule, CarbonInterface $today): Collection
    {
        $targetDueDate = $this->targetDueDate($schedule, $today);

        return Invoice::query()
            ->with(['client', 'payments', 'currency', 'workspace.currency'])
            ->where('workspace_id', $schedule->workspace_id)
            ->whereDate('due_date', $targetDueDate->toDateString())
            ->whereIn('status', [
                Invoice::STATUS_SENT,
                Invoice::STATUS_PARTIAL,
                Invoice::STATUS_OVERDUE,
            ])
            ->whereHas('client', function ($query): void {
                $query->whereNotNull('email')
                    ->where('email', '!=', '');
            })
            ->get()
            ->filter(fn (Invoice $invoice): bool => $invoice->remaining_balance > 0)
            ->values();
    }

    private function targetDueDate(ReminderSchedule $schedule, CarbonInterface $today): CarbonImmutable
    {
        $date = $this->normalizeDate($today);

        if ($schedule->direction === ReminderSchedule::DIRECTION_AFTER_DUE) {
            return $date->subDays($schedule->days_offset);
        }

        return $date->addDays($schedule->days_offset);
    }

    private function createReminderLog(Invoice $invoice, ReminderSchedule $schedule): ?ReminderLog
    {
        $workspaceId = (int) $schedule->workspace_id;

        if ((int) $invoice->workspace_id !== $workspaceId) {
            throw new LogicException('The invoice and reminder schedule must belong to the same workspace.');
        }

        $reminderLog = ReminderLog::query()->firstOrCreate(
            [
                'invoice_id' => $invoice->id,
                'reminder_schedule_id' => $schedule->id,
            ],
            [
                'workspace_id' => $workspaceId,
                'recipient_email' => $invoice->client->email,
                'status' => ReminderLog::STATUS_PENDING,
            ],
        );

        if ((int) $reminderLog->workspace_id !== $workspaceId) {
            throw new LogicException('The existing reminder log belongs to another workspace.');
        }

        return $reminderLog->wasRecentlyCreated ? $reminderLog : null;
    }

    private function markInvoiceScheduled(Invoice $invoice, CarbonInterface $today): void
    {
        $this->updateInvoiceReminderStatus(
            invoice: $invoice,
            status: $this->isOverdue($invoice, $today)
                ? Invoice::REMINDER_STATUS_OVERDUE
                : Invoice::REMINDER_STATUS_SCHEDULED,
        );
    }

    private function sentStatusForInvoice(Invoice $invoice): string
    {
        return $this->isOverdue($invoice, now())
            ? Invoice::REMINDER_STATUS_OVERDUE
            : Invoice::REMINDER_STATUS_SENT;
    }

    private function updateInvoiceReminderStatus(Invoice $invoice, string $status): void
    {
        $invoice->forceFill(['reminder_status' => $status])->save();
    }

    private function isOverdue(Invoice $invoice, CarbonInterface $today): bool
    {
        return $invoice->due_date?->lt($this->normalizeDate($today)) ?? false;
    }

    private function normalizeDate(?CarbonInterface $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date ?? now())->startOfDay();
    }
}
