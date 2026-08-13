<?php

namespace Database\Seeders;

use App\Models\Workspace;
use App\Services\WorkspaceDefaultsService;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $workspaceDefaultsService = app(WorkspaceDefaultsService::class);

        Workspace::query()->each(function (Workspace $workspace) use ($workspaceDefaultsService): void {
            $workspaceDefaultsService->provisionEmailTemplates($workspace);
        });
    }
}
