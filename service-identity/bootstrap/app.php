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
    ->withMiddleware(function (Middleware $middleware) {
        // Global middleware: Force JSON for all API requests
        $middleware->prepend(\App\Http\Middleware\ForceJsonResponse::class);

        // Named middleware aliases
        $middleware->alias([
<<<<<<< HEAD
            'active'     => \App\Http\Middleware\EnsureAccountIsActive::class,
            'ability'    => \App\Http\Middleware\CheckTokenAbility::class,
            'admin'      => \App\Http\Middleware\AdminMiddleware::class,
            'permission' => \App\Http\Middleware\CheckPermission::class,
=======
            'active'  => \App\Http\Middleware\EnsureAccountIsActive::class,
            'ability' => \App\Http\Middleware\CheckTokenAbility::class,
            'admin'   => \App\Http\Middleware\AdminMiddleware::class,
>>>>>>> import/master
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Render all exceptions as JSON
        $exceptions->shouldRenderJsonWhen(fn ($request) => true);
    })->create();
