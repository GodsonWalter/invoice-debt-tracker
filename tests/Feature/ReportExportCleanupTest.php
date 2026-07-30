<?php

use App\Models\ReportExport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

afterEach(function (): void {
    Carbon::setTestNow();
});

test('expired completed and failed exports are cleaned without removing pending exports', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-07-30 12:00:00'));
    config()->set('reports.export_retention_days', 7);
    Storage::fake('local');

    $completed = ReportExport::create([
        'workspace_id' => 1,
        'report_type' => 'payments',
        'format' => 'csv',
        'filters' => [],
        'status' => ReportExport::STATUS_COMPLETED,
        'path' => 'reports/1/old.csv',
        'completed_at' => now()->subDays(8),
    ]);
    $failed = ReportExport::create([
        'workspace_id' => 1,
        'report_type' => 'payments',
        'format' => 'pdf',
        'filters' => [],
        'status' => ReportExport::STATUS_FAILED,
        'path' => 'reports/1/failed.pdf',
        'failed_at' => now()->subDays(8),
    ]);
    $pending = ReportExport::create([
        'workspace_id' => 1,
        'report_type' => 'payments',
        'format' => 'xlsx',
        'filters' => [],
        'status' => ReportExport::STATUS_PENDING,
        'path' => 'reports/1/pending.xlsx',
        'created_at' => now()->subDays(8),
    ]);

    Storage::disk('local')->put($completed->path, 'completed');
    Storage::disk('local')->put($failed->path, 'failed');
    Storage::disk('local')->put($pending->path, 'pending');

    Artisan::call('reports:cleanup-exports');

    expect(ReportExport::query()->find($completed->id))->toBeNull()
        ->and(ReportExport::query()->find($failed->id))->toBeNull()
        ->and(ReportExport::query()->find($pending->id))->not->toBeNull()
        ->and(Storage::disk('local')->exists($completed->path))->toBeFalse()
        ->and(Storage::disk('local')->exists($failed->path))->toBeFalse()
        ->and(Storage::disk('local')->exists($pending->path))->toBeTrue();
});

test('exports exactly at the retention boundary are retained', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-07-30 12:00:00'));
    config()->set('reports.export_retention_days', 7);
    Storage::fake('local');

    $export = ReportExport::create([
        'workspace_id' => 1,
        'report_type' => 'payments',
        'format' => 'csv',
        'filters' => [],
        'status' => ReportExport::STATUS_COMPLETED,
        'path' => 'reports/1/boundary.csv',
        'completed_at' => now()->subDays(7),
    ]);
    Storage::disk('local')->put($export->path, 'boundary');

    Artisan::call('reports:cleanup-exports');

    expect(ReportExport::query()->find($export->id))->not->toBeNull()
        ->and(Storage::disk('local')->exists($export->path))->toBeTrue();
});
