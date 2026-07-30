<?php

namespace App\Jobs;

use App\Mail\WorkspaceLifecycleMail;
use App\Models\Workspace;
use App\Models\WorkspaceLifecycleAudit;
use App\Models\WorkspaceLifecycleNotification;
use App\Services\WorkspaceLifecycleService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendWorkspaceLifecycleNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 10;

    public int $timeout = 120;

    public function __construct(public int $notificationId) {}

    public function handle(WorkspaceLifecycleService $lifecycleService): void
    {
        $notification = WorkspaceLifecycleNotification::query()->findOrFail($this->notificationId);

        if ($notification->status === WorkspaceLifecycleNotification::STATUS_SENT) {
            return;
        }

        $notification->increment('attempts');

        try {
            $mail = new WorkspaceLifecycleMail($notification);
            Mail::to($notification->recipient_email)->send($mail);
            $notification->forceFill([
                'status' => WorkspaceLifecycleNotification::STATUS_SENT,
                'sent_at' => now(),
                'failed_at' => null,
                'error_message' => null,
            ])->save();

            $workspace = $notification->workspace_id
                ? Workspace::withTrashed()->find($notification->workspace_id)
                : null;
            $event = match (true) {
                $notification->notification_type === WorkspaceLifecycleService::NOTIFICATION_DELETED => 'workspace_deletion_notification_sent',
                in_array($notification->notification_type, [
                    WorkspaceLifecycleService::NOTIFICATION_OWNER_RESTORE,
                    WorkspaceLifecycleService::NOTIFICATION_PLATFORM_RESTORE,
                ], true) => 'workspace_restore_notification_sent',
                default => 'workspace_retention_warning_sent',
            };

            if ($workspace) {
                $lifecycleService->recordAudit($workspace, $event, null, 'system', null, [
                    'notification_type' => $notification->notification_type,
                    'notification_id' => $notification->id,
                ]);
            } else {
                WorkspaceLifecycleAudit::query()->create([
                    'workspace_id' => $notification->workspace_id,
                    'workspace_name' => $notification->workspace_name,
                    'workspace_owner_id' => $notification->workspace_owner_id,
                    'actor_type' => 'system',
                    'event' => $event,
                    'metadata' => [
                        'notification_type' => $notification->notification_type,
                        'notification_id' => $notification->id,
                    ],
                    'deleted_at' => $notification->metadata['deleted_at'] ?? null,
                    'restore_deadline' => $notification->metadata['restore_deadline'] ?? null,
                    'permanent_deletion_deadline' => $notification->metadata['permanent_deletion_deadline'] ?? null,
                ]);
            }
        } catch (Throwable $exception) {
            $notification->forceFill([
                'status' => WorkspaceLifecycleNotification::STATUS_FAILED,
                'failed_at' => now(),
                'error_message' => $exception->getMessage(),
            ])->save();

            throw $exception;
        }
    }
}
