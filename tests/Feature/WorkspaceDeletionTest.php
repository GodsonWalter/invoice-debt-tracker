<?php

use App\Models\Client;
use App\Models\User;
use App\Models\Workspace;

/**
 * @return array{0: User, 1: Workspace}
 */
function createWorkspaceDeletionFixture(string $role = 'owner'): array
{
    $user = User::factory()->create();
    $owner = $role === 'owner' ? $user : User::factory()->create();
    $suffix = strtolower($role).'-deletion-'.fake()->unique()->numerify('####');

    $workspace = Workspace::create([
        'owner_id' => $owner->id,
        'name' => ucfirst($role).' Deletion Workspace',
        'slug' => $suffix,
        'subdomain' => $suffix,
        'invoice_prefix' => 'DEL',
        'is_active' => true,
    ]);

    $workspace->users()->attach($user->id, [
        'role' => $role,
        'is_active' => true,
    ]);

    return [$user, $workspace];
}

function activeWorkspaceDeletionUrl(string $routeName, Workspace $workspace): string
{
    $parameters = $routeName === 'workspace.index' ? [] : $workspace;

    return 'http://'.$workspace->subdomain.'.'.config('app.base_domain').route($routeName, $parameters, false);
}

function requestedWorkspaceOnActiveHostUrl(string $routeName, Workspace $activeWorkspace, Workspace $requestedWorkspace): string
{
    return 'http://'.$activeWorkspace->subdomain.'.'.config('app.base_domain').route($routeName, $requestedWorkspace, false);
}

function baseDomainWorkspaceDeletionUrl(string $routeName): string
{
    return 'http://'.config('app.base_domain').route($routeName, [], false);
}

test('the owner can soft delete the active workspace and retain its related data', function () {
    [$user, $workspace] = createWorkspaceDeletionFixture();
    $client = Client::create([
        'workspace_id' => $workspace->id,
        'name' => 'Retained Client',
    ]);

    $this->actingAs($user)
        ->delete(activeWorkspaceDeletionUrl('workspace.destroy', $workspace), [
            'workspace_name' => $workspace->name,
        ])
        ->assertRedirect(baseDomainWorkspaceDeletionUrl('dashboard'))
        ->assertSessionHas('success', 'Workspace moved to recovery status.');

    expect(Workspace::find($workspace->id))->toBeNull()
        ->and(Workspace::withTrashed()->find($workspace->id)->trashed())->toBeTrue()
        ->and(Client::find($client->id))->not->toBeNull()
        ->and($user->workspaces()->whereKey($workspace->id)->exists())->toBeFalse();
});

test('the workspace deletion control requires the exact workspace name', function () {
    [$user, $workspace] = createWorkspaceDeletionFixture();

    $this->actingAs($user)
        ->get(activeWorkspaceDeletionUrl('workspace.index', $workspace))
        ->assertOk()
        ->assertSee('data-delete-confirm-name="'.$workspace->name.'"', false)
        ->assertSee('name="workspace_name"', false);

    $this->actingAs($user)
        ->delete(activeWorkspaceDeletionUrl('workspace.destroy', $workspace), [])
        ->assertSessionHasErrors('workspace_name');

    expect($workspace->refresh()->trashed())->toBeFalse();

    $this->actingAs($user)
        ->delete(activeWorkspaceDeletionUrl('workspace.destroy', $workspace), [
            'workspace_name' => strtolower($workspace->name),
        ])
        ->assertSessionHasErrors('workspace_name');

    expect($workspace->refresh()->trashed())->toBeFalse();
});

test('admins and members cannot delete a workspace', function (string $role) {
    [$user, $workspace] = createWorkspaceDeletionFixture($role);

    $this->actingAs($user)
        ->get(activeWorkspaceDeletionUrl('workspace.index', $workspace))
        ->assertOk()
        ->assertDontSee('title="Delete workspace"', false);

    $this->actingAs($user)
        ->delete(activeWorkspaceDeletionUrl('workspace.destroy', $workspace), [
            'workspace_name' => $workspace->name,
        ])
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('error', 'Only the workspace owner can delete the workspace.');

    expect($workspace->refresh()->trashed())->toBeFalse();
})->with(['admin', 'member']);

test('a non-member cannot delete a workspace', function () {
    [, $workspace] = createWorkspaceDeletionFixture();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->delete(activeWorkspaceDeletionUrl('workspace.destroy', $workspace), [
            'workspace_name' => $workspace->name,
        ])
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('error', 'You are not authorized to access this workspace.');

    expect($workspace->refresh()->trashed())->toBeFalse();
});

test('an owner cannot delete a workspace that is not active', function () {
    [$user, $workspace] = createWorkspaceDeletionFixture();

    $this->actingAs($user)
        ->delete(route('workspace.destroy', $workspace), [
            'workspace_name' => $workspace->name,
        ])
        ->assertRedirect(route('workspace.index'))
        ->assertSessionHas('error', 'Please switch to a workspace before accessing it.');

    expect($workspace->refresh()->trashed())->toBeFalse();
});

test('changing the workspace route parameter cannot bypass active-workspace validation', function () {
    [$user, $activeWorkspace] = createWorkspaceDeletionFixture();
    $otherOwner = User::factory()->create();
    $otherWorkspace = Workspace::create([
        'owner_id' => $otherOwner->id,
        'name' => 'Other Deletion Workspace',
        'slug' => 'other-deletion-'.fake()->unique()->numerify('####'),
        'subdomain' => 'other-deletion-'.fake()->unique()->numerify('####'),
        'invoice_prefix' => 'OTH',
        'is_active' => true,
    ]);
    $otherWorkspace->users()->attach($user->id, [
        'role' => 'owner',
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->delete(requestedWorkspaceOnActiveHostUrl('workspace.destroy', $activeWorkspace, $otherWorkspace), [
            'workspace_name' => $otherWorkspace->name,
        ])
        ->assertRedirect(route('workspace.index'))
        ->assertSessionHas('error', 'Please switch to this workspace before accessing it.');

    expect($otherWorkspace->refresh()->trashed())->toBeFalse();
});

test('deleted workspaces are excluded from normal access and switching', function () {
    [$user, $workspace] = createWorkspaceDeletionFixture();

    $this->actingAs($user)
        ->delete(activeWorkspaceDeletionUrl('workspace.destroy', $workspace), [
            'workspace_name' => $workspace->name,
        ])
        ->assertRedirect(baseDomainWorkspaceDeletionUrl('dashboard'));

    $this->actingAs($user)
        ->get(route('workspace.show', $workspace))
        ->assertNotFound();

    $this->actingAs($user)
        ->get(route('workspace.edit', $workspace))
        ->assertNotFound();

    $this->actingAs($user)
        ->put(route('workspace.update', $workspace), [])
        ->assertNotFound();

    $this->actingAs($user)
        ->get('http://'.$workspace->subdomain.'.'.config('app.base_domain').'/switch')
        ->assertRedirect();

    $this->actingAs($user)
        ->get('http://'.config('app.base_domain').route('workspace.index', [], false))
        ->assertOk()
        ->assertDontSee($workspace->name);
});
