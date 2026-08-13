<?php

namespace Database\Seeders;

use App\Models\Workspace;
use App\Services\WorkspaceDefaultsService;
use Illuminate\Database\Seeder;

class ReminderScheduleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $workspaceDefaultsService = app(WorkspaceDefaultsService::class);

        Workspace::query()->each(function (Workspace $workspace) use ($workspaceDefaultsService): void {
            $workspaceDefaultsService->provisionReminderSchedules($workspace);
        });
    }
}
