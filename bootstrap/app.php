<?php

use App\Http\Middleware\EnsureAdminRole;
use App\Http\Middleware\EnsureAdminTwoFactor;
use App\Http\Middleware\EnsurePaymentIp;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\TrackUserActivity;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);
        $middleware->web(append: [TrackUserActivity::class]);

        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('admin*')
            ? route('admin.login')
            : route('login'));

        $middleware->redirectUsersTo(fn (Request $request) => $request->is('admin*')
            ? route('admin.dashboard')
            : route('console.dashboard'));

        $middleware->alias([
            'admin.role' => EnsureAdminRole::class,
            'admin.2fa' => EnsureAdminTwoFactor::class,
            'payment.ip' => EnsurePaymentIp::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
