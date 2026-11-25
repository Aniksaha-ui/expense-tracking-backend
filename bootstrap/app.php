<?php

use App\Http\Middleware\AttachApiTraceId;
use App\Http\Middleware\EnsurePortfolioToken;
use App\Http\Middleware\RecordInAppActivity;
use App\Support\ApiResponse;
use App\Support\TraceId;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AttachApiTraceId::class);
        $middleware->alias([
            'activity.notification' => RecordInAppActivity::class,
            'portfolio.token' => EnsurePortfolioToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->context(fn (): array => [
            'trace_id' => TraceId::current(),
        ]);

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiResponse::error('Unauthenticated.', [], 401);
            }
        });
    })->create();
