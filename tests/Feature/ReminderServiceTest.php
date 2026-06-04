<?php

use App\Jobs\SendReminderEmailJob;
use App\Mail\ReminderMail;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ReminderLog;
use App\Models\ReminderSchedule;
use App\Models\User;
use App\Models\Workspace;
use App\Services\ReminderService;
use App\Services\TemplateRenderer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

afterEach(function (): void {
    Carbon::setTestNow();
});

/**
 * @return array{0: Workspace, 1: Client, 2: Invoice}
 */
function createReminderInvoiceFixture(
    string $dueDate,
    string $status = Invoice::STATUS_SENT,
    ?string $clientEmail = 'billing@example.test',
    float $totalAmount = 1000,
): array {
    $user = User::factory()->create();

    $workspace = Workspace::create([
        'owner_id' => $user->id,
        'name' => 'Reminder Workspace',
        'slug' => 'reminder-workspace',
        'subdomain' => 'reminder-workspace',
        'invoice_prefix' => 'REM',
        'is_active' => true,
    ]);

    $client = Client::create([
        'workspace_id' => $workspace->id,
        'name' => 'Reminder Client',
        'email' => $clientEmail,
    ]);

    $invoice = Invoice::create([
        'workspace_id' => $workspace->id,
        'client_id' => $client->id,
        'invoice_number' => 'REM-2026-0001',
        'issue_date' => '2026-06-01',
        'due_date' => $dueDate,
        'status' => $status,
        'subtotal' => $totalAmount,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => $totalAmount,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'item_name' => 'Reminder work',
        'description' => 'Reminder test invoice item',
        'quantity' => 1,
        'unit_price' => $totalAmount,
        'total_price' => $totalAmount,
    ]);

    return [$workspace, $client, $invoice];
}

function createReminderScheduleFixture(
    Workspace $workspace,
    int $daysOffset,
    string $direction,
    string $name = 'Reminder Schedule',
): ReminderSchedule {
    return ReminderSchedule::create([
        'workspace_id' => $workspace->id,
        'name' => $name,
        'days_offset' => $daysOffset,
        'direction' => $direction,
        'is_active' => true,
    ]);
}

test('before due reminders are queued when invoice due date matches today plus offset', function () {
    Carbon::setTestNow('2026-06-17 09:00:00');
    Queue::fake();

    [$workspace, $client, $invoice] = createReminderInvoiceFixture('2026-06-20');
    $schedule = createReminderScheduleFixture(
        workspace: $workspace,
        daysOffset: 3,
        direction: ReminderSchedule::DIRECTION_BEFORE_DUE,
        name: '3 Days Before Due',
    );

    $summary = app(ReminderService::class)->process();

    $reminderLog = ReminderLog::query()->first();

    expect($summary['schedules_processed'])->toBe(1);
    expect($summary['invoices_found'])->toBe(1);
    expect($summary['reminders_created'])->toBe(1);

    expect($reminderLog)
        ->not->toBeNull()
        ->invoice_id->toBe($invoice->id)
        ->reminder_schedule_id->toBe($schedule->id)
        ->recipient_email->toBe($client->email)
        ->status->toBe(ReminderLog::STATUS_PENDING);

    expect($invoice->refresh()->reminder_status)->toBe(Invoice::REMINDER_STATUS_SCHEDULED);

    Queue::assertPushed(SendReminderEmailJob::class, 1);
});

test('due today reminders are queued when offset is zero', function () {
    Carbon::setTestNow('2026-06-20 09:00:00');
    Queue::fake();

    [$workspace, , $invoice] = createReminderInvoiceFixture('2026-06-20');
    createReminderScheduleFixture(
        workspace: $workspace,
        daysOffset: 0,
        direction: ReminderSchedule::DIRECTION_BEFORE_DUE,
        name: 'Due Today',
    );

    app(ReminderService::class)->process();

    expect(ReminderLog::query()->where('invoice_id', $invoice->id)->count())->toBe(1);

    Queue::assertPushed(SendReminderEmailJob::class, 1);
});

test('overdue reminders are queued when due date plus offset is today', function () {
    Carbon::setTestNow('2026-06-20 09:00:00');
    Queue::fake();

    [$workspace, , $invoice] = createReminderInvoiceFixture('2026-06-13', Invoice::STATUS_OVERDUE);
    createReminderScheduleFixture(
        workspace: $workspace,
        daysOffset: 7,
        direction: ReminderSchedule::DIRECTION_AFTER_DUE,
        name: '7 Days Overdue',
    );

    app(ReminderService::class)->process();

    expect(ReminderLog::query()->where('invoice_id', $invoice->id)->count())->toBe(1);
    expect($invoice->refresh()->reminder_status)->toBe(Invoice::REMINDER_STATUS_OVERDUE);

    Queue::assertPushed(SendReminderEmailJob::class, 1);
});

test('duplicate reminders are not created for the same invoice and schedule', function () {
    Carbon::setTestNow('2026-06-17 09:00:00');
    Queue::fake();

    [$workspace, $client, $invoice] = createReminderInvoiceFixture('2026-06-20');
    $schedule = createReminderScheduleFixture(
        workspace: $workspace,
        daysOffset: 3,
        direction: ReminderSchedule::DIRECTION_BEFORE_DUE,
    );

    ReminderLog::create([
        'invoice_id' => $invoice->id,
        'reminder_schedule_id' => $schedule->id,
        'recipient_email' => $client->email,
        'status' => ReminderLog::STATUS_PENDING,
    ]);

    $summary = app(ReminderService::class)->process();

    expect($summary['reminders_created'])->toBe(0);
    expect(ReminderLog::query()->where('invoice_id', $invoice->id)->where('reminder_schedule_id', $schedule->id)->count())->toBe(1);

    Queue::assertNothingPushed();
});

test('paid invoices are skipped', function () {
    Carbon::setTestNow('2026-06-17 09:00:00');
    Queue::fake();

    [$workspace] = createReminderInvoiceFixture('2026-06-20', Invoice::STATUS_PAID);
    createReminderScheduleFixture(
        workspace: $workspace,
        daysOffset: 3,
        direction: ReminderSchedule::DIRECTION_BEFORE_DUE,
    );

    $summary = app(ReminderService::class)->process();

    expect($summary['invoices_found'])->toBe(0);
    expect($summary['reminders_created'])->toBe(0);

    expect(ReminderLog::query()->count())->toBe(0);

    Queue::assertNothingPushed();
});

test('reminder email job sends mail and marks log as sent', function () {
    Carbon::setTestNow('2026-06-17 09:00:00');
    Mail::fake();

    [$workspace, $client, $invoice] = createReminderInvoiceFixture('2026-06-20');
    $schedule = createReminderScheduleFixture(
        workspace: $workspace,
        daysOffset: 3,
        direction: ReminderSchedule::DIRECTION_BEFORE_DUE,
    );

    $reminderLog = ReminderLog::create([
        'invoice_id' => $invoice->id,
        'reminder_schedule_id' => $schedule->id,
        'recipient_email' => $client->email,
        'status' => ReminderLog::STATUS_PENDING,
    ]);

    (new SendReminderEmailJob($reminderLog->id))->handle(app(ReminderService::class), app(TemplateRenderer::class));

    Mail::assertSent(ReminderMail::class, function (ReminderMail $mail) use ($invoice, $schedule): bool {
        return $mail->invoice->id === $invoice->id
            && $mail->reminderSchedule->id === $schedule->id;
    });

    expect($reminderLog->refresh())
        ->status->toBe(ReminderLog::STATUS_SENT)
        ->sent_at->not->toBeNull()
        ->error_message->toBeNull();

    expect($invoice->refresh()->reminder_status)->toBe(Invoice::REMINDER_STATUS_SENT);
});
