<?php

use App\Http\Middleware\EnforceAccountInspection;
use App\Http\Middleware\EnforceAudit;
use App\Http\Middleware\EnsureIdentitySessionCurrent;
use App\Http\Middleware\EnsureUserAccessEnabled;
use App\Http\Middleware\JsonNavigation;
use App\Http\Middleware\ResolveOrganizationContext;
use App\Http\Middleware\SelfServicePermissions;
use App\Http\Middleware\ThrottleAccessRecovery;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['organization' => ResolveOrganizationContext::class]);

        $middleware->web(append: [
            EnsureUserAccessEnabled::class,
            JsonNavigation::class,
            EnsureIdentitySessionCurrent::class,
            EnforceAccountInspection::class,
            EnforceAudit::class,
            SelfServicePermissions::class,
            ThrottleAccessRecovery::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['administrator.password', 'administrator.password_confirmation']);

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson()
                || $request->routeIs('account.company.*.find-person'),
        );
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request): Response {
            if ($request->routeIs('account.company.*.find-person')) {
                $response->headers->set('Cache-Control', 'private, no-store');
            }

            return $response;
        });
    })->create();
