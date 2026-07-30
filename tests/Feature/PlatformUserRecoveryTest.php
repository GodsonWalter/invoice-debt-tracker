<?php

use App\Models\User;
use App\Models\UserAccountAudit;
use App\Models\Workspace;
use App\Services\UserAccountService;

function platformUserRecoveryUrl(string $route, mixed $parameters = []): string
{
    return 'http://'.config('app.base_domain').route($route, $parameters, false);
}

function deletedUserForRecovery(string $email = 'deleted@example.test'): User
{
    $user = User::factory()->create([
        'name' => 'Deleted Example',
        'email' => $email,
    ]);

    app(UserAccountService::class)->softDelete($user);

    return User::withTrashed()->findOrFail($user->id);
}

test('only the global platform owner can list deleted user accounts', function (): void {
    $platformOwner = User::factory()->create(['role' => 'owner']);
    $deletedUser = deletedUserForRecovery();

    $this->actingAs($platformOwner)
        ->get(platformUserRecoveryUrl('platform.user-recovery.index'))
        ->assertOk()
        ->assertSee($deletedUser->email);

    foreach (['admin', 'manager', 'staff', 'user'] as $role) {
        $this->actingAs(User::factory()->create(['role' => $role]))
            ->get(platformUserRecoveryUrl('platform.user-recovery.index'))
            ->assertForbidden();
    }
});

test('deleted user navigation is visible only to the global platform owner', function (): void {
    $platformOwner = User::factory()->create(['role' => 'owner']);
    deletedUserForRecovery('navigation-target@example.test');
    $regularUser = User::factory()->create(['role' => 'admin']);

    $this->actingAs($platformOwner)
        ->get('/profile')
        ->assertOk()
        ->assertSee('Deleted User Accounts');

    $this->actingAs($regularUser)
        ->get('/profile')
        ->assertOk()
        ->assertDontSee('Deleted User Accounts');
});

test('workspace pivot ownership does not grant platform user recovery access', function (): void {
    $workspaceUser = User::factory()->create(['role' => 'user']);
    $workspace = Workspace::create([
        'owner_id' => $workspaceUser->id,
        'name' => 'Workspace Owner Only',
        'slug' => 'workspace-owner-'.fake()->unique()->numerify('####'),
        'subdomain' => 'workspace-owner-'.fake()->unique()->numerify('####'),
        'is_active' => true,
    ]);
    $workspace->users()->attach($workspaceUser->id, ['role' => 'owner', 'is_active' => true]);
    deletedUserForRecovery();

    $this->actingAs($workspaceUser)
        ->get(platformUserRecoveryUrl('platform.user-recovery.index'))
        ->assertForbidden();
});

test('workspace pivot roles cannot restore deleted users without platform authority', function (): void {
    $deletedUser = deletedUserForRecovery('pivot-target@example.test');

    foreach (['owner', 'admin', 'member', 'viewer'] as $membershipRole) {
        $workspaceUser = User::factory()->create(['role' => 'user']);
        $workspace = Workspace::create([
            'owner_id' => $workspaceUser->id,
            'name' => 'Pivot Role Workspace',
            'slug' => 'pivot-role-'.fake()->unique()->numerify('####'),
            'subdomain' => 'pivot-role-'.fake()->unique()->numerify('####'),
            'is_active' => true,
        ]);
        $workspace->users()->attach($workspaceUser->id, ['role' => $membershipRole, 'is_active' => true]);

        $this->actingAs($workspaceUser)
            ->post(platformUserRecoveryUrl('platform.user-recovery.restore', $deletedUser->id), [
                'reason' => 'This workspace role is not platform authority.',
            ])
            ->assertForbidden();
    }
});

test('platform owner can restore a deleted user and audit the actor', function (): void {
    $platformOwner = User::factory()->create(['role' => 'owner']);
    $deletedUser = deletedUserForRecovery();

    $this->actingAs($platformOwner)
        ->post(platformUserRecoveryUrl('platform.user-recovery.restore', $deletedUser->id), [
            'reason' => 'Support verified the account owner identity.',
        ])
        ->assertRedirect(platformUserRecoveryUrl('platform.user-recovery.index'));

    expect(User::query()->find($deletedUser->id))->not->toBeNull()
        ->and(User::onlyTrashed()->find($deletedUser->id))->toBeNull();

    $this->post('/logout')->assertRedirect('/');

    $this->post('/login', [
        'email' => $deletedUser->email,
        'password' => 'password',
    ])->assertRedirect();
    $this->assertAuthenticatedAs(User::query()->findOrFail($deletedUser->id));

    $audit = UserAccountAudit::query()
        ->where('target_user_id', $deletedUser->id)
        ->where('event', UserAccountService::EVENT_RESTORED)
        ->latest()
        ->firstOrFail();

    expect($audit->actor_user_id)->toBe($platformOwner->id)
        ->and($audit->actor_global_role)->toBe('owner')
        ->and($audit->actor_type)->toBe('platform_owner')
        ->and($audit->reason)->toBe('Support verified the account owner identity.');
});

test('platform restoration requires an audit reason', function (): void {
    $platformOwner = User::factory()->create(['role' => 'owner']);
    $deletedUser = deletedUserForRecovery();

    $this->actingAs($platformOwner)
        ->post(platformUserRecoveryUrl('platform.user-recovery.restore', $deletedUser->id), [])
        ->assertSessionHasErrors('reason');

    expect(User::onlyTrashed()->find($deletedUser->id))->not->toBeNull();
});

test('platform restore does not reactivate memberships or deleted workspaces', function (): void {
    $platformOwner = User::factory()->create(['role' => 'owner']);
    $deletedUser = User::factory()->create(['role' => 'user']);
    $workspace = Workspace::create([
        'owner_id' => $platformOwner->id,
        'name' => 'Preserved Workspace',
        'slug' => 'preserved-workspace-'.fake()->unique()->numerify('####'),
        'subdomain' => 'preserved-workspace-'.fake()->unique()->numerify('####'),
        'is_active' => true,
    ]);
    $workspace->users()->attach($deletedUser->id, ['role' => 'member', 'is_active' => false]);
    $workspace->delete();
    app(UserAccountService::class)->softDelete($deletedUser);

    $this->actingAs($platformOwner)
        ->post(platformUserRecoveryUrl('platform.user-recovery.restore', $deletedUser->id), [
            'reason' => 'Restore after a verified support request.',
        ])
        ->assertRedirect();

    $preservedWorkspace = Workspace::withTrashed()->findOrFail($workspace->id);

    expect(Workspace::query()->find($workspace->id))->toBeNull()
        ->and((bool) $preservedWorkspace->users()->whereKey($deletedUser->id)->first()?->pivot->is_active)->toBeFalse();
});

test('deleted-user search remains restricted to deleted accounts', function (): void {
    $platformOwner = User::factory()->create(['role' => 'owner']);
    $deletedUser = deletedUserForRecovery('needle-deleted@example.test');
    User::factory()->create([
        'name' => 'Needle Active',
        'email' => 'needle-active@example.test',
    ]);

    $this->actingAs($platformOwner)
        ->get(platformUserRecoveryUrl('platform.user-recovery.index', ['search' => 'needle']))
        ->assertOk()
        ->assertSee($deletedUser->email)
        ->assertDontSee('needle-active@example.test');
});

test('restoration is rejected for an already active account', function (): void {
    $platformOwner = User::factory()->create(['role' => 'owner']);
    $activeUser = User::factory()->create();

    $this->actingAs($platformOwner)
        ->post(platformUserRecoveryUrl('platform.user-recovery.restore', $activeUser->id), [
            'reason' => 'This account should not be restored.',
        ])
        ->assertNotFound();
});
