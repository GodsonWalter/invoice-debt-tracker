<?php

use App\Models\User;
use App\Models\UserAccountAudit;
use App\Models\Workspace;
use App\Services\UserAccountService;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

function platformUsersUrl(string $route, mixed $parameters = []): string
{
    return 'http://'.config('app.base_domain').route($route, $parameters, false);
}

function platformUserRecoveryActionUrl(string $route, mixed $parameters = []): string
{
    return 'http://'.config('app.base_domain').route($route, $parameters, false);
}

test('platform management roles can view platform users but workspace users cannot', function (): void {
    foreach (['owner', 'admin', 'manager', 'staff'] as $role) {
        $actor = User::factory()->create(['role' => $role]);

        $this->actingAs($actor)
            ->get(platformUsersUrl('platform.users.index'))
            ->assertOk()
            ->assertSee('Platform Users');

        $this->actingAs($actor)
            ->get('/profile')
            ->assertOk()
            ->assertSee('Platform Users');
    }

    $workspaceUser = User::factory()->create(['role' => 'user']);

    $this->actingAs($workspaceUser)
        ->get(platformUsersUrl('platform.users.index'))
        ->assertForbidden();

    $this->actingAs($workspaceUser)
        ->get('/profile')
        ->assertOk()
        ->assertDontSee('Platform Users');
});

test('platform user listing supports grouped search, role, status, and sorting filters', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $matchingUser = User::factory()->create([
        'name' => 'Matching Platform User',
        'email' => 'matching-platform@example.test',
        'role' => 'staff',
    ]);
    $inactiveUser = User::factory()->create([
        'name' => 'Inactive Platform User',
        'email' => 'inactive-platform@example.test',
        'role' => 'manager',
        'is_active' => false,
    ]);
    $deletedUser = User::factory()->create([
        'name' => 'Deleted Platform User',
        'email' => 'deleted-platform@example.test',
    ]);
    $deletedUser->delete();

    $this->actingAs($admin)
        ->get(platformUsersUrl('platform.users.index', ['search' => 'Matching Platform User', 'role' => 'staff']))
        ->assertOk()
        ->assertSee($matchingUser->email)
        ->assertDontSee($inactiveUser->email)
        ->assertDontSee($deletedUser->email);

    $this->actingAs($admin)
        ->get(platformUsersUrl('platform.users.index', ['status' => 'inactive']))
        ->assertOk()
        ->assertSee($inactiveUser->email)
        ->assertDontSee($matchingUser->email);

    $this->actingAs($admin)
        ->get(platformUsersUrl('platform.users.index', ['status' => 'deleted']))
        ->assertOk()
        ->assertSee($deletedUser->email)
        ->assertDontSee($matchingUser->email);
});

test('platform user workspace counts use the real pivot column in the aggregate query', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $queries = [];

    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    $this->actingAs($admin)
        ->get(platformUsersUrl('platform.users.index'))
        ->assertOk();

    $workspaceQueries = collect($queries)
        ->filter(fn (string $sql): bool => str_contains($sql, 'workspace_user'))
        ->implode(' ');

    expect($workspaceQueries)
        ->toContain('workspace_user')
        ->toContain('is_active')
        ->not->toContain('`pivot`');
});

test('platform managers can create users within their role authority', function (): void {
    Notification::fake();
    $manager = User::factory()->create(['role' => 'manager']);

    $this->actingAs($manager)
        ->post(platformUsersUrl('platform.users.store'), [
            'name' => 'New Platform User',
            'email' => 'new-platform@example.test',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'user',
            'is_active' => '1',
        ])
        ->assertRedirect(platformUsersUrl('platform.users.index'));

    expect(User::query()->where('email', 'new-platform@example.test')->value('role'))->toBe('user');
    Notification::assertSentTo(User::query()->where('email', 'new-platform@example.test')->firstOrFail(), VerifyEmail::class);

    $this->actingAs($manager)
        ->from(platformUsersUrl('platform.users.create'))
        ->post(platformUsersUrl('platform.users.store'), [
            'name' => 'Escalated Platform User',
            'email' => 'escalated-platform@example.test',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'admin',
            'is_active' => '1',
        ])
        ->assertSessionHasErrors('role');
});

test('deactivating and reactivating a platform user synchronizes owned workspaces', function (): void {
    $owner = User::factory()->create(['role' => 'owner']);
    $target = User::factory()->create(['role' => 'user']);
    $workspace = Workspace::create([
        'owner_id' => $target->id,
        'name' => 'Target Workspace',
        'slug' => 'target-workspace-'.fake()->unique()->numerify('####'),
        'subdomain' => 'target-workspace-'.fake()->unique()->numerify('####'),
        'is_active' => true,
    ]);

    $this->actingAs($owner)
        ->put(platformUsersUrl('platform.users.update', $target->id), [
            'name' => $target->name,
            'email' => $target->email,
            'role' => 'user',
            'is_active' => '0',
        ])
        ->assertRedirect();

    expect($workspace->refresh()->is_active)->toBeFalse();

    $this->actingAs($owner)
        ->put(platformUsersUrl('platform.users.update', $target->id), [
            'name' => $target->name,
            'email' => $target->email,
            'role' => 'user',
            'is_active' => '1',
        ])
        ->assertRedirect();

    expect($workspace->refresh()->is_active)->toBeTrue();
});

test('platform soft deletion deactivates owned workspaces and recovery reactivates them', function (): void {
    $platformOwner = User::factory()->create(['role' => 'owner']);
    $target = User::factory()->create(['role' => 'user']);
    $workspace = Workspace::create([
        'owner_id' => $target->id,
        'name' => 'Deleted User Workspace',
        'slug' => 'deleted-user-workspace-'.fake()->unique()->numerify('####'),
        'subdomain' => 'deleted-user-workspace-'.fake()->unique()->numerify('####'),
        'is_active' => true,
    ]);

    $this->actingAs($platformOwner)
        ->delete(platformUsersUrl('platform.users.destroy', $target->id))
        ->assertRedirect(platformUsersUrl('platform.users.index'));

    expect(User::onlyTrashed()->find($target->id))->not->toBeNull()
        ->and($workspace->refresh()->is_active)->toBeFalse()
        ->and(UserAccountAudit::query()
            ->where('target_user_id', $target->id)
            ->where('event', UserAccountService::EVENT_DELETED_BY_PLATFORM)
            ->exists())->toBeTrue();

    $this->actingAs($platformOwner)
        ->post(platformUserRecoveryActionUrl('platform.user-recovery.restore', $target->id), [
            'reason' => 'Restore the account after a verified support request.',
        ])
        ->assertRedirect();

    expect(User::query()->find($target->id)?->is_active)->toBeTrue()
        ->and($workspace->refresh()->is_active)->toBeTrue();
});

test('platform owners can assign platform roles', function (): void {
    $owner = User::factory()->create(['role' => 'owner']);

    $response = $this->actingAs($owner)
        ->post(platformUsersUrl('platform.users.store'), [
            'name' => 'Platform Manager',
            'email' => 'platform-manager@example.test',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'manager',
            'is_active' => '1',
        ]);

    $response->assertRedirect();

    expect(User::query()->where('email', 'platform-manager@example.test')->value('role'))->toBe('manager');
});

test('platform role hierarchy protects higher-level accounts and self-management', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $manager = User::factory()->create(['role' => 'manager']);
    $owner = User::factory()->create(['role' => 'owner']);

    $this->actingAs($admin)
        ->get(platformUsersUrl('platform.users.edit', $owner->id))
        ->assertForbidden();

    $this->actingAs($admin)
        ->put(platformUsersUrl('platform.users.update', $admin->id), [
            'name' => 'Changed Admin',
            'email' => $admin->email,
            'role' => 'admin',
            'is_active' => '1',
        ])
        ->assertForbidden();

    $this->actingAs($admin)
        ->put(platformUsersUrl('platform.users.update', $manager->id), [
            'name' => 'Updated Manager',
            'email' => $manager->email,
            'role' => 'manager',
            'is_active' => '1',
        ])
        ->assertRedirect();

    expect($manager->refresh()->name)->toBe('Updated Manager');
});

test('deactivating a platform user prevents login and stale authenticated access', function (): void {
    $owner = User::factory()->create(['role' => 'owner']);
    $target = User::factory()->create(['role' => 'user']);

    $updateResponse = $this->actingAs($owner)
        ->put(platformUsersUrl('platform.users.update', $target->id), [
            'name' => $target->name,
            'email' => $target->email,
            'role' => 'user',
            'is_active' => '0',
        ]);
    $updateResponse->assertRedirect();

    Auth::forgetGuards();

    $loginResponse = $this->post('/login', [
        'email' => $target->email,
        'password' => 'password',
    ]);
    $loginResponse->assertRedirect(route('account.status'));

    $this->get(route('account.status'))
        ->assertOk()
        ->assertSee('Your account has been deactivated')
        ->assertSee('The workspaces associated with this account have also been deactivated.')
        ->assertSee('Please contact the support team');

    $this->actingAs($target->refresh())
        ->get('/profile')
        ->assertRedirect(route('login'));
});

test('a soft-deleted platform user sees the deleted account notice after valid login credentials', function (): void {
    $platformOwner = User::factory()->create(['role' => 'owner']);
    $target = User::factory()->create(['role' => 'user']);
    $workspace = Workspace::create([
        'owner_id' => $target->id,
        'name' => 'Deleted Login Workspace',
        'slug' => 'deleted-login-workspace-'.fake()->unique()->numerify('####'),
        'subdomain' => 'deleted-login-workspace-'.fake()->unique()->numerify('####'),
        'is_active' => true,
    ]);

    $this->actingAs($platformOwner)
        ->delete(platformUsersUrl('platform.users.destroy', $target->id))
        ->assertRedirect();

    Auth::forgetGuards();

    $this->post('/login', [
        'email' => $target->email,
        'password' => 'password',
    ])->assertRedirect(route('account.status'));

    $this->get(route('account.status'))
        ->assertOk()
        ->assertSee('Your account has been deleted')
        ->assertSee('This account has been soft deleted and is no longer available for login.')
        ->assertSee('The workspaces associated with this account have also been deactivated.')
        ->assertSee('Please contact the support team');

    expect($workspace->refresh()->is_active)->toBeFalse();
});

test('unavailable account notices are not shown for invalid credentials', function (): void {
    $target = User::factory()->create(['is_active' => false]);

    $this->post('/login', [
        'email' => $target->email,
        'password' => 'incorrect-password',
    ])
        ->assertSessionHasErrors('email')
        ->assertSessionMissing('account_status');
});

test('platform user management does not alter workspace membership management routes', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);

    expect(route('workspace.users.index', ['workspace' => 'example']))
        ->toContain('/workspace/example/user');

    $this->actingAs($admin)
        ->get(platformUsersUrl('platform.users.index'))
        ->assertOk();
});
