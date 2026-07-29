<?php

namespace App\Http\Controllers;

use App\Data\ReportFilters;
use App\Http\Requests\ReportRequest;
use App\Jobs\GenerateReportExport;
use App\Models\ReportExport;
use App\Models\Workspace;
use App\Report\ReportExportService;
use App\Report\ReportService;
use App\ReportType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function index(ReportRequest $request, ReportService $reportService): View|RedirectResponse
    {
        $workspace = $this->workspace();

        if (! $workspace) {
            return redirect()->route('dashboard')->with('error', 'Please switch to a workspace before viewing reports.');
        }

        $filters = $request->filters();

        return view('reports.index', [
            'workspace' => $workspace,
            'report' => $reportService->build($workspace, $filters),
            'reportTypes' => ReportType::cases(),
            'customers' => $workspace->clients()->orderBy('name')->get(['id', 'name']),
            'exports' => ReportExport::query()
                ->where('workspace_id', $workspace->id)
                ->latest()
                ->limit(10)
                ->get(),
        ]);
    }

    public function export(ReportRequest $request, string $report, string $format, ReportExportService $exportService): Response|RedirectResponse
    {
        $workspace = $this->workspace();

        if (! $workspace) {
            return redirect()->route('dashboard')->with('error', 'Please switch to a workspace before exporting reports.');
        }

        $filters = $request->filters();

        if (in_array($format, ['xlsx', 'pdf'], true) && $exportService->count($workspace, $filters) > (int) config('reports.large_export_row_threshold', 5000)) {
            $export = $this->queueExport($workspace, $request, $filters, $format);

            return redirect()->route('reports.index', ['report' => $report])
                ->with('success', 'This large export has been queued. You can download it when processing is complete.')
                ->with('report_export_id', $export->id);
        }

        return match ($format) {
            'csv' => $exportService->csv($workspace, $filters),
            'xlsx' => $exportService->xlsx($workspace, $filters),
            'pdf' => $exportService->pdf($workspace, $filters),
            default => abort(404),
        };
    }

    public function queue(ReportRequest $request, string $report, string $format): RedirectResponse
    {
        abort_unless(in_array($format, ['csv', 'xlsx', 'pdf'], true), 404);

        $workspace = $this->workspace();
        if (! $workspace) {
            return redirect()->route('dashboard')->with('error', 'Please switch to a workspace before exporting reports.');
        }

        $filters = $request->filters();
        $export = $this->queueExport($workspace, $request, $filters, $format);

        return redirect()->route('reports.index', ['report' => $report])
            ->with('success', 'Your export has been queued. You can download it when processing is complete.')
            ->with('report_export_id', $export->id);
    }

    public function download(int $reportExport): Response|RedirectResponse
    {
        $workspace = $this->workspace();
        $export = $workspace
            ? ReportExport::query()->where('workspace_id', $workspace->id)->findOrFail($reportExport)
            : null;

        if (! $workspace || ! $export) {
            return redirect()->route('dashboard')->with('error', 'Please switch to a workspace before downloading reports.');
        }

        if ($export->status !== ReportExport::STATUS_COMPLETED || ! $export->path) {
            return redirect()->route('reports.index', ['report' => $export->report_type])
                ->with('error', $export->error_message ?: 'This export is not ready yet.');
        }

        $disk = Storage::disk((string) config('reports.export_disk', 'local'));
        if (! $disk->exists($export->path)) {
            abort(404);
        }

        return $disk->download($export->path, basename($export->path));
    }

    private function queueExport(Workspace $workspace, ReportRequest $request, ReportFilters $filters, string $format): ReportExport
    {
        $export = ReportExport::create([
            'workspace_id' => $workspace->id,
            'user_id' => $request->user()->id,
            'report_type' => $filters->type->value,
            'format' => $format,
            'filters' => $filters->toQuery(),
            'status' => ReportExport::STATUS_PENDING,
        ]);
        $export->update(['path' => 'reports/'.$workspace->id.'/'.$export->id.'.'.$format]);

        GenerateReportExport::dispatch($export->id);

        return $export;
    }

    private function workspace(): ?Workspace
    {
        $workspace = app()->bound('currentWorkspace') ? app('currentWorkspace') : null;

        return $workspace instanceof Workspace ? $workspace : null;
    }
}
