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
        // Register language setting middleware and SimpleAuth middleware for all web routes
        $middleware->web(append: [
            \App\Http\Middleware\SetLanguage::class,
            \App\Http\Middleware\SimpleAuth::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->withProviders([
        \App\Providers\DashboardCacheServiceProvider::class,
    ])
    ->create();