<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Workspace;
use App\Services\ImpersonationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ImpersonationController extends Controller
{
    public function start(Request $request, User $user, Workspace $workspace, ImpersonationService $impersonationService): RedirectResponse
    {
        $impersonationService->start($request->user(), $user, $workspace);

        return redirect()
            ->away($impersonationService->workspaceDashboardUrl($workspace))
            ->with('success', 'Impersonation mode started.');
    }

    public function exit(ImpersonationService $impersonationService): RedirectResponse
    {
        $impersonationService->end();

        return redirect()
            ->route('platform.users.index')
            ->with('success', 'Impersonation mode ended.');
    }
}
