<?php

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\RestrictCustomerPortalAccess;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
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
        $middleware->trustProxies(at: '*');

        $middleware->redirectGuestsTo(fn (Request $request): string => route(
            $request->is('portal', 'portal/*') || $request->getHost() === config('app.customer_portal_domain') ? 'portal.login' : 'login'
        ));

        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'permission' => CheckPermission::class,
        ]);

        $middleware->appendToGroup('web', [
            EnsureUserIsActive::class,
            RestrictCustomerPortalAccess::class,
        ]);

        $middleware->prependToPriorityList(
            before: AuthenticatesRequests::class,
            prepend: RestrictCustomerPortalAccess::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
