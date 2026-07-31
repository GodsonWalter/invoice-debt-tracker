<?php

use App\Models\BusinessProfile;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Models\Workspace;
use App\Services\PlatformDashboardService;
use App\WorkspaceDashboardPeriod;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::create(2026, 7, 31, 15, 30, 0, 'UTC'));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function platformDashboardUrl(array $query = []): string
{
    $url = 'http://'.trim((string) config('app.base_domain'), '"').route('platform.dashboard', [], false);

    return $query ? $url.'?'.http_build_query($query) : $url;
}

/**
 * @return array{0: User, 1: Workspace, 2: Client, 3: Currency}
 */
function platformDashboardFixture(string $key = 'platform'): array
{
    $user = User::factory()->create(['role' => 'owner']);
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

function platformDashboardInvoice(Workspace $workspace, Client $client, Currency $currency, array $overrides = []): Invoice
{
    return Invoice::create(array_merge([
        'workspace_id' => $workspace->id,
        'client_id' => $client->id,
        'currency_id' => $currency->id,
        'invoice_number' => 'INV-'.fake()->unique()->numerify('#####'),
        'issue_date' => '2026-07-10',
        'due_date' => '2026-07-20',
        'status' => Invoice::STATUS_OVERDUE,
        'subtotal' => 1000,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 1000,
    ], $overrides));
}

test('platform management roles can view the base-domain dashboard', function (string $role): void {
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)
        ->get(platformDashboardUrl())
        ->assertOk()
        ->assertSee('Platform Dashboard')
        ->assertSee('Financial activity by currency')
        ->assertSee('No operational issues detected.')
        ->assertSee('value="today" selected', false)
        ->assertDontSee('value="last_7_days" selected', false)
        ->assertSeeInOrder(['<option value="today"', '<option value="last_7_days"'], false);
})->with(['owner', 'admin', 'manager', 'staff']);

test('the default base dashboard exposes the platform dashboard to platform staff', function (): void {
    $user = User::factory()->create(['role' => 'manager']);

    $this->actingAs($user)
        ->get('http://'.trim((string) config('app.base_domain'), '"').route('dashboard', [], false))
        ->assertOk()
        ->assertSee('Platform Dashboard');
});

test('non-platform users cannot view the platform dashboard', function (): void {
    $user = User::factory()->create(['role' => 'user']);

    $this->actingAs($user)
        ->get(platformDashboardUrl())
        ->assertForbidden();
});

test('the platform dashboard is not available on a workspace host', function (): void {
    $user = User::factory()->create(['role' => 'owner']);

    $this->actingAs($user)
        ->get('http://workspace.'.trim((string) config('app.base_domain'), '"').route('platform.dashboard', [], false))
        ->assertNotFound();
});

test('platform financial activity remains separated by currency and active workspace', function (): void {
    [$owner, $workspace, $client, $usd] = platformDashboardFixture('usd');
    $eur = Currency::create([
        'code' => 'EUR',
        'symbol' => '€',
        'name' => 'Euro',
        'is_active' => true,
    ]);
    $otherWorkspace = Workspace::create([
        'owner_id' => $owner->id,
        'name' => 'Euro workspace',
        'slug' => 'eur-'.fake()->unique()->numerify('####'),
        'subdomain' => 'eur-'.fake()->unique()->numerify('####'),
        'currency_id' => $eur->id,
        'is_active' => true,
    ]);
    $otherWorkspace->users()->attach($owner->id, ['role' => 'owner', 'is_active' => true]);
    $otherClient = Client::create([
        'workspace_id' => $otherWorkspace->id,
        'name' => 'Euro customer',
        'email' => 'euro@example.test',
    ]);

    $usdInvoice = platformDashboardInvoice($workspace, $client, $usd, ['total_amount' => 100, 'subtotal' => 100]);
    $eurInvoice = platformDashboardInvoice($otherWorkspace, $otherClient, $eur, ['total_amount' => 300, 'subtotal' => 300]);
    Payment::create(['workspace_id' => $workspace->id, 'invoice_id' => $usdInvoice->id, 'amount' => 25, 'payment_date' => '2026-07-11']);
    Payment::create(['workspace_id' => $otherWorkspace->id, 'invoice_id' => $eurInvoice->id, 'amount' => 100, 'payment_date' => '2026-07-12']);
    Payment::create(['workspace_id' => $workspace->id, 'invoice_id' => $eurInvoice->id, 'amount' => 50, 'payment_date' => '2026-07-13']);

    $inactiveWorkspace = Workspace::create([
        'owner_id' => $owner->id,
        'name' => 'Inactive workspace',
        'slug' => 'inactive-'.fake()->unique()->numerify('####'),
        'subdomain' => 'inactive-'.fake()->unique()->numerify('####'),
        'currency_id' => $usd->id,
        'is_active' => false,
    ]);
    $inactiveWorkspace->users()->attach($owner->id, ['role' => 'owner', 'is_active' => true]);
    $inactiveClient = Client::create(['workspace_id' => $inactiveWorkspace->id, 'name' => 'Inactive customer']);
    platformDashboardInvoice($inactiveWorkspace, $inactiveClient, $usd, ['total_amount' => 900, 'subtotal' => 900]);

    $dashboard = app(PlatformDashboardService::class)->dashboard(
        $owner,
        WorkspaceDashboardPeriod::fromInput(WorkspaceDashboardPeriod::CUSTOM, '2026-07-01', '2026-07-31'),
    );
    $currencyRows = $dashboard['currencies']->keyBy('code');

    expect($currencyRows)->toHaveKeys(['EUR', 'USD'])
        ->and($currencyRows['USD']['invoiced_amount'])->toBe(100.0)
        ->and($currencyRows['USD']['collected_amount'])->toBe(25.0)
        ->and($currencyRows['USD']['outstanding_amount'])->toBe(75.0)
        ->and($currencyRows['EUR']['invoiced_amount'])->toBe(300.0)
        ->and($currencyRows['EUR']['collected_amount'])->toBe(100.0)
        ->and($currencyRows['EUR']['outstanding_amount'])->toBe(200.0)
        ->and($dashboard['overview']['active_workspaces'])->toBe(2)
        ->and($dashboard['overview']['invoices_period'])->toBe(2)
        ->and($dashboard['overview']['payments_period'])->toBe(2);
});

test('the platform dashboard reports operational alerts', function (): void {
    [$owner, $workspace] = array_values(array_slice(platformDashboardFixture('alerts'), 0, 2));
    $workspace->delete();

    User::factory()->create(['role' => 'user'])->delete();
    DB::table('failed_jobs')->insert([
        'uuid' => (string) Str::uuid(),
        'connection' => 'database',
        'queue' => 'default',
        'payload' => '{}',
        'exception' => 'Test failure',
        'failed_at' => now(),
    ]);

    $this->actingAs($owner)
        ->get(platformDashboardUrl())
        ->assertOk()
        ->assertSee('Failed queue jobs')
        ->assertSee('Deleted user accounts')
        ->assertSee('Deleted workspaces');
});

test('platform dashboard lists active workspaces without business profiles', function (): void {
    [$owner, $missingProfileWorkspace] = array_values(array_slice(platformDashboardFixture('missing-profile'), 0, 2));
    $missingProfileWorkspace->update(['name' => 'Workspace Without Profile']);

    [$profileOwner, $configuredWorkspace] = array_values(array_slice(platformDashboardFixture('configured'), 0, 2));
    BusinessProfile::create([
        'workspace_id' => $configuredWorkspace->id,
        'business_name' => 'Configured Business',
    ]);

    $inactiveWorkspace = Workspace::create([
        'owner_id' => $profileOwner->id,
        'name' => 'Inactive Without Profile',
        'slug' => 'inactive-without-profile-'.fake()->unique()->numerify('####'),
        'subdomain' => 'inactive-without-profile-'.fake()->unique()->numerify('####'),
        'is_active' => false,
    ]);

    $dashboard = app(PlatformDashboardService::class)->dashboard(
        $owner,
        WorkspaceDashboardPeriod::fromInput(WorkspaceDashboardPeriod::TODAY),
    );
    $attention = collect($dashboard['attention'])->firstWhere('label', 'Active workspaces without a business profile');

    expect($attention)->not->toBeNull()
        ->and($attention['count'])->toBe(1)
        ->and($attention['workspaces'])->toHaveCount(1)
        ->and($attention['workspaces'][0])->toMatchArray([
            'id' => $missingProfileWorkspace->id,
            'name' => 'Workspace Without Profile',
            'identifier' => $missingProfileWorkspace->subdomain,
        ])
        ->and(collect($attention['workspaces'])->pluck('id'))->not->toContain($configuredWorkspace->id)
        ->and(collect($attention['workspaces'])->pluck('id'))->not->toContain($inactiveWorkspace->id);

    $this->actingAs($owner)
        ->get(platformDashboardUrl())
        ->assertOk()
        ->assertSee('Active workspaces without a business profile')
        ->assertSee('Affected workspaces')
        ->assertSee('Workspace Without Profile');
});

test('platform dashboard custom periods are validated', function (): void {
    $user = User::factory()->create(['role' => 'owner']);

    $this->actingAs($user)
        ->get(platformDashboardUrl(['period' => 'custom']))
        ->assertSessionHasErrors('start_date');

    $this->actingAs($user)
        ->get(platformDashboardUrl([
            'period' => 'custom',
            'start_date' => '2025-01-01',
            'end_date' => '2026-01-02',
        ]))
        ->assertSessionHasErrors('end_date');
});

test('platform dashboard supports today and inclusive last seven days filters', function (): void {
    $user = User::factory()->create(['role' => 'owner']);

    $this->actingAs($user)
        ->get(platformDashboardUrl(['period' => 'today']))
        ->assertOk()
        ->assertSee('Today');

    $this->actingAs($user)
        ->get(platformDashboardUrl(['period' => 'last_7_days']))
        ->assertOk()
        ->assertSee('Last 7 days');
});
