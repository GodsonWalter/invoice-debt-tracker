<?php

namespace App\Console\Commands;

use App\Models\ReportExport;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

#[Signature('reports:cleanup-exports')]
#[Description('Remove expired report export files and records.')]
class CleanupReportExports extends Command
{
    public function handle(): int
    {
        $retentionDays = max(1, (int) config('reports.export_retention_days', 7));
        $cutoff = Carbon::now()->subDays($retentionDays);
        $deleted = 0;
        $disk = Storage::disk((string) config('reports.export_disk', 'local'));

        ReportExport::query()
            ->whereIn('status', [ReportExport::STATUS_COMPLETED, ReportExport::STATUS_FAILED])
            ->where(function (Builder $query) use ($cutoff): void {
                $query
                    ->where('completed_at', '<', $cutoff)
                    ->orWhere('failed_at', '<', $cutoff)
                    ->orWhere(function (Builder $query) use ($cutoff): void {
                        $query
                            ->whereNull('completed_at')
                            ->whereNull('failed_at')
                            ->where('created_at', '<', $cutoff);
                    });
            })
            ->orderBy('id')
            ->chunkById(100, function (Collection $exports) use ($disk, &$deleted): void {
                foreach ($exports as $export) {
                    if ($export->path) {
                        $disk->delete($export->path);
                    }

                    $export->delete();
                    $deleted++;
                }
            });

        $this->info("Deleted {$deleted} expired report export(s).");

        return self::SUCCESS;
    }
}
