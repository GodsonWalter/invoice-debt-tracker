<?php

use App\Http\Middleware\EnsureWorkspaceIsActive;
use App\Http\Middleware\ResolveWorkspace;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Models\Workspace;
use App\Services\WorkspaceDashboardService;
use Carbon\CarbonImmutable;

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

    $filters = [
        'range' => 'last_6_months',
        'start' => CarbonImmutable::now()->subMonths(5)->startOfMonth(),
        'end' => CarbonImmutable::now()->endOfMonth(),
        'label' => 'Last 6 months',
    ];
    $dashboard = app(WorkspaceDashboardService::class)->dashboard($workspace, $user, $filters);

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
        ->get(dashboardTestUrl('workspace.dashboard', $workspace, ['range' => 'this_month']))
        ->assertOk()
        ->assertSee('This month');

    $this->actingAs($user)
        ->get(dashboardTestUrl('workspace.dashboard', $workspace, [
            'range' => 'custom',
            'start_date' => now()->subMonths(2)->startOfMonth()->toDateString(),
            'end_date' => now()->subMonths(2)->endOfMonth()->toDateString(),
        ]))
        ->assertOk()
        ->assertSee('Custom range');

    $this->actingAs($user)
        ->get(dashboardTestUrl('workspace.dashboard', $workspace, [
            'range' => 'custom',
            'start_date' => now()->subYears(2)->toDateString(),
            'end_date' => now()->toDateString(),
        ]))
        ->assertSessionHasErrors('end_date');
});

test('workspace dashboard rejects a mismatched or unauthorized workspace', function () {
    [$user, $workspace] = array_values(array_slice(createDashboardFixture('active'), 0, 2));
    [$otherUser, $otherWorkspace] = array_values(array_slice(createDashboardFixture('other-active'), 0, 2));

    $this->actingAs($user)
        ->get(dashboardTestUrl('workspace.dashboard', $workspace))
        ->assertOk();

    $this->actingAs($user)
        ->get('http://'.$workspace->subdomain.'.'.config('app.base_domain').route('workspace.dashboard', $otherWorkspace, false))
        ->assertRedirect('http://'.$workspace->subdomain.'.'.config('app.base_domain').route('workspace.dashboard', $workspace, false));

    $this->actingAs($otherUser)
        ->get(dashboardTestUrl('workspace.dashboard', $workspace))
        ->assertForbidden();

    $workspace->users()->updateExistingPivot($user->id, ['is_active' => false]);
    $this->actingAs($user)
        ->get(dashboardTestUrl('workspace.dashboard', $workspace))
        ->assertForbidden();
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
