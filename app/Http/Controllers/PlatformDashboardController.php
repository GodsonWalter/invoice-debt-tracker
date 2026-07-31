<?php

namespace App\Http\Controllers;

use App\Http\Requests\PlatformDashboardRequest;
use App\Services\PlatformDashboardService;
use Illuminate\View\View;

class PlatformDashboardController extends Controller
{
    public function __invoke(PlatformDashboardRequest $request, PlatformDashboardService $dashboardService): View
    {
        return view('platform.dashboard.index', [
            'dashboard' => $dashboardService->dashboard(
                $request->user(),
                $request->dashboardPeriod(),
            ),
        ]);
    }
}
