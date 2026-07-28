<?php

use App\Models\Currency;
use App\Models\User;
use App\Models\Workspace;
use AppHttp\Middleware\EnsureWorkspaceIsActive;
use AppHttp\Middleware\ResolveWorkspace;

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

beforeEach(function () {
    $this->withoutMiddleware([EnsureWorkspaceIsActive::class, ResolveWorkspace::class]);
    view()->share('currentWorkspace', null);
});

test('a workspace owner sees and can use workspace editing', function () {
    [$user, $workspace, $currency] = createWorkspaceAuthorizationFixture('owner');

    $this->actingAs($user)
        ->get(route('workspace.index'))
        ->assertOk()
        ->assertSee(route('workspace.edit', $workspace), false);

    $this->actingAs($user)
        ->get(route('workspace.edit', $workspace))
        ->assertOk()
        ->assertSee('Edit Workspace');

    $this->actingAs($user)
        ->put(route('workspace.update', $workspace), workspaceUpdatePayload($currency, 'owner-updated'))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('workspace.show', $workspace));

    expect($workspace->refresh()->name)->toBe('Updated Workspace');
});

test('a workspace admin sees and can use workspace editing', function () {
    [$user, $workspace, $currency] = createWorkspaceAuthorizationFixture('admin');

    $this->actingAs($user)
        ->get(route('workspace.index'))
        ->assertOk()
        ->assertSee(route('workspace.edit', $workspace), false);

    $this->actingAs($user)
        ->get(route('workspace.edit', $workspace))
        ->assertOk();

    $this->actingAs($user)
        ->put(route('workspace.update', $workspace), workspaceUpdatePayload($currency, 'admin-updated'))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('workspace.show', $workspace));

    expect($workspace->refresh()->name)->toBe('Updated Workspace');
});

test('a workspace member does not see or access workspace editing', function () {
    [$user, $workspace, $currency] = createWorkspaceAuthorizationFixture('member');

    $this->actingAs($user)
        ->get(route('workspace.index'))
        ->assertOk()
        ->assertDontSee(route('workspace.edit', $workspace), false);

    $this->actingAs($user)
        ->get(route('workspace.edit', $workspace))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('error', 'You are not authorized to manage this workspace.');

    $this->actingAs($user)
        ->put(route('workspace.update', $workspace), workspaceUpdatePayload($currency, 'member-updated'))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('error', 'You are not authorized to manage this workspace.');

    expect($workspace->refresh()->name)->toBe('Member Authorization Workspace');
});

test('a user outside a workspace cannot see or access workspace editing', function () {
    [$member, $workspace, $currency] = createWorkspaceAuthorizationFixture('member');
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('workspace.index'))
        ->assertOk()
        ->assertDontSee(route('workspace.edit', $workspace), false);

    $this->actingAs($user)
        ->get(route('workspace.edit', $workspace))
        ->assertRedirect(route('dashboard'));

    $this->actingAs($user)
        ->put(route('workspace.update', $workspace), workspaceUpdatePayload($currency, 'outside-updated'))
        ->assertRedirect(route('dashboard'));

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
        ->get(route('workspace.index'))
        ->assertOk()
        ->assertSee(route('workspace.edit', $managedWorkspace), false)
        ->assertDontSee(route('workspace.edit', $otherWorkspace), false);

    $this->actingAs($user)
        ->get(route('workspace.edit', $otherWorkspace))
        ->assertRedirect(route('dashboard'));

    $this->actingAs($user)
        ->put(route('workspace.update', $otherWorkspace), workspaceUpdatePayload($currency, 'cross-tenant-update'))
        ->assertRedirect(route('dashboard'));

    expect($otherWorkspace->refresh()->name)->toBe('Member Authorization Workspace');
});
