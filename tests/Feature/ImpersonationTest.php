<?php

use App\Models\ImpersonationAudit;
use App\Models\User;
use App\Models\Workspace;
use App\Services\ImpersonationService;

/**
 * @return array{actor: User, workspaceOwner: User, workspace: Workspace}
 */
function createImpersonationFixture(string $key = 'impersonation', string $actorRole = 'owner'): array
{
    $actor = User::factory()->create(['role' => $actorRole]);
    $workspaceOwner = User::factory()->create(['role' => 'user']);
    $workspace = Workspace::create([
        'owner_id' => $workspaceOwner->id,
        'name' => ucfirst($key).' workspace',
        'slug' => $key.'-'.fake()->unique()->numerify('####'),
        'subdomain' => $key.'-'.fake()->unique()->numerify('####'),
        'is_active' => true,
    ]);
    $workspace->users()->attach($workspaceOwner->id, [
        'role' => 'owner',
        'is_active' => true,
    ]);

    return compact('actor', 'workspaceOwner', 'workspace');
}

function impersonationBaseUrl(string $routeName, mixed $parameters = []): string
{
    return route($routeName, $parameters);
}

function impersonationWorkspaceUrl(Workspace $workspace, string $routeName, mixed $parameters = []): string
{
    $routeParameters = $parameters === null
        ? []
        : ($parameters === [] ? $workspace : [$workspace, ...((array) $parameters)]);
    $path = parse_url(route($routeName, $routeParameters), PHP_URL_PATH) ?: '/';

    return 'http://'.$workspace->subdomain.'.'.trim((string) config('app.base_domain'), '"')
        .$path;
}

test('only platform owners and admins can see and initiate impersonation', function (string $role): void {
    ['actor' => $actor, 'workspaceOwner' => $workspaceOwner, 'workspace' => $workspace] = createImpersonationFixture($role.'-impersonation', $role);

    $this->actingAs($actor)
        ->get(impersonationBaseUrl('platform.users.index'))
        ->assertOk()
        ->assertSee('Impersonate');

    $this->actingAs($actor)
        ->post(impersonationBaseUrl('platform.users.impersonate', [$workspaceOwner, $workspace]))
        ->assertRedirect(impersonationWorkspaceUrl($workspace, 'workspace.dashboard'));
})->with(['owner', 'admin']);

test('platform managers staff and workspace users cannot initiate impersonation', function (string $role): void {
    ['actor' => $actor, 'workspaceOwner' => $workspaceOwner, 'workspace' => $workspace] = createImpersonationFixture($role.'-blocked', $role);

    $response = $this->actingAs($actor)
        ->post(impersonationBaseUrl('platform.users.impersonate', [$workspaceOwner, $workspace]));

    $response->assertForbidden();
    expect(ImpersonationAudit::query()->count())->toBe(0);

    $listingResponse = $this->actingAs($actor)
        ->get(impersonationBaseUrl('platform.users.index'));

    if ($role === 'user') {
        $listingResponse->assertForbidden();
    } else {
        $listingResponse->assertOk()->assertDontSee('Impersonate');
    }
})->with(['manager', 'staff', 'user']);

test('impersonation grants workspace-owner access without changing either user record', function (): void {
    ['actor' => $actor, 'workspaceOwner' => $workspaceOwner, 'workspace' => $workspace] = createImpersonationFixture();

    $this->actingAs($actor)
        ->post(impersonationBaseUrl('platform.users.impersonate', [$workspaceOwner, $workspace]))
        ->assertRedirect();

    $this->actingAs($actor)
        ->get(impersonationWorkspaceUrl($workspace, 'workspace.dashboard'))
        ->assertOk()
        ->assertSee('Workspace overview')
        ->assertSee('Impersonation Mode')
        ->assertSee('Exit Impersonation');

    $this->actingAs($actor)
        ->get(impersonationWorkspaceUrl($workspace, 'business-profile.index', null))
        ->assertOk();

    expect(User::query()->findOrFail($actor->id)->role)->toBe('owner')
        ->and(User::query()->findOrFail($workspaceOwner->id)->role)->toBe('user')
        ->and(session(ImpersonationService::SESSION_KEY)['impersonator_id'])->toBe($actor->id)
        ->and(session(ImpersonationService::SESSION_KEY)['impersonated_user_id'])->toBe($workspaceOwner->id);
});

test('impersonation is isolated to the selected workspace and cannot reach platform routes', function (): void {
    ['actor' => $actor, 'workspaceOwner' => $workspaceOwner, 'workspace' => $workspace] = createImpersonationFixture('isolated');
    $otherOwner = User::factory()->create();
    $otherWorkspace = Workspace::create([
        'owner_id' => $otherOwner->id,
        'name' => 'Other workspace',
        'slug' => 'other-'.fake()->unique()->numerify('####'),
        'subdomain' => 'other-'.fake()->unique()->numerify('####'),
        'is_active' => true,
    ]);
    $otherWorkspace->users()->attach($otherOwner->id, ['role' => 'owner', 'is_active' => true]);

    $this->actingAs($actor)
        ->post(impersonationBaseUrl('platform.users.impersonate', [$workspaceOwner, $workspace]))
        ->assertRedirect();

    $this->actingAs($actor)
        ->get(impersonationWorkspaceUrl($otherWorkspace, 'workspace.dashboard'))
        ->assertRedirect(impersonationWorkspaceUrl($workspace, 'workspace.dashboard'));

    $this->actingAs($actor)
        ->get(impersonationBaseUrl('platform.users.index'))
        ->assertRedirect(impersonationWorkspaceUrl($workspace, 'workspace.dashboard'));
});

test('important workspace actions are audited and exit closes the impersonation session', function (): void {
    ['actor' => $actor, 'workspaceOwner' => $workspaceOwner, 'workspace' => $workspace] = createImpersonationFixture('audited');

    $this->actingAs($actor)
        ->post(impersonationBaseUrl('platform.users.impersonate', [$workspaceOwner, $workspace]))
        ->assertRedirect();

    $this->actingAs($actor)
        ->post(impersonationWorkspaceUrl($workspace, 'clients.store'), [
            'name' => 'Audited client',
            'email' => 'audited-client@example.test',
        ])
        ->assertRedirect();

    $startedAudit = ImpersonationAudit::query()
        ->where('event', ImpersonationService::EVENT_STARTED)
        ->firstOrFail();
    $actionAudit = ImpersonationAudit::query()
        ->where('event', ImpersonationService::EVENT_ACTION)
        ->where('action', 'clients.store')
        ->firstOrFail();

    expect($startedAudit->impersonator_user_id)->toBe($actor->id)
        ->and($startedAudit->impersonated_user_id)->toBe($workspaceOwner->id)
        ->and($startedAudit->workspace_id)->toBe($workspace->id)
        ->and($startedAudit->started_at)->not->toBeNull()
        ->and($actionAudit->status_code)->toBe(302);

    $this->actingAs($actor)
        ->post(impersonationWorkspaceUrl($workspace, 'impersonation.exit', null))
        ->assertRedirect(impersonationBaseUrl('platform.users.index'));

    $endedAudit = ImpersonationAudit::query()
        ->where('event', ImpersonationService::EVENT_ENDED)
        ->firstOrFail();

    expect($endedAudit->impersonator_user_id)->toBe($actor->id)
        ->and($endedAudit->impersonated_user_id)->toBe($workspaceOwner->id)
        ->and($endedAudit->workspace_id)->toBe($workspace->id)
        ->and($endedAudit->started_at)->not->toBeNull()
        ->and($endedAudit->ended_at)->not->toBeNull()
        ->and($endedAudit->action)->toBe('impersonation.exit')
        ->and(session(ImpersonationService::SESSION_KEY))->toBeNull();

    $this->actingAs($actor)
        ->get(impersonationWorkspaceUrl($workspace, 'workspace.dashboard'))
        ->assertRedirect();
});

test('impersonation sessions cannot be chained or escalated', function (): void {
    ['actor' => $actor, 'workspaceOwner' => $workspaceOwner, 'workspace' => $workspace] = createImpersonationFixture('chained');
    $secondOwner = User::factory()->create(['role' => 'admin']);
    $secondWorkspace = Workspace::create([
        'owner_id' => $secondOwner->id,
        'name' => 'Second workspace',
        'slug' => 'second-'.fake()->unique()->numerify('####'),
        'subdomain' => 'second-'.fake()->unique()->numerify('####'),
        'is_active' => true,
    ]);
    $secondWorkspace->users()->attach($secondOwner->id, ['role' => 'owner', 'is_active' => true]);

    $this->actingAs($actor)
        ->post(impersonationBaseUrl('platform.users.impersonate', [$workspaceOwner, $workspace]))
        ->assertRedirect();

    $this->actingAs($actor)
        ->post(impersonationBaseUrl('platform.users.impersonate', [$secondOwner, $secondWorkspace]))
        ->assertRedirect(impersonationWorkspaceUrl($workspace, 'workspace.dashboard'));

    expect(ImpersonationAudit::query()->where('event', ImpersonationService::EVENT_STARTED)->count())->toBe(1);
});
