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
    ->withMiddleware(function (Middleware $middleware): void {
        // Add Sanctum middleware for API authentication
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);
        
        // Add security middleware to API routes
        $middleware->api([
            \App\Http\Middleware\CorsMiddleware::class,
            \App\Http\Middleware\ApiRequestLogging::class,
            \App\Http\Middleware\SuspiciousActivityDetection::class,
            \App\Http\Middleware\ApiRateLimit::class,
            \App\Http\Middleware\InputSanitization::class,
        ]);
        
        // Register middleware aliases
        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'api.rate.limit' => \App\Http\Middleware\ApiRateLimit::class,
            'input.sanitization' => \App\Http\Middleware\InputSanitization::class,
            'api.logging' => \App\Http\Middleware\ApiRequestLogging::class,
            'suspicious.detection' => \App\Http\Middleware\SuspiciousActivityDetection::class,
            'cors' => \App\Http\Middleware\CorsMiddleware::class,
        ]);
    })
    ->withProviders([
        // Register crypto exchange service providers
        App\Providers\TradingServiceProvider::class,
    ])
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
