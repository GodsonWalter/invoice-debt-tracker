<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveWorkspace
{
    /**
     * Handle an incoming request.
     *
     * This middleware resolves the current workspace for the request
     * by looking up the workspace matching the request subdomain.
     * The workspace is then made available to controllers and views.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $workspace = $this->resolveWorkspaceFromHost($request->getHost());

        // Make the workspace available across the application.
        app()->instance('currentWorkspace', $workspace);
        // Retrieve anywhere using: app('currentWorkspace')

        $request->attributes->set('currentWorkspace', $workspace);
        // Retrieve using: $request->attributes->get('currentWorkspace')

        view()->share('currentWorkspace', $workspace);
        // Retrieve directly in Blade views using: $currentWorkspace

        $request->merge(['currentWorkspace' => $workspace]);
        // Retrieve using: $request->currentWorkspace OR request('currentWorkspace')

        return $next($request);
    }

    /**
     * Resolve the current workspace from the request host.
     */
    private function resolveWorkspaceFromHost(string $host): ?Workspace
    {
        $parts = explode('.', $host);

        // Only treat a host as a workspace subdomain if it has at least 3 parts.
        // Example: workspace.example.com => workspace
        $subdomain = count($parts) > 2 ? $parts[0] : null;

        if (! $subdomain) {
            return null;
        }
        return Workspace::where('subdomain', $subdomain)->first();
    }
}
