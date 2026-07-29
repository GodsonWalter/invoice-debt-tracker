<?php

namespace App\Console\Commands;

use App\Services\WorkspaceLifecycleService;
use Illuminate\Console\Command;

class ProcessWorkspaceLifecycleCommand extends Command
{
    protected $signature = 'workspaces:lifecycle';

    protected $description = 'Send workspace lifecycle notifications and queue permanent cleanup';

    public function handle(WorkspaceLifecycleService $lifecycleService): int
    {
        $summary = $lifecycleService->processDeletedWorkspaces();

        $this->info('Queued '.$summary['notifications'].' lifecycle notifications and '.$summary['cleanup_jobs'].' cleanup job(s).');

        return self::SUCCESS;
    }
}
