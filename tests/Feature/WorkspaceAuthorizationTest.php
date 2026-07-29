<?php

use App\Models\Currency;
use App\Models\User;
use App\Models\Workspace;

/**
 * @return array{0: User, 1: Workspace, 2: Currency}
 */
function createWorkspaceAuthorizationFixture(string $role): array
{
    $user = User::factory()->create([
        'role' => $role === 'admin' ? 'admin' : 'user',
    ]);
    $owner = $role === 'owner' ? $user : User::factory()->create();
    $suffix = strtolower($role).'-'.fake()->unique()->numerify('####');

    $workspace = Workspace::create([
        'owner_id' => $owner->id,
        'name' => ucfirst($role).' Authorization Workspace',
        'slug' => $suffix,
        'subdomain' => $suffix,
        'invoice_prefix' => 'AUTH',
        'is_active' => true,
    ]);

    $workspace->users()->attach($user->id, [
        'role' => $role,
        'is_active' => true,
    ]);

    $currency = Currency::create([
        'code' => strtoupper(substr($role, 0, 3)),
        'symbol' => '$',
        'name' => ucfirst($role).' Currency',
        'is_active' => true,
    ]);

    return [$user, $workspace, $currency];
}

function workspaceUpdatePayload(Currency $currency, string $slug = 'updated-workspace'): array
{
    return [
        'name' => 'Updated Workspace',
        'slug' => $slug,
        'subdomain' => $slug,
        'invoice_prefix' => 'UPD',
        'currency_id' => $currency->id,
        'is_active' => 1,
    ];
}

function activeWorkspaceRoute(string $routeName, Workspace $workspace): string
{
    $parameters = $routeName === 'workspace.index' ? [] : $workspace;

    return 'http://'.$workspace->subdomain.'.'.config('app.base_domain').route($routeName, $parameters, false);
}

function routeFromActiveWorkspace(string $routeName, Workspace $activeWorkspace, Workspace $requestedWorkspace): string
{
    return 'http://'.$activeWorkspace->subdomain.'.'.config('app.base_domain').route($routeName, $requestedWorkspace, false);
}

beforeEach(function () {
    view()->share('currentWorkspace', null);
});

test('a workspace owner sees and can use workspace editing', function () {
    [$user, $workspace, $currency] = createWorkspaceAuthorizationFixture('owner');

    $this->actingAs($user)
        ->get(activeWorkspaceRoute('workspace.index', $workspace))
        ->assertOk()
        ->assertSee(route('workspace.edit', $workspace, false), false);

    $this->actingAs($user)
        ->get(activeWorkspaceRoute('workspace.edit', $workspace))
        ->assertOk()
        ->assertSee('Edit Workspace');

    $response = $this->actingAs($user)
        ->put(activeWorkspaceRoute('workspace.update', $workspace), workspaceUpdatePayload($currency, 'owner-updated'))
        ->assertSessionHasNoErrors();

    $workspace->refresh();
    $response->assertRedirect(activeWorkspaceRoute('workspace.show', $workspace));

    expect($workspace->refresh()->name)->toBe('Updated Workspace');
});

test('a workspace admin sees and can use workspace editing', function () {
    [$user, $workspace, $currency] = createWorkspaceAuthorizationFixture('admin');

    $this->actingAs($user)
        ->get(activeWorkspaceRoute('workspace.index', $workspace))
        ->assertOk()
        ->assertSee(route('workspace.edit', $workspace, false), false);

    $this->actingAs($user)
        ->get(activeWorkspaceRoute('workspace.edit', $workspace))
        ->assertOk();

    $response = $this->actingAs($user)
        ->put(activeWorkspaceRoute('workspace.update', $workspace), workspaceUpdatePayload($currency, 'admin-updated'))
        ->assertSessionHasNoErrors();

    $workspace->refresh();
    $response->assertRedirect(activeWorkspaceRoute('workspace.show', $workspace));

    expect($workspace->refresh()->name)->toBe('Updated Workspace');
});

test('a workspace member does not see or access workspace editing', function () {
    [$user, $workspace, $currency] = createWorkspaceAuthorizationFixture('member');

    $this->actingAs($user)
        ->get(activeWorkspaceRoute('workspace.index', $workspace))
        ->assertOk()
        ->assertDontSee('href="'.route('workspace.show', $workspace, false).'"', false)
        ->assertDontSee(route('workspace.edit', $workspace, false), false);

    $this->actingAs($user)
        ->get(activeWorkspaceRoute('workspace.edit', $workspace))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('error', 'You are not authorized to manage this workspace.');

    $this->actingAs($user)
        ->put(activeWorkspaceRoute('workspace.update', $workspace), workspaceUpdatePayload($currency, 'member-updated'))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('error', 'You are not authorized to manage this workspace.');

    expect($workspace->refresh()->name)->toBe('Member Authorization Workspace');
});

test('a user outside a workspace cannot see or access workspace editing', function () {
    [$member, $workspace, $currency] = createWorkspaceAuthorizationFixture('member');
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(activeWorkspaceRoute('workspace.index', $workspace))
        ->assertOk()
        ->assertDontSee(route('workspace.edit', $workspace, false), false);

    $this->actingAs($user)
        ->get(activeWorkspaceRoute('workspace.edit', $workspace))
        ->assertRedirect(route('dashboard'));

    $this->actingAs($user)
        ->put(activeWorkspaceRoute('workspace.update', $workspace), workspaceUpdatePayload($currency, 'outside-updated'))
        ->assertRedirect(route('dashboard'));

    $switchUrl = 'http://'.$workspace->subdomain.'.'.config('app.base_domain').'/switch';

    $this->actingAs($user)
        ->get($switchUrl)
        ->assertRedirect(route('workspace.index'))
        ->assertSessionHas('error', 'You are not authorized to switch to this workspace.');

    expect($workspace->refresh()->name)->toBe('Member Authorization Workspace')
        ->and($member->id)->not->toBe($user->id);
});

test('an admin of one workspace cannot edit another workspace without an admin role there', function () {
    [$user, $managedWorkspace, $currency] = createWorkspaceAuthorizationFixture('admin');
    [, $otherWorkspace] = createWorkspaceAuthorizationFixture('member');

    $otherWorkspace->users()->attach($user->id, [
        'role' => 'member',
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->get(activeWorkspaceRoute('workspace.index', $managedWorkspace))
        ->assertOk()
        ->assertSee(route('workspace.edit', $managedWorkspace, false), false)
        ->assertDontSee('href="'.route('workspace.show', $otherWorkspace, false).'"', false)
        ->assertDontSee(route('workspace.edit', $otherWorkspace, false), false);

    $this->actingAs($user)
        ->get(routeFromActiveWorkspace('workspace.edit', $managedWorkspace, $otherWorkspace))
        ->assertRedirect(route('workspace.index'))
        ->assertSessionHas('error', 'Please switch to this workspace before accessing it.');

    $this->actingAs($user)
        ->put(routeFromActiveWorkspace('workspace.update', $managedWorkspace, $otherWorkspace), workspaceUpdatePayload($currency, 'cross-tenant-update'))
        ->assertRedirect(route('workspace.index'))
        ->assertSessionHas('error', 'Please switch to this workspace before accessing it.');

    expect($otherWorkspace->refresh()->name)->toBe('Member Authorization Workspace');
});

test('workspace details and editing require an active workspace', function () {
    [$user, $workspace, $currency] = createWorkspaceAuthorizationFixture('owner');

    $this->actingAs($user)
        ->get(route('workspace.index'))
        ->assertOk()
        ->assertDontSee('href="'.route('workspace.show', $workspace, false).'"', false)
        ->assertDontSee(route('workspace.edit', $workspace, false), false);

    $this->actingAs($user)
        ->get(route('workspace.show', $workspace))
        ->assertRedirect(route('workspace.index'))
        ->assertSessionHas('error', 'Please switch to a workspace before accessing it.');

    $this->actingAs($user)
        ->get(route('workspace.edit', $workspace))
        ->assertRedirect(route('workspace.index'))
        ->assertSessionHas('error', 'Please switch to a workspace before accessing it.');

    $this->actingAs($user)
        ->put(route('workspace.update', $workspace), workspaceUpdatePayload($currency, 'inactive-update'))
        ->assertRedirect(route('workspace.index'))
        ->assertSessionHas('error', 'Please switch to a workspace before accessing it.');

    expect($workspace->refresh()->name)->toBe('Owner Authorization Workspace');
});

test('workspace actions include a switch link for another active workspace', function () {
    [$user, $activeWorkspace] = createWorkspaceAuthorizationFixture('owner');
    $otherWorkspace = Workspace::create([
        'owner_id' => User::factory()->create()->id,
        'name' => 'Other Switch Workspace',
        'slug' => 'other-switch-workspace-'.fake()->unique()->numerify('####'),
        'subdomain' => 'other-switch-workspace-'.fake()->unique()->numerify('####'),
        'invoice_prefix' => 'SWI',
        'is_active' => true,
    ]);
    $otherWorkspace->users()->attach($user->id, [
        'role' => 'member',
        'is_active' => true,
    ]);

    $switchUrl = route('workspace.switch', ['workspace' => $otherWorkspace->subdomain]);

    $this->actingAs($user)
        ->get(activeWorkspaceRoute('workspace.index', $activeWorkspace))
        ->assertOk()
        ->assertSee($switchUrl, false)
        ->assertSee('Switch', false);
});

test('switching workspaces changes which workspace can be viewed and edited', function () {
    [$user, $firstWorkspace] = createWorkspaceAuthorizationFixture('owner');
    $secondOwner = User::factory()->create();

    $secondWorkspace = Workspace::create([
        'owner_id' => $secondOwner->id,
        'name' => 'Second Managed Workspace',
        'slug' => 'second-managed-workspace',
        'subdomain' => 'second-managed-workspace',
        'invoice_prefix' => 'SEC',
        'is_active' => true,
    ]);
    $secondWorkspace->users()->attach($user->id, [
        'role' => 'admin',
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->get(activeWorkspaceRoute('workspace.show', $firstWorkspace))
        ->assertOk()
        ->assertSee($firstWorkspace->name);

    $this->actingAs($user)
        ->get(routeFromActiveWorkspace('workspace.show', $firstWorkspace, $secondWorkspace))
        ->assertRedirect(route('workspace.index'))
        ->assertSessionHas('error', 'Please switch to this workspace before accessing it.');

    $switchUrl = 'http://'.$secondWorkspace->subdomain.'.'.config('app.base_domain').'/switch';

    $this->actingAs($user)
        ->get($switchUrl)
        ->assertRedirect('http://'.$secondWorkspace->subdomain.'.'.config('app.base_domain').'/dashboard');

    $this->actingAs($user)
        ->get(activeWorkspaceRoute('workspace.show', $secondWorkspace))
        ->assertOk()
        ->assertSee($secondWorkspace->name);

    $this->actingAs($user)
        ->get(activeWorkspaceRoute('workspace.edit', $secondWorkspace))
        ->assertOk()
        ->assertSee('Edit Workspace');

    $tamperedUrl = 'http://'.$secondWorkspace->subdomain.'.'.config('app.base_domain').route('workspace.show', $firstWorkspace, false);

    $this->actingAs($user)
        ->get($tamperedUrl)
        ->assertRedirect(route('workspace.index'))
        ->assertSessionHas('error', 'Please switch to this workspace before accessing it.');
});
