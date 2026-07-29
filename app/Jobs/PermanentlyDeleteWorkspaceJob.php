<?php

namespace App\Jobs;

use App\Models\Workspace;
use App\Services\WorkspaceLifecycleService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PermanentlyDeleteWorkspaceJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 3600;

    public function __construct(public int $workspaceId) {}

    public function handle(WorkspaceLifecycleService $lifecycleService): void
    {
        $workspace = Workspace::onlyTrashed()->find($this->workspaceId);

        if ($workspace) {
            $lifecycleService->permanentlyDelete($workspace);
        }
    }

    public function uniqueId(): string
    {
        return (string) $this->workspaceId;
    }
}
