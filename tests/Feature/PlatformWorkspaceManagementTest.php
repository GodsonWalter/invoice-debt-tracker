<?php

use App\Models\Client;
use App\Models\User;
use App\Models\Workspace;
use App\Services\PlatformWorkspaceManagementService;
use Illuminate\Support\Str;

function platformWorkspaceManagementUrl(string $routeName, mixed $parameters = []): string
{
    return 'http://'.config('app.base_domain').route($routeName, $parameters, false);
}

/**
 * @return array{0: User, 1: Workspace}
 */
function platformManagedWorkspaceFixture(string $key, string $role = 'user'): array
{
    $owner = User::factory()->create(['role' => $role]);
    $slug = $key.'-'.fake()->unique()->numerify('####');
    $workspace = Workspace::create([
        'owner_id' => $owner->id,
        'name' => Str::headline($key).' Workspace',
        'slug' => $slug,
        'subdomain' => $slug,
        'invoice_prefix' => strtoupper(substr($key, 0, 3)),
        'is_active' => true,
    ]);
    $workspace->users()->attach($owner->id, ['role' => 'owner', 'is_active' => true]);

    return [$owner, $workspace];
}

test('platform management roles can view platform workspaces while workspace users cannot', function (): void {
    foreach (['owner', 'admin', 'manager', 'staff'] as $role) {
        $actor = User::factory()->create(['role' => $role]);

        $this->actingAs($actor)
            ->get(platformWorkspaceManagementUrl('platform.workspaces.index'))
            ->assertOk()
            ->assertSee('Platform Workspaces')
            ->assertSee('Add workspace');
    }

    $workspaceUser = User::factory()->create(['role' => 'user']);

    $this->actingAs($workspaceUser)
        ->get(platformWorkspaceManagementUrl('platform.workspaces.index'))
        ->assertForbidden();
});

test('platform workspace listing filters active inactive and deleted workspaces', function (): void {
    $platformOwner = User::factory()->create(['role' => 'owner']);
    [, $activeWorkspace] = platformManagedWorkspaceFixture('active-platform');
    [, $inactiveWorkspace] = platformManagedWorkspaceFixture('inactive-platform');
    $inactiveWorkspace->update(['is_active' => false]);
    [, $deletedWorkspace] = platformManagedWorkspaceFixture('deleted-platform');
    $deletedWorkspace->delete();

    $this->actingAs($platformOwner)
        ->get(platformWorkspaceManagementUrl('platform.workspaces.index', ['status' => 'active']))
        ->assertOk()
        ->assertSee($activeWorkspace->name)
        ->assertDontSee($inactiveWorkspace->name)
        ->assertDontSee($deletedWorkspace->name);

    $this->actingAs($platformOwner)
        ->get(platformWorkspaceManagementUrl('platform.workspaces.index', ['status' => 'inactive']))
        ->assertOk()
        ->assertSee($inactiveWorkspace->name)
        ->assertDontSee($activeWorkspace->name)
        ->assertDontSee($deletedWorkspace->name);

    $this->actingAs($platformOwner)
        ->get(platformWorkspaceManagementUrl('platform.workspaces.index', ['status' => 'deleted']))
        ->assertOk()
        ->assertSee($deletedWorkspace->name)
        ->assertDontSee($activeWorkspace->name)
        ->assertDontSee($inactiveWorkspace->name)
        ->assertSee('Recovery');
});

test('platform managers can create a workspace with an owner and membership', function (): void {
    $manager = User::factory()->create(['role' => 'manager']);
    $workspaceOwner = User::factory()->create(['role' => 'user']);

    $this->actingAs($manager)
        ->post(platformWorkspaceManagementUrl('platform.workspaces.store'), [
            'name' => 'Created Platform Workspace',
            'slug' => 'created-platform-workspace',
            'subdomain' => 'created-platform-workspace',
            'invoice_prefix' => 'CRE',
            'metadata' => '{"region":"ng"}',
            'owner_id' => $workspaceOwner->id,
            'is_active' => '1',
        ])
        ->assertRedirect(platformWorkspaceManagementUrl('platform.workspaces.index'));

    $workspace = Workspace::query()->where('slug', 'created-platform-workspace')->firstOrFail();

    expect($workspace->owner_id)->toBe($workspaceOwner->id)
        ->and($workspace->metadata)->toBe(['region' => 'ng'])
        ->and($workspace->users()->whereKey($workspaceOwner->id)->wherePivot('role', 'owner')->exists())->toBeTrue();
});

test('platform workspace creation and update reject subdomains with unsupported symbols', function (): void {
    $manager = User::factory()->create(['role' => 'manager']);
    $workspaceOwner = User::factory()->create(['role' => 'user']);

    $this->actingAs($manager)
        ->post(platformWorkspaceManagementUrl('platform.workspaces.store'), [
            'name' => 'Invalid Platform Workspace',
            'slug' => 'invalid-platform-workspace',
            'subdomain' => 'invalid.subdomain',
            'owner_id' => $workspaceOwner->id,
            'is_active' => true,
        ])
        ->assertSessionHasErrors('subdomain');

    [, $workspace] = platformManagedWorkspaceFixture('platform-subdomain-validation');

    $this->actingAs($manager)
        ->put(platformWorkspaceManagementUrl('platform.workspaces.update', $workspace), [
            'name' => $workspace->name,
            'slug' => $workspace->slug,
            'subdomain' => 'invalid/subdomain',
            'owner_id' => $workspace->owner_id,
            'is_active' => true,
        ])
        ->assertSessionHasErrors('subdomain');

    expect($workspace->refresh()->subdomain)->toBe($workspace->slug);
});

test('platform managers can update workspace configuration and transfer ownership safely', function (): void {
    $manager = User::factory()->create(['role' => 'manager']);
    [$oldOwner, $workspace] = platformManagedWorkspaceFixture('transfer-platform');
    $newOwner = User::factory()->create(['role' => 'user']);

    $this->actingAs($manager)
        ->put(platformWorkspaceManagementUrl('platform.workspaces.update', $workspace), [
            'name' => 'Transferred Platform Workspace',
            'slug' => $workspace->slug,
            'subdomain' => $workspace->subdomain,
            'invoice_prefix' => 'TRN',
            'metadata' => '{"tier":"managed"}',
            'owner_id' => $newOwner->id,
            'is_active' => '0',
        ])
        ->assertRedirect(platformWorkspaceManagementUrl('platform.workspaces.index'));

    $workspace->refresh();

    expect($workspace->name)->toBe('Transferred Platform Workspace')
        ->and($workspace->owner_id)->toBe($newOwner->id)
        ->and($workspace->is_active)->toBeFalse()
        ->and($workspace->users()->whereKey($oldOwner->id)->wherePivot('role', 'admin')->exists())->toBeTrue()
        ->and($workspace->users()->whereKey($newOwner->id)->wherePivot('role', 'owner')->exists())->toBeTrue();
});

test('only the platform owner can soft delete a workspace and exact name confirmation is required', function (): void {
    $platformOwner = User::factory()->create(['role' => 'owner']);
    $admin = User::factory()->create(['role' => 'admin']);
    [, $workspace] = platformManagedWorkspaceFixture('soft-delete-platform');
    $client = Client::create([
        'workspace_id' => $workspace->id,
        'name' => 'Retained tenant client',
    ]);

    $this->actingAs($admin)
        ->delete(platformWorkspaceManagementUrl('platform.workspaces.destroy', $workspace), [
            'workspace_name' => $workspace->name,
        ])
        ->assertForbidden();

    $this->actingAs($platformOwner)
        ->delete(platformWorkspaceManagementUrl('platform.workspaces.destroy', $workspace), [
            'workspace_name' => 'Wrong workspace name',
        ])
        ->assertSessionHasErrors('workspace_name');

    expect($workspace->refresh()->trashed())->toBeFalse();

    $this->actingAs($platformOwner)
        ->delete(platformWorkspaceManagementUrl('platform.workspaces.destroy', $workspace), [
            'workspace_name' => $workspace->name,
        ])
        ->assertRedirect(platformWorkspaceManagementUrl('platform.workspaces.index'));

    expect(Workspace::find($workspace->id))->toBeNull()
        ->and(Workspace::withTrashed()->find($workspace->id)->trashed())->toBeTrue()
        ->and(Client::find($client->id))->not->toBeNull();
});

test('platform workspace management links deleted workspaces to the audited restore flow', function (): void {
    $platformOwner = User::factory()->create(['role' => 'owner']);
    [, $workspace] = platformManagedWorkspaceFixture('restore-platform');
    $workspace->delete();

    $this->actingAs($platformOwner)
        ->get(platformWorkspaceManagementUrl('platform.workspaces.index', ['status' => 'deleted']))
        ->assertOk()
        ->assertSee(route('platform.recovery.show', $workspace->id));

    $this->actingAs($platformOwner)
        ->post(platformWorkspaceManagementUrl('platform.recovery.restore', $workspace->id), [
            'reason' => 'Support approved restoration after ownership verification.',
        ])
        ->assertRedirect(platformWorkspaceManagementUrl('platform.recovery.index'));

    expect(Workspace::find($workspace->id))->not->toBeNull()
        ->and(Workspace::find($workspace->id)->is_active)->toBeTrue();
});

test('platform workspace aggregates remain scoped to each workspace', function (): void {
    $platformOwner = User::factory()->create(['role' => 'owner']);
    [, $firstWorkspace] = platformManagedWorkspaceFixture('first-scope-platform');
    [, $secondWorkspace] = platformManagedWorkspaceFixture('second-scope-platform');
    Client::create(['workspace_id' => $firstWorkspace->id, 'name' => 'First tenant client']);
    Client::create(['workspace_id' => $secondWorkspace->id, 'name' => 'Second tenant client']);

    $workspaces = app(PlatformWorkspaceManagementService::class)
        ->paginatedWorkspaces(['status' => 'active', 'per_page' => 100])
        ->getCollection()
        ->keyBy('id');

    expect($workspaces[$firstWorkspace->id]->clients_count)->toBe(1)
        ->and($workspaces[$secondWorkspace->id]->clients_count)->toBe(1)
        ->and($workspaces[$firstWorkspace->id]->name)->not->toBe($workspaces[$secondWorkspace->id]->name)
        ->and($platformOwner->canManagePlatformUsers())->toBeTrue();
});
