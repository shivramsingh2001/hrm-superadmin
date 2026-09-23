<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'sa_role' => \App\Http\Middleware\EnsureSuperAdminRole::class,
            'api.jwt' => \App\Http\Middleware\ApiJwtAuth::class,
            'api.sa_role' => \App\Http\Middleware\ApiSuperAdminRole::class,
        ]);
        $middleware->redirectGuestsTo(fn () => route('login'));

        // IP allowlist + hard session cap on every authenticated request.
        $middleware->web(append: [
            \App\Http\Middleware\SuperAdminGuard::class,
        ]);

        // API always speaks JSON (errors included).
        $middleware->api(prepend: [
            \App\Http\Middleware\ForceJsonResponse::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
