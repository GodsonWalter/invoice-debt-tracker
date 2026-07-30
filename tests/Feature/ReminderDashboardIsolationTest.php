<?php

use App\Models\Client;
use App\Models\Invoice;
use App\Models\ReminderLog;
use App\Models\ReminderSchedule;
use App\Models\User;
use App\Models\Workspace;

/**
 * @return array{workspace: Workspace, invoice: Invoice, client: Client, sent: ReminderLog, failed: ReminderLog}
 */
function createReminderDashboardIsolationFixture(string $key, string $clientName): array
{
    $user = User::factory()->create();
    $workspace = Workspace::create([
        'owner_id' => $user->id,
        'name' => ucfirst($key).' workspace',
        'slug' => $key.'-'.fake()->unique()->numerify('####'),
        'subdomain' => $key.'-'.fake()->unique()->numerify('####'),
        'invoice_prefix' => strtoupper(substr($key, 0, 3)),
        'is_active' => true,
    ]);
    $workspace->users()->attach($user->id, ['role' => 'owner', 'is_active' => true]);

    $client = Client::create([
        'workspace_id' => $workspace->id,
        'name' => $clientName,
        'email' => $key.'@example.test',
    ]);
    $invoice = Invoice::create([
        'workspace_id' => $workspace->id,
        'client_id' => $client->id,
        'invoice_number' => strtoupper(substr($key, 0, 3)).'-'.fake()->unique()->numerify('#####'),
        'issue_date' => now()->toDateString(),
        'due_date' => now()->addDay()->toDateString(),
        'status' => Invoice::STATUS_SENT,
        'subtotal' => 100,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 100,
    ]);
    $schedule = ReminderSchedule::create([
        'workspace_id' => $workspace->id,
        'name' => 'Isolation schedule',
        'days_offset' => 0,
        'direction' => ReminderSchedule::DIRECTION_BEFORE_DUE,
        'is_active' => true,
    ]);
    $sent = ReminderLog::create([
        'workspace_id' => $workspace->id,
        'invoice_id' => $invoice->id,
        'reminder_schedule_id' => $schedule->id,
        'recipient_email' => $client->email,
        'status' => ReminderLog::STATUS_SENT,
        'sent_at' => now(),
    ]);
    $failedSchedule = ReminderSchedule::create([
        'workspace_id' => $workspace->id,
        'name' => 'Isolation failure schedule',
        'days_offset' => 1,
        'direction' => ReminderSchedule::DIRECTION_BEFORE_DUE,
        'is_active' => true,
    ]);
    $failed = ReminderLog::create([
        'workspace_id' => $workspace->id,
        'invoice_id' => $invoice->id,
        'reminder_schedule_id' => $failedSchedule->id,
        'recipient_email' => $client->email,
        'status' => ReminderLog::STATUS_FAILED,
        'error_message' => 'Test failure',
    ]);

    return compact('workspace', 'invoice', 'client', 'sent', 'failed');
}

function reminderDashboardIsolationUrl(Workspace $workspace, string $routeName, string $search): string
{
    return 'http://'.$workspace->subdomain.'.'.config('app.base_domain').route($routeName, [], false).'?search='.urlencode($search);
}

test('reminder dashboard searches remain scoped to the active workspace', function (string $routeName): void {
    $primary = createReminderDashboardIsolationFixture('primary-reminder', 'Primary Search Client');
    $foreign = createReminderDashboardIsolationFixture('foreign-reminder', 'Foreign Search Client');

    $this->actingAs(User::findOrFail($primary['workspace']->owner_id))
        ->get(reminderDashboardIsolationUrl($primary['workspace'], $routeName, $foreign['client']->name))
        ->assertOk()
        ->assertDontSee($foreign['invoice']->invoice_number);
})->with([
    'activity' => 'reminders.activity',
    'sent' => 'reminders.sent',
    'failed' => 'reminders.failed',
    'upcoming' => 'reminders.upcoming',
]);
test('example', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});
