<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRouteWorkspaceMatchesActiveWorkspace
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $routeWorkspace = $request->route('workspace');
        $user = $request->user();

        if (! $routeWorkspace instanceof Workspace) {
            return $next($request);
        }

        $currentWorkspace = app()->bound('currentWorkspace')
            ? app('currentWorkspace')
            : null;

        if (! $currentWorkspace instanceof Workspace) {
            return redirect()
                ->route('workspace.index')
                ->with('error', 'Please switch to a workspace before accessing it.');
        }

        if (! $currentWorkspace->is($routeWorkspace)) {
            return redirect()
                ->route('workspace.index')
                ->with('error', 'Please switch to this workspace before accessing it.');
        }

        if (! $user || ! $routeWorkspace->is_active || ! $routeWorkspace->hasActiveMember($user)) {
            return redirect()
                ->route('dashboard')
                ->with('error', 'You are not authorized to access this workspace.');
        }

        return $next($request);
    }
}
