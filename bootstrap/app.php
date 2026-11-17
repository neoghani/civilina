<?php

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
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'civilina.staff' => \App\Http\Middleware\EnsureCivilinaPlatformStaff::class,
        ]);

        // Per-account request limit on every web (and web-based API) route.
        $middleware->web(append: [
            \Illuminate\Routing\Middleware\ThrottleRequests::class.':app',
        ]);

        // Deny non-staff before route-model binding answers 404 for
        // unknown corpus documents.
        $middleware->prependToPriorityList(
            before: \Illuminate\Routing\Middleware\SubstituteBindings::class,
            prepend: \App\Http\Middleware\EnsureCivilinaPlatformStaff::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
