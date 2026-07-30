<?php

use App\Http\Middleware\EnsureWorkspaceIsActive;
use App\Http\Middleware\ResolveWorkspace;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ReminderLog;
use App\Models\ReminderSchedule;
use App\Models\User;
use App\Models\Workspace;
use App\Services\WorkspaceDashboardService;
use App\WorkspaceDashboardPeriod;
use Carbon\Carbon;

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::create(2026, 7, 29, 15, 30, 0, 'UTC'));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

/**
 * @return array{0: User, 1: Workspace, 2: Client, 3: Currency}
 */
function createDashboardFixture(string $key = 'dashboard'): array
{
    $user = User::factory()->create();
    $currency = Currency::create([
        'code' => strtoupper(substr($key, 0, 3)),
        'symbol' => '$',
        'name' => ucfirst($key).' currency',
        'is_active' => true,
    ]);
    $workspace = Workspace::create([
        'owner_id' => $user->id,
        'name' => ucfirst($key).' workspace',
        'slug' => $key.'-'.fake()->unique()->numerify('####'),
        'subdomain' => $key.'-'.fake()->unique()->numerify('####'),
        'invoice_prefix' => strtoupper(substr($key, 0, 3)),
        'currency_id' => $currency->id,
        'is_active' => true,
    ]);
    $workspace->users()->attach($user->id, ['role' => 'owner', 'is_active' => true]);
    $client = Client::create([
        'workspace_id' => $workspace->id,
        'name' => ucfirst($key).' customer',
        'email' => $key.'@example.test',
    ]);

    return [$user, $workspace, $client, $currency];
}

function dashboardTestUrl(string $routeName, Workspace $workspace, array $query = []): string
{
    $url = 'http://'.$workspace->subdomain.'.'.config('app.base_domain').route($routeName, $workspace, false);

    return $query ? $url.'?'.http_build_query($query) : $url;
}

function createDashboardInvoice(Workspace $workspace, Client $client, Currency $currency, array $overrides = []): Invoice
{
    return Invoice::create(array_merge([
        'workspace_id' => $workspace->id,
        'client_id' => $client->id,
        'currency_id' => $currency->id,
        'invoice_number' => 'INV-'.fake()->unique()->numerify('#####'),
        'issue_date' => now()->subDays(20)->toDateString(),
        'due_date' => now()->subDays(10)->toDateString(),
        'status' => Invoice::STATUS_OVERDUE,
        'subtotal' => 1000,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 1000,
    ], $overrides));
}

test('workspace dashboard calculates payments and balances only for the active workspace', function () {
    [$user, $workspace, $client, $currency] = createDashboardFixture('primary');
    [, $otherWorkspace, $otherClient, $otherCurrency] = createDashboardFixture('other');

    $invoice = createDashboardInvoice($workspace, $client, $currency);
    Payment::create([
        'workspace_id' => $workspace->id,
        'invoice_id' => $invoice->id,
        'amount' => 250,
        'payment_date' => now()->toDateString(),
        'payment_method' => 'Bank transfer',
    ]);

    $otherInvoice = createDashboardInvoice($otherWorkspace, $otherClient, $otherCurrency, [
        'total_amount' => 900,
        'subtotal' => 900,
    ]);
    Payment::create([
        'workspace_id' => $otherWorkspace->id,
        'invoice_id' => $otherInvoice->id,
        'amount' => 900,
        'payment_date' => now()->toDateString(),
    ]);

    $period = WorkspaceDashboardPeriod::fromInput(WorkspaceDashboardPeriod::LAST_6_MONTHS);
    $dashboard = app(WorkspaceDashboardService::class)->dashboard($workspace, $user, $period);

    expect($dashboard['kpis'])
        ->revenue_all_time->toBe(250.0)
        ->and($dashboard['kpis']['outstanding_debt'])->toBe(750.0)
        ->and($dashboard['kpis']['unpaid_count'])->toBe(1)
        ->and($dashboard['kpis']['customers_owing'])->toBe(1)
        ->and($dashboard['kpis']['overdue_amount'])->toBe(750.0)
        ->and(max($dashboard['chart']['outstanding']))->toBe(750.0)
        ->and($dashboard['topDebtors'])->toHaveCount(1)
        ->and((float) $dashboard['topDebtors']->first()->outstanding_amount)->toBe(750.0);
});

test('workspace dashboard renders operational sections and empty states safely', function () {
    [$user, $workspace, $client, $currency] = createDashboardFixture('render');
    $invoice = createDashboardInvoice($workspace, $client, $currency, [
        'status' => Invoice::STATUS_SENT,
        'issue_date' => now()->toDateString(),
        'due_date' => now()->addDays(5)->toDateString(),
    ]);
    Payment::create([
        'workspace_id' => $workspace->id,
        'invoice_id' => $invoice->id,
        'amount' => 100,
        'payment_date' => now()->toDateString(),
    ]);

    $this->actingAs($user)
        ->get(dashboardTestUrl('workspace.dashboard', $workspace))
        ->assertOk()
        ->assertSee('Revenue and collections')
        ->assertSee('Invoice status')
        ->assertSee($invoice->invoice_number)
        ->assertSee($client->name)
        ->assertSee('Recent payments')
        ->assertSee('Quick actions');

    [$emptyUser, $emptyWorkspace] = array_values(array_slice(createDashboardFixture('empty'), 0, 2));

    $this->actingAs($emptyUser)
        ->get(dashboardTestUrl('workspace.dashboard', $emptyWorkspace))
        ->assertOk()
        ->assertSee('Create your first invoice')
        ->assertSee('No customers currently owe money.');
});

test('workspace dashboard date filters are validated and affect the selected period', function () {
    [$user, $workspace, $client, $currency] = createDashboardFixture('filters');
    $invoice = createDashboardInvoice($workspace, $client, $currency, [
        'issue_date' => now()->subMonths(2)->startOfMonth()->toDateString(),
        'due_date' => now()->subMonths(2)->endOfMonth()->toDateString(),
        'status' => Invoice::STATUS_SENT,
    ]);
    Payment::create([
        'workspace_id' => $workspace->id,
        'invoice_id' => $invoice->id,
        'amount' => 100,
        'payment_date' => now()->subMonths(2)->startOfMonth()->toDateString(),
    ]);

    $this->actingAs($user)
        ->get(dashboardTestUrl('workspace.dashboard', $workspace, ['period' => 'this_month']))
        ->assertOk()
        ->assertSee('This month');

    $this->actingAs($user)
        ->get(dashboardTestUrl('workspace.dashboard', $workspace, [
            'period' => 'custom',
            'start_date' => now()->subMonths(2)->startOfMonth()->toDateString(),
            'end_date' => now()->subMonths(2)->endOfMonth()->toDateString(),
        ]))
        ->assertOk()
        ->assertSee('Custom range')
        ->assertSee('value="2026-05-01"', false)
        ->assertSee('value="2026-05-31"', false);

    $this->actingAs($user)
        ->get(dashboardTestUrl('workspace.dashboard', $workspace, [
            'period' => 'custom',
            'start_date' => now()->subYears(2)->toDateString(),
            'end_date' => now()->toDateString(),
        ]))
        ->assertSessionHasErrors('end_date');
});

test('workspace dashboard uses business dates for period analytics', function (): void {
    [$user, $workspace, $client, $currency] = createDashboardFixture('business-dates');
    $periodInvoice = createDashboardInvoice($workspace, $client, $currency, [
        'issue_date' => '2026-07-10',
        'due_date' => '2026-08-10',
        'status' => Invoice::STATUS_SENT,
        'total_amount' => 300,
        'subtotal' => 300,
    ]);
    $outsideInvoice = createDashboardInvoice($workspace, $client, $currency, [
        'issue_date' => '2026-06-30',
        'due_date' => '2026-07-15',
        'status' => Invoice::STATUS_SENT,
        'total_amount' => 500,
        'subtotal' => 500,
    ]);
    Payment::create([
        'workspace_id' => $workspace->id,
        'invoice_id' => $periodInvoice->id,
        'amount' => 100,
        'payment_date' => '2026-07-11',
    ]);
    Payment::create([
        'workspace_id' => $workspace->id,
        'invoice_id' => $outsideInvoice->id,
        'amount' => 200,
        'payment_date' => '2026-06-30',
    ]);

    $schedule = ReminderSchedule::create([
        'workspace_id' => $workspace->id,
        'name' => 'Dashboard reminders',
        'days_offset' => 1,
        'direction' => ReminderSchedule::DIRECTION_AFTER_DUE,
        'is_active' => true,
    ]);
    $failedSchedule = ReminderSchedule::create([
        'workspace_id' => $workspace->id,
        'name' => 'Dashboard failed reminders',
        'days_offset' => 2,
        'direction' => ReminderSchedule::DIRECTION_AFTER_DUE,
        'is_active' => true,
    ]);
    $outsideSchedule = ReminderSchedule::create([
        'workspace_id' => $workspace->id,
        'name' => 'Dashboard outside reminders',
        'days_offset' => 3,
        'direction' => ReminderSchedule::DIRECTION_AFTER_DUE,
        'is_active' => true,
    ]);
    $sentInsidePeriod = ReminderLog::create([
        'workspace_id' => $workspace->id,
        'invoice_id' => $periodInvoice->id,
        'reminder_schedule_id' => $failedSchedule->id,
        'recipient_email' => 'inside@example.test',
        'status' => ReminderLog::STATUS_SENT,
        'sent_at' => '2026-07-12 10:00:00',
    ]);
    $sentInsidePeriod->forceFill(['created_at' => '2026-06-30 10:00:00'])->saveQuietly();
    $failedInsidePeriod = ReminderLog::create([
        'workspace_id' => $workspace->id,
        'invoice_id' => $periodInvoice->id,
        'reminder_schedule_id' => $outsideSchedule->id,
        'recipient_email' => 'failed@example.test',
        'status' => ReminderLog::STATUS_FAILED,
        'error_message' => 'SMTP unavailable',
    ]);
    $failedInsidePeriod->forceFill(['created_at' => '2026-07-13 10:00:00'])->saveQuietly();
    $sentOutsidePeriod = ReminderLog::create([
        'workspace_id' => $workspace->id,
        'invoice_id' => $outsideInvoice->id,
        'reminder_schedule_id' => $schedule->id,
        'recipient_email' => 'outside@example.test',
        'status' => ReminderLog::STATUS_SENT,
        'sent_at' => '2026-06-30 10:00:00',
    ]);
    $sentOutsidePeriod->forceFill(['created_at' => '2026-07-14 10:00:00'])->saveQuietly();

    $period = WorkspaceDashboardPeriod::fromInput(WorkspaceDashboardPeriod::THIS_MONTH);
    $dashboard = app(WorkspaceDashboardService::class)->dashboard($workspace, $user, $period);

    expect($dashboard['kpis']['payments_period'])->toBe(100.0)
        ->and($dashboard['kpis']['payment_transactions_period'])->toBe(1)
        ->and($dashboard['kpis']['revenue_all_time'])->toBe(300.0)
        ->and($dashboard['reminders']['sent'])->toBe(1)
        ->and($dashboard['reminders']['failed'])->toBe(1)
        ->and($dashboard['invoiceStatuses'])->toHaveCount(1)
        ->and((float) $dashboard['invoiceStatuses']->first()->value)->toBe(300.0);
});

test('workspace dashboard validates unsupported, incomplete, reversed, future, and excessive ranges', function (): void {
    [$owner, $workspace] = array_values(array_slice(createDashboardFixture('validation'), 0, 2));

    $this->actingAs($owner)
        ->get(dashboardTestUrl('workspace.dashboard', $workspace, ['period' => 'invalid']))
        ->assertSessionHasErrors('period');

    $this->actingAs($owner)
        ->get(dashboardTestUrl('workspace.dashboard', $workspace, ['period' => 'custom']))
        ->assertSessionHasErrors('start_date');

    $this->actingAs($owner)
        ->get(dashboardTestUrl('workspace.dashboard', $workspace, [
            'period' => 'custom',
            'start_date' => '2026-07-20',
            'end_date' => '2026-07-10',
        ]))
        ->assertSessionHasErrors('end_date');

    $this->actingAs($owner)
        ->get(dashboardTestUrl('workspace.dashboard', $workspace, [
            'period' => 'custom',
            'start_date' => '2026-07-30',
            'end_date' => '2026-07-30',
        ]))
        ->assertSessionHasErrors(['start_date', 'end_date']);

    $this->actingAs($owner)
        ->get(dashboardTestUrl('workspace.dashboard', $workspace, [
            'period' => 'custom',
            'start_date' => '2025-01-01',
            'end_date' => '2026-01-02',
        ]))
        ->assertSessionHasErrors('end_date');

    $this->actingAs($owner)
        ->get(dashboardTestUrl('workspace.dashboard', $workspace, [
            'period' => 'custom',
            'start_date' => '2025-07-29',
            'end_date' => '2026-07-29',
        ]))
        ->assertOk();
});

test('workspace dashboard fills zero-value daily chart buckets for short custom ranges', function (): void {
    [$user, $workspace, $client, $currency] = createDashboardFixture('daily-chart');
    createDashboardInvoice($workspace, $client, $currency, [
        'issue_date' => '2026-07-02',
        'due_date' => '2026-07-10',
        'total_amount' => 120,
        'subtotal' => 120,
    ]);

    $period = WorkspaceDashboardPeriod::fromInput(
        WorkspaceDashboardPeriod::CUSTOM,
        '2026-07-01',
        '2026-07-03',
    );
    $dashboard = app(WorkspaceDashboardService::class)->dashboard($workspace, $user, $period);

    expect($dashboard['chart']['labels'])->toBe(['01 Jul', '02 Jul', '03 Jul'])
        ->and($dashboard['chart']['invoiced'])->toBe([0.0, 120.0, 0.0]);
});

test('workspace dashboard rejects a mismatched or unauthorized workspace', function () {
    [$user, $workspace] = array_values(array_slice(createDashboardFixture('active'), 0, 2));
    [$otherUser, $otherWorkspace] = array_values(array_slice(createDashboardFixture('other-active'), 0, 2));

    $this->actingAs($user)
        ->get(dashboardTestUrl('workspace.dashboard', $workspace))
        ->assertOk();

    $this->actingAs($user)
        ->get('http://'.$workspace->subdomain.'.'.config('app.base_domain').route('workspace.dashboard', $otherWorkspace, false))
        ->assertRedirect(route('workspace.index'));

    $this->actingAs($otherUser)
        ->get(dashboardTestUrl('workspace.dashboard', $workspace))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('error', 'You are not authorized to access this workspace.');

    $workspace->users()->updateExistingPivot($user->id, ['is_active' => false]);
    $this->actingAs($user)
        ->get(dashboardTestUrl('workspace.dashboard', $workspace))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('error', 'You are not authorized to access this workspace.');
});

test('base dashboard remains safe when no active workspace is available', function () {
    $this->withoutMiddleware([
        EnsureWorkspaceIsActive::class,
        ResolveWorkspace::class,
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Select a workspace')
        ->assertDontSee('Total revenue');
});
