<?php

use App\Http\Middleware\EnsureWorkspaceIsActive;
use App\Http\Middleware\ResolveWorkspace;
use App\Jobs\PermanentlyDeleteWorkspaceJob;
use App\Jobs\SendWorkspaceLifecycleNotificationJob;
use App\Mail\WorkspaceLifecycleMail;
use App\Models\Client;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceLifecycleAudit;
use App\Models\WorkspaceLifecycleNotification;
use App\Services\WorkspaceLifecycleService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

afterEach(function (): void {
    Carbon::setTestNow();
});

/**
 * @return array{0: User, 1: Workspace}
 */
function createLifecycleWorkspaceFixture(string $globalRole = 'user', string $membershipRole = 'owner'): array
{
    $user = User::factory()->create(['role' => $globalRole]);
    $owner = $membershipRole === 'owner' ? $user : User::factory()->create();
    $suffix = fake()->unique()->numerify('lifecycle-####');
    $workspace = Workspace::create([
        'owner_id' => $owner->id,
        'name' => 'Lifecycle Workspace '.$suffix,
        'slug' => $suffix,
        'subdomain' => $suffix,
        'invoice_prefix' => 'LIF',
        'is_active' => true,
    ]);
    $workspace->users()->attach($user->id, ['role' => $membershipRole, 'is_active' => true]);

    return [$user, $workspace];
}

function deleteLifecycleWorkspace(Workspace $workspace, Carbon $deletedAt): void
{
    $currentTime = Carbon::now();
    Carbon::setTestNow($deletedAt);
    $workspace->delete();
    Carbon::setTestNow($currentTime);
}

function lifecycleBaseUrl(string $routeName, mixed $parameters = []): string
{
    return 'http://'.config('app.base_domain').route($routeName, $parameters, false);
}

test('an owner can restore their deleted workspace within the configured recovery period', function () {
    [$owner, $workspace] = createLifecycleWorkspaceFixture();
    $deletedAt = Carbon::now()->subDays(29);
    deleteLifecycleWorkspace($workspace, $deletedAt);

    $this->actingAs($owner)
        ->post(lifecycleBaseUrl('workspace.recovery.restore', $workspace->id))
        ->assertRedirect(lifecycleBaseUrl('workspace.recovery.index'));

    expect(Workspace::find($workspace->id))->not->toBeNull()
        ->and(WorkspaceLifecycleAudit::where('event', 'workspace_restored_by_workspace_owner')->exists())->toBeTrue();
});

test('an owner can restore on the final allowed recovery day but not after it expires', function () {
    [$owner, $workspace] = createLifecycleWorkspaceFixture();
    $deletedAt = Carbon::now()->subDays(30);
    deleteLifecycleWorkspace($workspace, $deletedAt);
    Carbon::setTestNow($deletedAt);

    $this->actingAs($owner)
        ->post(lifecycleBaseUrl('workspace.recovery.restore', $workspace->id))
        ->assertRedirect();

    expect(Workspace::find($workspace->id))->not->toBeNull();

    [$expiredOwner, $expiredWorkspace] = createLifecycleWorkspaceFixture();
    $expiredAt = Carbon::now()->subDays(31);
    deleteLifecycleWorkspace($expiredWorkspace, $expiredAt);

    $this->actingAs($expiredOwner)
        ->post(lifecycleBaseUrl('workspace.recovery.restore', $expiredWorkspace->id))
        ->assertRedirect()
        ->assertSessionHas('error', 'The self-service workspace recovery period has expired. Please contact platform support.');

    expect(Workspace::withTrashed()->find($expiredWorkspace->id)->trashed())->toBeTrue()
        ->and(WorkspaceLifecycleAudit::where('event', 'workspace_owner_restore_rejected_expired')->exists())->toBeTrue();
});

test('only the original workspace owner can use the recovery center', function () {
    [$owner, $workspace] = createLifecycleWorkspaceFixture();
    [, $otherWorkspace] = createLifecycleWorkspaceFixture();
    deleteLifecycleWorkspace($workspace, Carbon::now()->subDays(2));

    $this->actingAs($owner)
        ->get(lifecycleBaseUrl('workspace.recovery.index'))
        ->assertOk()
        ->assertSee($workspace->name)
        ->assertDontSee($otherWorkspace->name);

    $otherUser = User::factory()->create();
    $this->actingAs($otherUser)
        ->get(lifecycleBaseUrl('workspace.recovery.index'))
        ->assertOk()
        ->assertDontSee($workspace->name);

    $this->actingAs($otherUser)
        ->post(lifecycleBaseUrl('workspace.recovery.restore', $workspace->id))
        ->assertNotFound();
});

test('the global platform owner can exceptionally restore expired recovery with a reason', function () {
    [$workspaceOwner, $workspace] = createLifecycleWorkspaceFixture();
    [$platformOwner] = createLifecycleWorkspaceFixture('owner');
    deleteLifecycleWorkspace($workspace, Carbon::now()->subDays(31));

    $this->actingAs($platformOwner)
        ->post(lifecycleBaseUrl('platform.recovery.restore', $workspace->id), [])
        ->assertSessionHasErrors('reason');

    $this->actingAs($platformOwner)
        ->post(lifecycleBaseUrl('platform.recovery.restore', $workspace->id), ['reason' => 'Customer support approved exceptional recovery.'])
        ->assertRedirect(lifecycleBaseUrl('platform.recovery.index'));

    $audit = WorkspaceLifecycleAudit::where('event', 'workspace_restored_by_platform_owner')->latest()->first();
    expect($audit)->not->toBeNull()
        ->and($audit->actor_user_id)->toBe($platformOwner->id)
        ->and($audit->actor_global_role)->toBe('owner')
        ->and($audit->actor_type)->toBe('platform_owner')
        ->and($audit->reason)->toBe('Customer support approved exceptional recovery.')
        ->and($workspaceOwner->id)->not->toBe($platformOwner->id);
});

test('platform recovery is limited to the global owner role and requires a reason', function (string $role) {
    [, $workspace] = createLifecycleWorkspaceFixture();
    [$actor] = createLifecycleWorkspaceFixture($role);
    deleteLifecycleWorkspace($workspace, Carbon::now()->subDays(31));

    $this->actingAs($actor)
        ->get(lifecycleBaseUrl('platform.recovery.index'))
        ->assertForbidden();
})->with(['admin', 'manager', 'staff', 'user']);

test('workspace pivot ownership does not grant platform recovery access', function () {
    [, $workspace] = createLifecycleWorkspaceFixture();
    [$workspaceOwner] = createLifecycleWorkspaceFixture('user');
    $workspace->users()->attach($workspaceOwner->id, ['role' => 'owner', 'is_active' => true]);
    deleteLifecycleWorkspace($workspace, Carbon::now()->subDays(31));

    $this->actingAs($workspaceOwner)
        ->get(lifecycleBaseUrl('platform.recovery.index'))
        ->assertForbidden();
});

test('the recovery status summary uses configured deadlines', function () {
    [, $workspace] = createLifecycleWorkspaceFixture();
    deleteLifecycleWorkspace($workspace, Carbon::now()->subDays(90));

    $summary = app(WorkspaceLifecycleService::class)->summary($workspace);

    expect($summary['state'])->toBe(WorkspaceLifecycleService::STATE_PENDING_PERMANENT_DELETION)
        ->and($summary['restore_days_remaining'])->toBe(0)
        ->and($summary['permanent_deletion_days_remaining'])->toBe(30);
});

test('lifecycle processing queues warnings once and queues cleanup at the retention boundary', function () {
    Queue::fake();
    [$owner, $workspace] = createLifecycleWorkspaceFixture();
    deleteLifecycleWorkspace($workspace, Carbon::now()->subDays(120));

    $service = app(WorkspaceLifecycleService::class);
    $service->processDeletedWorkspaces();
    $service->processDeletedWorkspaces();

    Queue::assertPushed(PermanentlyDeleteWorkspaceJob::class, 1);
    expect(WorkspaceLifecycleNotification::query()->where('workspace_id', $workspace->id)->count())->toBe(1)
        ->and($owner->exists)->toBeTrue();
});

test('permanent cleanup removes workspace data but preserves users and lifecycle audit records', function () {
    [$owner, $workspace] = createLifecycleWorkspaceFixture();
    $client = Client::create(['workspace_id' => $workspace->id, 'name' => 'Retained until cleanup']);
    $deletedAt = Carbon::now()->subDays(120);
    deleteLifecycleWorkspace($workspace, $deletedAt);

    app(WorkspaceLifecycleService::class)->permanentlyDelete($workspace);

    expect(Workspace::withTrashed()->find($workspace->id))->toBeNull()
        ->and(Client::find($client->id))->toBeNull()
        ->and(User::find($owner->id))->not->toBeNull()
        ->and($owner->workspaces()->whereKey($workspace->id)->exists())->toBeFalse()
        ->and(WorkspaceLifecycleAudit::where('workspace_id', $workspace->id)
            ->where('event', 'workspace_permanent_deletion_completed')->exists())->toBeTrue();
});

test('lifecycle notification delivery is queued, deduplicated, and audited', function () {
    Mail::fake();
    [$owner, $workspace] = createLifecycleWorkspaceFixture();
    deleteLifecycleWorkspace($workspace, Carbon::now()->subDays(2));

    $service = app(WorkspaceLifecycleService::class);
    $notification = $service->queueNotification($workspace, WorkspaceLifecycleService::NOTIFICATION_DELETED);
    $sameNotification = $service->queueNotification($workspace, WorkspaceLifecycleService::NOTIFICATION_DELETED);

    expect($notification->id)->toBe($sameNotification->id);
    (new SendWorkspaceLifecycleNotificationJob($notification->id))->handle($service);

    Mail::assertSent(WorkspaceLifecycleMail::class, fn ($mail): bool => $mail->hasTo($owner->email));
    expect($notification->fresh()->status)->toBe(WorkspaceLifecycleNotification::STATUS_SENT)
        ->and(WorkspaceLifecycleAudit::where('event', 'workspace_deletion_notification_sent')->exists())->toBeTrue();
});

test('recovery and platform links respect global roles when no workspace is active', function () {
    [$owner, $workspace] = createLifecycleWorkspaceFixture();
    deleteLifecycleWorkspace($workspace, Carbon::now()->subDays(2));

    $this->withoutMiddleware([EnsureWorkspaceIsActive::class, ResolveWorkspace::class])
        ->actingAs($owner)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Recovery Center')
        ->assertDontSee('Platform Management');

    [$platformOwner] = createLifecycleWorkspaceFixture('owner');
    $this->withoutMiddleware([EnsureWorkspaceIsActive::class, ResolveWorkspace::class])
        ->actingAs($platformOwner)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Platform Management')
        ->assertSee('Lifecycle Audit');
});
