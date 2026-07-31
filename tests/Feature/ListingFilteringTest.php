<?php

use App\Models\Currency;
use App\Models\Invoice;
use App\Models\ReminderSchedule;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\ReminderScheduleSeeder;

/**
 * @return array{0: User, 1: Workspace, 2: Currency}
 */
function createListingFixture(string $suffix): array
{
    $user = User::factory()->create(['role' => 'owner']);
    $currency = Currency::create([
        'code' => strtoupper(substr($suffix, 0, 3)),
        'symbol' => '$',
        'name' => ucfirst($suffix).' currency',
        'is_active' => true,
    ]);
    $workspace = Workspace::create([
        'owner_id' => $user->id,
        'name' => ucfirst($suffix).' Workspace',
        'slug' => $suffix.'-'.fake()->unique()->numerify('####'),
        'subdomain' => $suffix.'-'.fake()->unique()->numerify('####'),
        'currency_id' => $currency->id,
        'is_active' => true,
    ]);
    $workspace->users()->attach($user->id, ['role' => 'owner', 'is_active' => true]);

    return [$user, $workspace, $currency];
}

function listingUrl(string $routeName, Workspace $workspace, array $query = []): string
{
    $parameters = $routeName === 'workspace.index' ? [] : $workspace;
    $path = route($routeName, $parameters, false);

    return 'http://'.$workspace->subdomain.'.'.config('app.base_domain').$path
        .($query ? '?'.http_build_query($query) : '');
}

test('client listing filters the full workspace dataset on the server', function (): void {
    [$user, $workspace] = createListingFixture('clients');

    foreach (range(1, 12) as $number) {
        $workspace->clients()->create([
            'name' => $number === 12 ? 'Target Client' : 'Client '.$number,
            'email' => 'client'.$number.'@example.test',
        ]);
    }

    $response = $this->actingAs($user)->get(listingUrl('clients.index', $workspace, [
        'search' => 'Target Client',
        'sort' => 'name',
        'direction' => 'asc',
    ]));

    $response->assertOk()->assertViewHas('filters', [
        'search' => 'Target Client',
        'sort' => 'name',
        'direction' => 'asc',
        'per_page' => 10,
    ]);

    expect($response->viewData('clients')->total())->toBe(1)
        ->and($response->viewData('clients')->first()->name)->toBe('Target Client');
});

test('invoice listing filters by status and client search', function (): void {
    [$user, $workspace, $currency] = createListingFixture('invoices');
    $targetClient = $workspace->clients()->create([
        'name' => 'Target Billing Client',
        'email' => 'target-billing@example.test',
    ]);
    $otherClient = $workspace->clients()->create([
        'name' => 'Other Client',
        'email' => 'other@example.test',
    ]);

    $targetInvoice = $workspace->invoices()->create([
        'client_id' => $targetClient->id,
        'currency_id' => $currency->id,
        'invoice_number' => 'INV-TARGET',
        'issue_date' => now()->subDay()->toDateString(),
        'due_date' => now()->addDays(10)->toDateString(),
        'status' => Invoice::STATUS_PAID,
        'subtotal' => 100,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 100,
    ]);
    $workspace->invoices()->create([
        'client_id' => $otherClient->id,
        'currency_id' => $currency->id,
        'invoice_number' => 'INV-OTHER',
        'issue_date' => now()->subDays(2)->toDateString(),
        'due_date' => now()->addDays(9)->toDateString(),
        'status' => Invoice::STATUS_SENT,
        'subtotal' => 200,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 200,
    ]);

    $response = $this->actingAs($user)->get(listingUrl('invoices.index', $workspace, [
        'search' => 'Target Billing',
        'status' => Invoice::STATUS_PAID,
    ]));

    $response->assertOk();

    expect($response->viewData('invoices')->total())->toBe(1)
        ->and($response->viewData('invoices')->first()->is($targetInvoice))->toBeTrue();
});

test('workspace user listing filters by membership role and active status', function (): void {
    [$owner, $workspace] = createListingFixture('members');
    $activeAdmin = User::factory()->create(['name' => 'Searchable Admin']);
    $inactiveAdmin = User::factory()->create(['name' => 'Inactive Admin']);
    $workspace->users()->attach($activeAdmin->id, ['role' => 'admin', 'is_active' => true]);
    $workspace->users()->attach($inactiveAdmin->id, ['role' => 'admin', 'is_active' => false]);

    $response = $this->actingAs($owner)->get(listingUrl('workspace.users.index', $workspace, [
        'search' => 'Admin',
        'role' => 'admin',
        'status' => 'active',
    ]));

    $response->assertOk();

    expect($response->viewData('users')->total())->toBe(1)
        ->and($response->viewData('users')->first()->is($activeAdmin))->toBeTrue();
});

test('workspace listing filters only the authenticated user workspaces', function (): void {
    [$user, $workspace] = createListingFixture('owned');
    [$otherUser, $otherWorkspace] = createListingFixture('other');
    $workspace->users()->attach($otherUser->id, ['role' => 'member', 'is_active' => true]);

    $response = $this->actingAs($user)->get(listingUrl('workspace.index', $workspace, [
        'search' => $otherWorkspace->name,
    ]));

    $response->assertOk();
    expect($response->viewData('workspaces')->total())->toBe(0);
});

test('reminder schedules are seeded for every workspace', function (): void {
    [, $firstWorkspace] = createListingFixture('first');
    [, $secondWorkspace] = createListingFixture('second');

    (new ReminderScheduleSeeder)->run();

    expect(ReminderSchedule::query()->where('workspace_id', $firstWorkspace->id)->count())->toBe(4)
        ->and(ReminderSchedule::query()->where('workspace_id', $secondWorkspace->id)->count())->toBe(4);
});
