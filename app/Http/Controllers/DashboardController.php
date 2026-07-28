<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $currentWorkspace = request()->currentWorkspace;
        $clientCount = $currentWorkspace?->clients()->count() ?? 0;

        return view('dashboard.index', compact('clientCount'));
    }


    public function __invoke(DashboardService $dashboardService) {
        $workspace = request()->currentWorkspace;

        return view('dashboard.index', [
            

            'revenue' => $dashboardService->revenueSummary($workspace->id),

            'debt' => $dashboardService->outstandingDebt($workspace->id),

            'overdue' => $dashboardService->overdueSummary($workspace->id),

            'activities' => $dashboardService->recentActivities($workspace->id),

            'topDebtors' => $dashboardService->topDebtors($workspace->id),

            // 'reminderStats' => $dashboardService->reminderStats($workspace->id),

            'outstandingInvoices' => $dashboardService->outstandingInvoices($workspace->id),
            /*
             'revenue' => $dashboardService->revenueSummary($workspace->id),
    'debt' => $dashboardService->outstandingDebt($workspace->id),
    'overdue' => $dashboardService->overdueSummary($workspace->id),

    'monthlyRevenue' => $dashboardService->monthlyRevenue($workspace->id),
             */
        ]);
    }

}
