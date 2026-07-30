<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWorkspaceIsActive
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $baseDomain = config('app.base_domain');
        $host = $request->getHost();

        if ($baseDomain && strcasecmp($host, trim($baseDomain, '"')) === 0) {
            return $next($request);
        }

        $subdomain = explode('.', $host)[0];
        $workspace = Workspace::where('subdomain', $subdomain)->where('is_active', true)->first();

        if (! $workspace) {
            $baseDomain = config('app.base_domain');
            $baseDomain = $baseDomain ? trim($baseDomain, '"') : $request->getHost();

            return redirect()->away($request->getScheme().'://'.$baseDomain);
        }

        return $next($request);
    }
}
