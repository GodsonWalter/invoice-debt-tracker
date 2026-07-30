<?php

use App\Models\User;
use App\Models\UserAccountAudit;
use App\Models\Workspace;
use App\Services\UserAccountService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get('/profile');

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $user->refresh();

    $this->assertSame('Test User', $user->name);
    $this->assertSame('test@example.com', $user->email);
    $this->assertNull($user->email_verified_at);
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $this->assertNotNull($user->refresh()->email_verified_at);
});

test('user can soft delete their account and is logged out', function (): void {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete('/profile', [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/');

    $this->assertGuest();
    $this->assertSoftDeleted('users', ['id' => $user->id]);
    expect(User::withTrashed()->find($user->id)->remember_token)->toBeNull()
        ->and(UserAccountAudit::where('target_user_id', $user->id)->where('event', UserAccountService::EVENT_DELETED)->exists())->toBeTrue();
});

test('a deleted user cannot authenticate or use a stale session', function (): void {
    $user = User::factory()->create();

    $user->delete();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->actingAs($user)->get('/profile')->assertOk();
    $user->delete();
    Auth::forgetGuards();

    $this->get('/profile')->assertRedirect('/login');
});

test('a deleted user cannot request a password reset', function (): void {
    Notification::fake();
    $user = User::factory()->create();
    $user->delete();

    $this->post('/forgot-password', ['email' => $user->email])
        ->assertSessionHasErrors('email');

    Notification::assertNothingSent();
    Notification::assertNotSentTo($user, ResetPassword::class);
});

test('a deleted user email remains reserved for identity safety', function (): void {
    $user = User::factory()->create();
    $user->delete();

    $this->post('/register', [
        'name' => 'Replacement Account',
        'email' => $user->email,
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors('email');
});

test('a deleted first account cannot cause a new registrant to become platform owner', function (): void {
    $deletedOwner = User::factory()->create(['role' => 'owner']);
    $deletedOwner->delete();

    $this->post('/register', [
        'name' => 'New Account',
        'email' => 'new-account@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect();

    expect(User::query()->where('email', 'new-account@example.test')->value('role'))->toBe('user');
});

test('account deletion is blocked while the user owns a non-deleted workspace', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::create([
        'owner_id' => $user->id,
        'name' => 'Owned Workspace',
        'slug' => 'owned-workspace-'.fake()->unique()->numerify('####'),
        'subdomain' => 'owned-workspace-'.fake()->unique()->numerify('####'),
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->from('/profile')
        ->delete('/profile', ['password' => 'password'])
        ->assertRedirect('/profile')
        ->assertSessionHasErrorsIn('userDeletion', 'account');

    expect(User::query()->find($user->id))->not->toBeNull()
        ->and($workspace->fresh())->not->toBeNull();
});

test('an owner of only soft-deleted workspaces may soft delete their account', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::create([
        'owner_id' => $user->id,
        'name' => 'Deleted Workspace',
        'slug' => 'deleted-workspace-'.fake()->unique()->numerify('####'),
        'subdomain' => 'deleted-workspace-'.fake()->unique()->numerify('####'),
        'is_active' => true,
    ]);
    $workspace->delete();

    $this->actingAs($user)
        ->delete('/profile', ['password' => 'password'])
        ->assertRedirect('/');

    $this->assertSoftDeleted('users', ['id' => $user->id]);
    $this->assertSoftDeleted('workspaces', ['id' => $workspace->id]);
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->delete('/profile', [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrorsIn('userDeletion', 'password')
        ->assertRedirect('/profile');

    $this->assertNotNull($user->fresh());
});

test('platform-level accounts cannot delete themselves', function (): void {
    foreach (['owner', 'admin', 'manager', 'staff'] as $role) {
        $user = User::factory()->create(['role' => $role]);

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertDontSee('Delete Account');

        $this->actingAs($user)
            ->delete('/profile', ['password' => 'password'])
            ->assertForbidden();

        expect(User::query()->find($user->id))->not->toBeNull();
    }
});
