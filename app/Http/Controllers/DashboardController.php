<?php

namespace App\Http\Controllers;

use App\Http\Requests\WorkspaceDashboardRequest;
use App\Models\Workspace;
use App\Services\WorkspaceDashboardService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(WorkspaceDashboardRequest $request, WorkspaceDashboardService $dashboardService): View
    {
        $workspace = request()->currentWorkspace;

        if ($workspace instanceof Workspace && $workspace->canBeManagedBy(Auth::user())) {
            return view('dashboard.workspace', [
                'workspace' => $workspace,
                'dashboard' => $dashboardService->dashboard(
                    $workspace,
                    Auth::user(),
                    $request->dashboardPeriod(),
                    $request->dashboardCurrencyId(),
                ),
            ]);
        }

        return view('dashboard.index', [
            'workspace' => $workspace,
            'workspaces' => Auth::user()->workspaces()
                ->with(['businessProfile:id,workspace_id,business_name,logo'])
                ->wherePivot('is_active', true)
                ->where('workspaces.is_active', true)
                ->whereNotNull('workspaces.subdomain')
                ->where('workspaces.subdomain', '<>', '')
                ->orderBy('workspaces.name')
                ->get(),
        ]);
    }
}
