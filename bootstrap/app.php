<?php

use App\Http\Middleware\EnsureAuthorizeWorkspaceUser;
use App\Http\Middleware\EnsureWorkspaceIsActive;
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
        $middleware->alias([
            'workspace.active' => EnsureWorkspaceIsActive::class,
            'resolve.workspace' => ResolveWorkspace::class,
            'authorized-workspace-user' => EnsureAuthorizeWorkspaceUser::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
