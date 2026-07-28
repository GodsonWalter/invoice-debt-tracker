<?php

namespace App\Http\Controllers;

use App\Services\WorkspaceDashboardService;

class WorkspaceDashboardController extends Controller
{
    public function __invoke(WorkspaceDashboardService $dashboardService)
    {
        $workspace = request()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('workspace.index')->with('error', 'Please switch to a workspace before accessing the dashboard.');
        }

        return view('dashboard.workspace', [
            'workspace' => $workspace,

            'revenue' => $dashboardService->revenueSummary($workspace->id),

            'debt' => $dashboardService->outstandingDebt($workspace->id),

            'overdue' => $dashboardService->overdueSummary($workspace->id),

            'activities' => $dashboardService->recentActivities($workspace->id),

            'topDebtors' => $dashboardService->topDebtors($workspace->id),

            // 'reminderStats' => $dashboardService->reminderStats($workspace->id),

            'outstandingInvoices' => $dashboardService->outstandingInvoices($workspace->id),

            // 'monthlyRevenue' => $dashboardService->monthlyRevenue($workspace->id),

        ]);
    }
}
