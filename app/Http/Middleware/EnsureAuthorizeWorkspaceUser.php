<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAuthorizeWorkspaceUser
{
    /**
     * Handle an incoming request.
     *
     * Ensure that only workspace owners and admins can access authorized resources.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $workspace = app('currentWorkspace');
       

        // Deny access if no workspace is found
        if (! $workspace) {
            throw new HttpResponseException(
                redirect()->route('dashboard')->with('error', 'No workspace found.')
            );
        }

        // Check if user is owner or admin of the workspace
        $isAuthorized = $workspace->users()
            ->where('user_id', Auth::id())
            ->whereIn('workspace_user.role', ['owner', 'admin'])
            ->exists();

        if (! $isAuthorized) {
            throw new HttpResponseException(
                redirect()->route('dashboard')->with('error', 'You are not authorized to manage this workspace.')
            );
        }      

        return $next($request);
    }
}
