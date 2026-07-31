<?php

namespace App\Http\Controllers;

use App\Http\Requests\WorkspaceDashboardRequest;
use App\Models\Workspace;
use App\Services\WorkspaceDashboardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class WorkspaceDashboardController extends Controller
{
    public function __invoke(
        WorkspaceDashboardRequest $request,
        Workspace $workspace,
        WorkspaceDashboardService $dashboardService,
    ): View|RedirectResponse {
        $currentWorkspace = request()->currentWorkspace;

        if (! $currentWorkspace instanceof Workspace) {
            return redirect()->away($this->baseDomainUrl('dashboard'))
                ->with('error', 'Please switch to a workspace before accessing the dashboard.');
        }

        if (! $currentWorkspace->is($workspace)) {
            return redirect()->away($this->workspaceDashboardUrl($currentWorkspace))
                ->with('error', 'Please switch to this workspace before accessing its dashboard.');
        }

        if (! $workspace->canBeManagedBy(Auth::user())) {
            abort(403);
        }

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

    private function baseDomainUrl(string $routeName): string
    {
        $baseDomain = trim((string) config('app.base_domain'), '"');

        return request()->getScheme().'://'.$baseDomain.route($routeName, [], false);
    }

    private function workspaceDashboardUrl(Workspace $workspace): string
    {
        $baseDomain = trim((string) config('app.base_domain'), '"');

        return request()->getScheme().'://'.$workspace->subdomain.'.'.$baseDomain.route('workspace.dashboard', $workspace, false);
    }
}
