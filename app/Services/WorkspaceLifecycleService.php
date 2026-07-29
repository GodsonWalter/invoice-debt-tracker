<?php

namespace App\Services;

use App\Exceptions\WorkspaceRecoveryExpiredException;
use App\Jobs\PermanentlyDeleteWorkspaceJob;
use App\Jobs\SendWorkspaceLifecycleNotificationJob;
use App\Models\BusinessProfile;
use App\Models\Client;
use App\Models\EmailTemplate;
use App\Models\Invoice;
use App\Models\InvoiceEmailLog;
use App\Models\ReminderLog;
use App\Models\ReminderSchedule;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceLifecycleAudit;
use App\Models\WorkspaceLifecycleNotification;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class WorkspaceLifecycleService
{
    public const STATE_RECOVERABLE = 'recoverable';

    public const STATE_RESTORE_EXPIRED = 'restore_expired';

    public const STATE_PENDING_PERMANENT_DELETION = 'pending_permanent_deletion';

    public const STATE_PERMANENT_DELETION_DUE = 'permanent_deletion_due';

    public const NOTIFICATION_DELETED = 'deletion_requested';

    public const NOTIFICATION_OWNER_RESTORE = 'owner_restored';

    public const NOTIFICATION_PLATFORM_RESTORE = 'platform_restored';

    public const NOTIFICATION_OWNER_RESTORE_WARNING = 'owner_restore_warning';

    public const NOTIFICATION_OWNER_RESTORE_EXPIRED = 'owner_restore_expired';

    public const NOTIFICATION_PERMANENT_WARNING = 'permanent_deletion_warning';

    public const NOTIFICATION_PERMANENT_DELETED = 'permanently_deleted';

    public function summary(Workspace $workspace, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $deletedAt = CarbonImmutable::instance($workspace->deleted_at);
        $restoreDeadline = $deletedAt->addDays((int) config('workspace-lifecycle.self_service_restore_days'));
        $permanentDeletionDeadline = $deletedAt->addDays((int) config('workspace-lifecycle.permanent_deletion_days'));
        $permanentWarningWindow = max(config('workspace-lifecycle.permanent_deletion_warning_days', [30]));

        $state = match (true) {
            $now->gte($permanentDeletionDeadline) => self::STATE_PERMANENT_DELETION_DUE,
            $now->lte($restoreDeadline) => self::STATE_RECOVERABLE,
            $now->gte($permanentDeletionDeadline->subDays($permanentWarningWindow)) => self::STATE_PENDING_PERMANENT_DELETION,
            default => self::STATE_RESTORE_EXPIRED,
        };

        return [
            'state' => $state,
            'deleted_at' => $deletedAt,
            'restore_deadline' => $restoreDeadline,
            'permanent_deletion_deadline' => $permanentDeletionDeadline,
            'restore_days_remaining' => $this->daysRemaining($now, $restoreDeadline),
            'permanent_deletion_days_remaining' => $this->daysRemaining($now, $permanentDeletionDeadline),
            'owner_can_restore' => $state === self::STATE_RECOVERABLE,
        ];
    }

    public function softDelete(Workspace $workspace, User $actor): void
    {
        DB::transaction(function () use ($workspace, $actor): void {
            $lockedWorkspace = Workspace::query()->lockForUpdate()->findOrFail($workspace->id);

            if ($lockedWorkspace->trashed()) {
                return;
            }

            $lockedWorkspace->delete();
            $this->recordAudit($lockedWorkspace, 'workspace_deletion_requested', $actor, 'workspace_owner', null);
            $this->queueNotification($lockedWorkspace, self::NOTIFICATION_DELETED);
        });
    }

    public function restoreAsOwner(Workspace $workspace, User $actor): Workspace
    {
        try {
            return DB::transaction(function () use ($workspace, $actor): Workspace {
                $lockedWorkspace = Workspace::onlyTrashed()->lockForUpdate()->findOrFail($workspace->id);

                if ((int) $lockedWorkspace->owner_id !== (int) $actor->id) {
                    throw new AuthorizationException('Only the original workspace owner can restore this workspace.');
                }

                $lifecycle = $this->summary($lockedWorkspace);
                if (! $lifecycle['owner_can_restore']) {
                    throw new WorkspaceRecoveryExpiredException('The self-service workspace recovery period has expired. Please contact platform support.');
                }

                $this->assertIdentifiersAvailable($lockedWorkspace);
                $lockedWorkspace->restore();
                $this->recordAudit($lockedWorkspace, 'workspace_restored_by_workspace_owner', $actor, 'workspace_owner', null);
                $this->queueNotification($lockedWorkspace, self::NOTIFICATION_OWNER_RESTORE);

                return $lockedWorkspace->fresh();
            });
        } catch (WorkspaceRecoveryExpiredException $exception) {
            $this->recordAuditSnapshot(
                $workspace,
                'workspace_owner_restore_rejected_expired',
                $actor,
                'workspace_owner',
                $exception->getMessage(),
            );

            throw $exception;
        }
    }

    public function restoreAsPlatformOwner(Workspace $workspace, User $actor, string $reason): Workspace
    {
        if (! $actor->isPlatformOwner()) {
            throw new AuthorizationException('Only the global platform owner can perform exceptional recovery.');
        }

        return DB::transaction(function () use ($workspace, $actor, $reason): Workspace {
            $lockedWorkspace = Workspace::onlyTrashed()->lockForUpdate()->findOrFail($workspace->id);
            $lifecycle = $this->summary($lockedWorkspace);

            if (CarbonImmutable::now()->gte($lifecycle['permanent_deletion_deadline'])) {
                throw new AuthorizationException('This workspace is already eligible for permanent deletion and cannot be restored.');
            }

            $this->assertIdentifiersAvailable($lockedWorkspace);
            $lockedWorkspace->restore();
            $this->recordAudit($lockedWorkspace, 'workspace_restored_by_platform_owner', $actor, 'platform_owner', $reason);
            $this->queueNotification($lockedWorkspace, self::NOTIFICATION_PLATFORM_RESTORE, ['reason' => $reason]);

            return $lockedWorkspace->fresh();
        });
    }

    public function processDeletedWorkspaces(): array
    {
        $now = CarbonImmutable::now();
        $summary = ['notifications' => 0, 'cleanup_jobs' => 0];

        Workspace::onlyTrashed()->orderBy('id')->chunkById((int) config('workspace-lifecycle.cleanup_batch_size', 100), function ($workspaces) use ($now, &$summary): void {
            foreach ($workspaces as $workspace) {
                $lifecycle = $this->summary($workspace, $now);

                foreach (config('workspace-lifecycle.owner_restore_warning_days', []) as $days) {
                    if ($this->isNotificationDay($lifecycle['restore_deadline'], (int) $days, $now)) {
                        $this->queueNotification($workspace, self::NOTIFICATION_OWNER_RESTORE_WARNING, ['days' => (int) $days]);
                        $summary['notifications']++;
                    }
                }

                if ($now->gt($lifecycle['restore_deadline'])) {
                    $this->queueNotification($workspace, self::NOTIFICATION_OWNER_RESTORE_EXPIRED);
                    $summary['notifications']++;
                }

                foreach (config('workspace-lifecycle.permanent_deletion_warning_days', []) as $days) {
                    if ($this->isNotificationDay($lifecycle['permanent_deletion_deadline'], (int) $days, $now)) {
                        $this->queueNotification($workspace, self::NOTIFICATION_PERMANENT_WARNING, ['days' => (int) $days]);
                        $summary['notifications']++;
                    }
                }

                if ($now->gte($lifecycle['permanent_deletion_deadline'])) {
                    PermanentlyDeleteWorkspaceJob::dispatch($workspace->id);
                    $summary['cleanup_jobs']++;
                }
            }
        });

        return $summary;
    }

    public function permanentlyDelete(Workspace $workspace): void
    {
        $snapshot = $this->snapshot($workspace);
        $this->recordAuditSnapshot($workspace, 'workspace_permanent_deletion_started', null, 'system', null);

        try {
            DB::transaction(function () use ($workspace): void {
                $lockedWorkspace = Workspace::onlyTrashed()->lockForUpdate()->find($workspace->id);

                if (! $lockedWorkspace || ! $this->isPermanentlyDue($lockedWorkspace)) {
                    return;
                }

                $this->deleteWorkspaceData($lockedWorkspace);
                $this->queueNotification($lockedWorkspace, self::NOTIFICATION_PERMANENT_DELETED);
                $lockedWorkspace->forceDelete();
            });

            $this->recordAuditSnapshot($snapshot, 'workspace_permanent_deletion_completed', null, 'system', null);
        } catch (Throwable $exception) {
            $this->recordAuditSnapshot($snapshot, 'workspace_permanent_deletion_failed', null, 'system', $exception->getMessage(), [
                'exception' => $exception::class,
            ]);

            throw $exception;
        }
    }

    public function queueNotification(Workspace $workspace, string $type, array $metadata = []): ?WorkspaceLifecycleNotification
    {
        $owner = $workspace->owner()->first();

        if (! $owner?->email) {
            return null;
        }

        $lifecycleMetadata = $metadata;
        if ($workspace->trashed()) {
            $details = $this->summary($workspace);
            $lifecycleMetadata = array_merge([
                'deleted_at' => $details['deleted_at']->toDateTimeString(),
                'restore_deadline' => $details['restore_deadline']->toDateTimeString(),
                'permanent_deletion_deadline' => $details['permanent_deletion_deadline']->toDateTimeString(),
            ], $metadata);
        } else {
            $deletionAudit = WorkspaceLifecycleAudit::query()
                ->where('workspace_id', $workspace->id)
                ->where('event', 'workspace_deletion_requested')
                ->latest('id')
                ->first();

            if ($deletionAudit) {
                $lifecycleMetadata = array_merge([
                    'deleted_at' => $deletionAudit->deleted_at?->toDateTimeString(),
                    'restore_deadline' => $deletionAudit->restore_deadline?->toDateTimeString(),
                    'permanent_deletion_deadline' => $deletionAudit->permanent_deletion_deadline?->toDateTimeString(),
                ], $metadata);
            }
        }

        $dedupeKey = $workspace->id.':'.$type.':'.($metadata['days'] ?? 'initial');
        $notification = WorkspaceLifecycleNotification::query()->firstOrCreate(
            ['dedupe_key' => $dedupeKey],
            [
                'workspace_id' => $workspace->id,
                'workspace_name' => $workspace->name,
                'workspace_owner_id' => $workspace->owner_id,
                'recipient_email' => $owner->email,
                'notification_type' => $type,
                'status' => WorkspaceLifecycleNotification::STATUS_PENDING,
                'scheduled_for' => CarbonImmutable::now(),
                'metadata' => $lifecycleMetadata,
            ],
        );

        if ($notification->wasRecentlyCreated) {
            SendWorkspaceLifecycleNotificationJob::dispatch($notification->id)
                ->onConnection(config('workspace-lifecycle.queue_connection'))
                ->afterCommit();
        }

        return $notification;
    }

    public function recordAudit(Workspace $workspace, string $event, ?User $actor, string $actorType, ?string $reason, array $metadata = []): WorkspaceLifecycleAudit
    {
        return $this->recordAuditSnapshot($workspace, $event, $actor, $actorType, $reason, $metadata);
    }

    /**
     * @param  Workspace|array<string, mixed>  $workspace
     */
    public function recordAuditSnapshot(Workspace|array $workspace, string $event, ?User $actor, string $actorType, ?string $reason, array $metadata = []): WorkspaceLifecycleAudit
    {
        $snapshot = $workspace instanceof Workspace ? $this->snapshot($workspace) : $workspace;
        $deletedAt = $snapshot['deleted_at'] ? CarbonImmutable::parse($snapshot['deleted_at']) : null;

        return WorkspaceLifecycleAudit::query()->create([
            'workspace_id' => $snapshot['workspace_id'],
            'workspace_name' => $snapshot['workspace_name'],
            'workspace_owner_id' => $snapshot['workspace_owner_id'],
            'actor_user_id' => $actor?->id,
            'actor_global_role' => $actor?->role,
            'actor_type' => $actorType,
            'event' => $event,
            'reason' => $reason,
            'metadata' => $metadata,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'deleted_at' => $deletedAt,
            'restore_deadline' => $deletedAt?->addDays((int) config('workspace-lifecycle.self_service_restore_days')),
            'permanent_deletion_deadline' => $deletedAt?->addDays((int) config('workspace-lifecycle.permanent_deletion_days')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(Workspace $workspace): array
    {
        return [
            'workspace_id' => $workspace->id,
            'workspace_name' => $workspace->name,
            'workspace_owner_id' => $workspace->owner_id,
            'deleted_at' => $workspace->deleted_at?->toDateTimeString(),
        ];
    }

    private function assertIdentifiersAvailable(Workspace $workspace): void
    {
        $duplicateSlug = Workspace::query()
            ->where('slug', $workspace->slug)
            ->where('id', '!=', $workspace->id)
            ->exists();
        $duplicateSubdomain = $workspace->subdomain && Workspace::query()
            ->where('subdomain', $workspace->subdomain)
            ->where('id', '!=', $workspace->id)
            ->exists();

        if ($duplicateSlug || $duplicateSubdomain) {
            throw new AuthorizationException('This workspace cannot be restored because one of its unique identifiers is already in use.');
        }
    }

    private function isPermanentlyDue(Workspace $workspace): bool
    {
        return CarbonImmutable::now()->gte($this->summary($workspace)['permanent_deletion_deadline']);
    }

    private function isNotificationDay(CarbonImmutable $deadline, int $daysBefore, CarbonImmutable $now): bool
    {
        return $deadline->subDays($daysBefore)->isSameDay($now);
    }

    private function daysRemaining(CarbonImmutable $now, CarbonImmutable $deadline): int
    {
        return max(0, (int) ceil($now->diffInSeconds($deadline, false) / 86400));
    }

    private function deleteWorkspaceData(Workspace $workspace): void
    {
        $invoiceIds = Invoice::query()->where('workspace_id', $workspace->id)->pluck('id');

        ReminderLog::query()->where('workspace_id', $workspace->id)->delete();
        InvoiceEmailLog::query()->where('workspace_id', $workspace->id)->delete();
        DB::table('payments')->where('workspace_id', $workspace->id)->delete();
        DB::table('invoice_items')->whereIn('invoice_id', $invoiceIds)->delete();
        Invoice::query()->where('workspace_id', $workspace->id)->delete();
        ReminderSchedule::query()->where('workspace_id', $workspace->id)->delete();
        EmailTemplate::query()->where('workspace_id', $workspace->id)->delete();
        Client::query()->where('workspace_id', $workspace->id)->delete();
        DB::table('ai_queries')->where('workspace_id', $workspace->id)->delete();
        DB::table('activity_logs')->where('workspace_id', $workspace->id)->delete();

        $reportExportPaths = DB::table('report_exports')
            ->where('workspace_id', $workspace->id)
            ->whereNotNull('path')
            ->pluck('path');
        foreach ($reportExportPaths as $path) {
            Storage::disk((string) config('reports.export_disk', 'local'))->delete($path);
        }
        DB::table('report_exports')->where('workspace_id', $workspace->id)->delete();

        $profile = BusinessProfile::query()->where('workspace_id', $workspace->id)->first();
        if ($profile?->logo) {
            Storage::disk('public')->delete($profile->logo);
        }
        BusinessProfile::query()->where('workspace_id', $workspace->id)->delete();
        DB::table('workspace_user')->where('workspace_id', $workspace->id)->delete();
    }
}
