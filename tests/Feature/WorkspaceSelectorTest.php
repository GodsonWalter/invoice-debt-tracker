<?php

use App\Models\User;
use App\Models\Workspace;

function workspaceSelectorUrl(): string
{
    return 'http://'.config('app.base_domain').route('dashboard', [], false);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function createWorkspaceSelectorWorkspace(string $key, ?User $owner = null, array $overrides = []): Workspace
{
    $owner ??= User::factory()->create();
    $suffix = $key.'-'.fake()->unique()->numerify('####');

    return Workspace::create(array_merge([
        'owner_id' => $owner->id,
        'name' => ucfirst($key).' Workspace',
        'slug' => $suffix,
        'subdomain' => $suffix,
        'invoice_prefix' => strtoupper(substr($key, 0, 3)),
        'is_active' => true,
    ], $overrides));
}

function attachWorkspaceSelectorMembership(Workspace $workspace, User $user, string $role = 'member', bool $isActive = true): void
{
    $workspace->users()->attach($user->id, [
        'role' => $role,
        'is_active' => $isActive,
    ]);
}

test('the workspace selector shows only active workspaces with active memberships', function (): void {
    $user = User::factory()->create();
    $visibleWorkspace = createWorkspaceSelectorWorkspace('visible');
    $inactiveMembershipWorkspace = createWorkspaceSelectorWorkspace('inactive-membership');
    $inactiveWorkspace = createWorkspaceSelectorWorkspace('inactive');
    $deletedWorkspace = createWorkspaceSelectorWorkspace('deleted');
    $outsideWorkspace = createWorkspaceSelectorWorkspace('outside');

    attachWorkspaceSelectorMembership($visibleWorkspace, $user, 'member');
    attachWorkspaceSelectorMembership($inactiveMembershipWorkspace, $user, 'viewer', false);
    attachWorkspaceSelectorMembership($inactiveWorkspace, $user, 'admin');
    attachWorkspaceSelectorMembership($deletedWorkspace, $user, 'owner');
    $inactiveWorkspace->update(['is_active' => false]);
    $deletedWorkspace->delete();

    $response = $this->actingAs($user)->get(workspaceSelectorUrl());

    $response
        ->assertOk()
        ->assertSee('Your workspaces')
        ->assertSee($visibleWorkspace->name)
        ->assertSee('member')
        ->assertDontSee($inactiveMembershipWorkspace->name)
        ->assertDontSee($inactiveWorkspace->name)
        ->assertDontSee($deletedWorkspace->name)
        ->assertDontSee($outsideWorkspace->name);
});

test('a workspace selector card uses the existing workspace switching flow', function (): void {
    $user = User::factory()->create();
    $workspace = createWorkspaceSelectorWorkspace('switchable');
    attachWorkspaceSelectorMembership($workspace, $user, 'member');

    $switchUrl = route('workspace.switch', ['workspace' => $workspace->subdomain]);

    $this->actingAs($user)
        ->get(workspaceSelectorUrl())
        ->assertOk()
        ->assertSee($switchUrl, false)
        ->assertSee('Switch Workspace');

    $this->actingAs($user)
        ->get($switchUrl)
        ->assertRedirect('http://'.$workspace->subdomain.'.'.config('app.base_domain').'/dashboard');
});

test('the selector exposes the existing authorized workspace creation flow', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(workspaceSelectorUrl())
        ->assertOk()
        ->assertSee(route('workspace.create', [], false), false)
        ->assertSee('Create Workspace');

    $this->actingAs($user)
        ->get('http://'.config('app.base_domain').route('workspace.create', [], false))
        ->assertOk()
        ->assertSee('Create workspace');
});

test('workspace creation remains protected by the existing authentication middleware', function (): void {
    $this->get(workspaceSelectorUrl())
        ->assertRedirect(route('login'));

    $this->get('http://'.config('app.base_domain').route('workspace.create', [], false))
        ->assertRedirect(route('login'));
});

test('the selector renders a clean onboarding state when the user has no workspaces', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(workspaceSelectorUrl())
        ->assertOk()
        ->assertSee('No workspaces yet')
        ->assertSee('Create your first business workspace')
        ->assertSee('Create Workspace')
        ->assertSee(route('workspace.create', [], false), false);
});
