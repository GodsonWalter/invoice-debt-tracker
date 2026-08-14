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
use App\Services\InvoicePdfService;
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
    string $workspaceKey = 'reminder-workspace',
): array {
    $user = User::factory()->create();
    $invoicePrefix = $workspaceKey === 'reminder-workspace' ? 'REM' : strtoupper($workspaceKey);

    $workspace = Workspace::create([
        'owner_id' => $user->id,
        'name' => $workspaceKey === 'reminder-workspace' ? 'Reminder Workspace' : 'Reminder Workspace '.$workspaceKey,
        'slug' => $workspaceKey,
        'subdomain' => $workspaceKey,
        'invoice_prefix' => $invoicePrefix,
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
        'invoice_number' => $invoicePrefix.'-2026-0001',
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
    bool $includeInvoicePdf = false,
): ReminderSchedule {
    return ReminderSchedule::create([
        'workspace_id' => $workspace->id,
        'name' => $name,
        'days_offset' => $daysOffset,
        'direction' => $direction,
        'is_active' => true,
        'include_invoice_pdf' => $includeInvoicePdf,
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
        ->workspace_id->toBe($workspace->id)
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
        'workspace_id' => $workspace->id,
        'invoice_id' => $invoice->id,
        'reminder_schedule_id' => $schedule->id,
        'recipient_email' => $client->email,
        'status' => ReminderLog::STATUS_PENDING,
    ]);

    $summary = app(ReminderService::class)->process();

    expect($summary['reminders_created'])->toBe(0);
    expect(ReminderLog::query()->where('invoice_id', $invoice->id)->where('reminder_schedule_id', $schedule->id)->count())->toBe(1);
    expect(ReminderLog::query()->where('invoice_id', $invoice->id)->value('workspace_id'))->toBe($workspace->id);

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

test('inactive workspaces are skipped by reminder processing', function () {
    Carbon::setTestNow('2026-06-17 09:00:00');
    Queue::fake();

    [$workspace] = createReminderInvoiceFixture('2026-06-20');
    createReminderScheduleFixture(
        workspace: $workspace,
        daysOffset: 3,
        direction: ReminderSchedule::DIRECTION_BEFORE_DUE,
    );
    $workspace->update(['is_active' => false]);

    $summary = app(ReminderService::class)->process();

    expect($summary['schedules_processed'])->toBe(0)
        ->and($summary['reminders_created'])->toBe(0)
        ->and(ReminderLog::query()->count())->toBe(0);

    Queue::assertNothingPushed();
});

test('queued reminder jobs do not send after the workspace is deactivated', function () {
    Carbon::setTestNow('2026-06-17 09:00:00');
    Mail::fake();

    [$workspace, $client, $invoice] = createReminderInvoiceFixture('2026-06-20');
    $schedule = createReminderScheduleFixture(
        workspace: $workspace,
        daysOffset: 3,
        direction: ReminderSchedule::DIRECTION_BEFORE_DUE,
    );
    $reminderLog = ReminderLog::create([
        'workspace_id' => $workspace->id,
        'invoice_id' => $invoice->id,
        'reminder_schedule_id' => $schedule->id,
        'recipient_email' => $client->email,
        'status' => ReminderLog::STATUS_PENDING,
    ]);
    $workspace->update(['is_active' => false]);

    (new SendReminderEmailJob($reminderLog->id))->handle(
        app(ReminderService::class),
        app(TemplateRenderer::class),
        app(InvoicePdfService::class),
    );

    Mail::assertNothingSent();
    expect($reminderLog->refresh())
        ->status->toBe(ReminderLog::STATUS_FAILED)
        ->error_message->toBe('The workspace is no longer active.');
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
        'workspace_id' => $workspace->id,
        'invoice_id' => $invoice->id,
        'reminder_schedule_id' => $schedule->id,
        'recipient_email' => $client->email,
        'status' => ReminderLog::STATUS_PENDING,
    ]);

    (new SendReminderEmailJob($reminderLog->id))->handle(
        app(ReminderService::class),
        app(TemplateRenderer::class),
        app(InvoicePdfService::class),
    );

    Mail::assertSent(ReminderMail::class, function (ReminderMail $mail) use ($invoice, $schedule): bool {
        $content = $mail->content();

        return $mail->invoice->id === $invoice->id
            && $mail->reminderSchedule->id === $schedule->id
            && $mail->attachments() === []
            && array_key_exists('platformSettings', $content->with);
    });

    expect($reminderLog->refresh())
        ->workspace_id->toBe($workspace->id)
        ->status->toBe(ReminderLog::STATUS_SENT)
        ->sent_at->not->toBeNull()
        ->error_message->toBeNull();

    expect($invoice->refresh()->reminder_status)->toBe(Invoice::REMINDER_STATUS_SENT);
});

test('reminder email can include the latest invoice pdf when enabled', function (): void {
    Carbon::setTestNow('2026-06-17 09:00:00');
    Mail::fake();

    app()->bind(InvoicePdfService::class, fn (): InvoicePdfService => new class extends InvoicePdfService
    {
        public function content(Invoice $invoice): string
        {
            return '%PDF-1.4 reminder invoice';
        }
    });

    [$workspace, $client, $invoice] = createReminderInvoiceFixture('2026-06-20');
    $schedule = createReminderScheduleFixture(
        workspace: $workspace,
        daysOffset: 0,
        direction: ReminderSchedule::DIRECTION_BEFORE_DUE,
        name: 'Due Today',
        includeInvoicePdf: true,
    );
    $reminderLog = ReminderLog::create([
        'workspace_id' => $workspace->id,
        'invoice_id' => $invoice->id,
        'reminder_schedule_id' => $schedule->id,
        'recipient_email' => $client->email,
        'status' => ReminderLog::STATUS_PENDING,
    ]);

    (new SendReminderEmailJob($reminderLog->id))->handle(
        app(ReminderService::class),
        app(TemplateRenderer::class),
        app(InvoicePdfService::class),
    );

    Mail::assertSent(ReminderMail::class, function (ReminderMail $mail) use ($invoice, $schedule): bool {
        $content = $mail->content();

        return $mail->invoice->id === $invoice->id
            && $mail->reminderSchedule->id === $schedule->id
            && count($mail->attachments()) === 1
            && array_key_exists('platformSettings', $content->with);
    });
});

test('queued reminder is not sent when the invoice is paid before delivery', function (): void {
    Carbon::setTestNow('2026-06-17 09:00:00');
    Mail::fake();

    [$workspace, $client, $invoice] = createReminderInvoiceFixture('2026-06-20');
    $schedule = createReminderScheduleFixture(
        workspace: $workspace,
        daysOffset: 3,
        direction: ReminderSchedule::DIRECTION_BEFORE_DUE,
    );
    $reminderLog = ReminderLog::create([
        'workspace_id' => $workspace->id,
        'invoice_id' => $invoice->id,
        'reminder_schedule_id' => $schedule->id,
        'recipient_email' => $client->email,
        'status' => ReminderLog::STATUS_PENDING,
    ]);
    $invoice->update(['status' => Invoice::STATUS_PAID]);

    (new SendReminderEmailJob($reminderLog->id))->handle(
        app(ReminderService::class),
        app(TemplateRenderer::class),
        app(InvoicePdfService::class),
    );

    Mail::assertNothingSent();
    expect($reminderLog->refresh())
        ->status->toBe(ReminderLog::STATUS_FAILED)
        ->error_message->toBe('The invoice has no outstanding balance.');
});

test('reminder logs remain isolated between workspaces', function () {
    Carbon::setTestNow('2026-06-17 09:00:00');
    Queue::fake();

    [$firstWorkspace, , $firstInvoice] = createReminderInvoiceFixture(
        dueDate: '2026-06-20',
        workspaceKey: 'first-workspace',
    );
    [$secondWorkspace, , $secondInvoice] = createReminderInvoiceFixture(
        dueDate: '2026-06-20',
        workspaceKey: 'second-workspace',
    );

    createReminderScheduleFixture(
        workspace: $firstWorkspace,
        daysOffset: 3,
        direction: ReminderSchedule::DIRECTION_BEFORE_DUE,
    );
    createReminderScheduleFixture(
        workspace: $secondWorkspace,
        daysOffset: 3,
        direction: ReminderSchedule::DIRECTION_BEFORE_DUE,
    );

    app(ReminderService::class)->process();

    expect(ReminderLog::query()->count())->toBe(2);
    expect(ReminderLog::query()
        ->where('workspace_id', $firstWorkspace->id)
        ->where('invoice_id', $firstInvoice->id)
        ->exists())->toBeTrue();
    expect(ReminderLog::query()
        ->where('workspace_id', $secondWorkspace->id)
        ->where('invoice_id', $secondInvoice->id)
        ->exists())->toBeTrue();
    expect(ReminderLog::query()
        ->where('workspace_id', $firstWorkspace->id)
        ->where('invoice_id', $secondInvoice->id)
        ->exists())->toBeFalse();
    expect(ReminderLog::query()
        ->where('workspace_id', $secondWorkspace->id)
        ->where('invoice_id', $firstInvoice->id)
        ->exists())->toBeFalse();

    Queue::assertPushed(SendReminderEmailJob::class, 2);
});

test('failed reminder email attempts retain the correct workspace', function () {
    Carbon::setTestNow('2026-06-17 09:00:00');
    Queue::fake();

    [$workspace, $client, $invoice] = createReminderInvoiceFixture('2026-06-20');
    $schedule = createReminderScheduleFixture(
        workspace: $workspace,
        daysOffset: 3,
        direction: ReminderSchedule::DIRECTION_BEFORE_DUE,
    );

    app(ReminderService::class)->process();

    $reminderLog = ReminderLog::query()->firstOrFail();

    expect($reminderLog)
        ->workspace_id->toBe($workspace->id)
        ->invoice_id->toBe($invoice->id)
        ->reminder_schedule_id->toBe($schedule->id)
        ->recipient_email->toBe($client->email);

    Mail::shouldReceive('to')
        ->once()
        ->andThrow(new RuntimeException('SMTP connection failed.'));

    expect(fn () => (new SendReminderEmailJob($reminderLog->id))->handle(
        app(ReminderService::class),
        app(TemplateRenderer::class),
        app(InvoicePdfService::class),
    ))->toThrow(RuntimeException::class, 'SMTP connection failed.');

    expect($reminderLog->refresh())
        ->workspace_id->toBe($workspace->id)
        ->status->toBe(ReminderLog::STATUS_FAILED)
        ->error_message->toBe('SMTP connection failed.');
});

test('mismatched existing reminder logs are rejected instead of reassigned', function () {
    Carbon::setTestNow('2026-06-17 09:00:00');
    Queue::fake();

    [$workspace, $client, $invoice] = createReminderInvoiceFixture('2026-06-20');
    [$otherWorkspace] = createReminderInvoiceFixture(
        dueDate: '2026-06-20',
        workspaceKey: 'other-workspace',
    );
    $schedule = createReminderScheduleFixture(
        workspace: $workspace,
        daysOffset: 3,
        direction: ReminderSchedule::DIRECTION_BEFORE_DUE,
    );

    ReminderLog::create([
        'workspace_id' => $otherWorkspace->id,
        'invoice_id' => $invoice->id,
        'reminder_schedule_id' => $schedule->id,
        'recipient_email' => $client->email,
        'status' => ReminderLog::STATUS_PENDING,
    ]);

    expect(fn () => app(ReminderService::class)->process())
        ->toThrow(LogicException::class, 'The existing reminder log belongs to another workspace.');

    expect(ReminderLog::query()->first()->workspace_id)->toBe($otherWorkspace->id);
});
