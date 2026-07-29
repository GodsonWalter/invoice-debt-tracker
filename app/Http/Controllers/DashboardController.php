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
                'dashboard' => $dashboardService->dashboard($workspace, Auth::user(), $request->dashboardPeriod()),
            ]);
        }

        return view('dashboard.index', [
            'workspace' => $workspace,
            'hasAuthorizedWorkspace' => false,
        ]);
    }
}
