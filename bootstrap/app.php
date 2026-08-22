<?php

use App\Http\Middleware\EnsureAuthorizeWorkspaceUser;
use App\Http\Middleware\EnsureRouteWorkspaceMatchesActiveWorkspace;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureWorkspaceIsActive;
use App\Http\Middleware\ImpersonationMiddleware;
use App\Http\Middleware\ResolveWorkspace;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands()
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: ['webhooks/whatsapp/meta']);

        $middleware->alias([
            'active.user' => EnsureUserIsActive::class,
            'workspace.active' => EnsureWorkspaceIsActive::class,
            'resolve.workspace' => ResolveWorkspace::class,
            'impersonation' => ImpersonationMiddleware::class,
            'authorized-workspace-user' => EnsureAuthorizeWorkspaceUser::class,
            'active.route.workspace' => EnsureRouteWorkspaceMatchesActiveWorkspace::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
