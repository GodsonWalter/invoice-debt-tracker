<?php

namespace App\Jobs;

use App\Data\ReportFilters;
use App\Models\ReportExport;
use App\Models\User;
use App\Models\Workspace;
use App\Report\ReportExportService;
use App\ReportType;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GenerateReportExport implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 10;

    public int $timeout = 120;

    public function __construct(public int $reportExportId) {}

    public function handle(ReportExportService $exportService): void
    {
        $export = ReportExport::query()->find($this->reportExportId);

        if (! $export || $export->status === ReportExport::STATUS_COMPLETED) {
            return;
        }

        $workspace = Workspace::query()->find($export->workspace_id);
        if (! $workspace || $workspace->trashed()) {
            $this->failExport($export, 'The workspace is no longer available.');

            return;
        }

        $actor = User::query()->find($export->user_id);
        if (! $actor || ! $workspace->canBeManagedBy($actor)) {
            $this->failExport($export, 'The export requester is no longer authorized for this workspace.');

            return;
        }

        $export->forceFill(['status' => ReportExport::STATUS_PENDING, 'error_message' => null])->save();
        $type = ReportType::tryFrom($export->report_type);
        if (! $type) {
            $this->failExport($export, 'The report type is invalid.');

            return;
        }

        try {
            $filters = ReportFilters::fromQuery($type, $export->filters ?? []);
            $path = $export->path ?: 'reports/'.$workspace->id.'/'.$export->id.'.'.$export->format;
            $exportService->store($workspace, $filters, $path);
            $export->forceFill([
                'status' => ReportExport::STATUS_COMPLETED,
                'path' => $path,
                'completed_at' => now(),
                'failed_at' => null,
                'error_message' => null,
            ])->save();
        } catch (Throwable $exception) {
            $this->failExport($export, $exception->getMessage());

            throw $exception;
        }
    }

    public function failed(Throwable $exception): void
    {
        $export = ReportExport::query()->find($this->reportExportId);

        if ($export) {
            $this->failExport($export, $exception->getMessage());
        }
    }

    private function failExport(ReportExport $export, string $message): void
    {
        if ($export->path) {
            Storage::disk((string) config('reports.export_disk', 'local'))->delete($export->path);
        }

        $export->forceFill([
            'status' => ReportExport::STATUS_FAILED,
            'failed_at' => now(),
            'error_message' => mb_substr($message, 0, 2000),
        ])->save();
    }
}
