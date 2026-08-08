<?php

namespace App\Http\Middleware;

use App\Services\ImpersonationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ImpersonationMiddleware
{
    public function __construct(private readonly ImpersonationService $impersonationService) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $context = $this->impersonationService->activate($request);

        if ($context && ! $this->impersonationService->allowsRequest(
            $request,
            app()->bound('currentWorkspace') ? app('currentWorkspace') : null,
            $context['workspace'],
        )) {
            return redirect()->away($this->impersonationService->workspaceDashboardUrl($context['workspace']))
                ->with('error', 'Impersonation is limited to the selected workspace.');
        }

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            if ($context) {
                $this->impersonationService->recordAction($request);
            }

            throw $exception;
        }

        if ($context) {
            $this->impersonationService->recordAction($request, $response->getStatusCode());
        }

        return $response;
    }
}
