<?php

use App\Http\Middleware\AuthorizeAnyPermission;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['sidebar_state']);

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        /*
         * `can_any` is the app's own, because `can:` can only ask the Gate about
         * one ability. It is the only guard that admits a holder of any of
         * several permissions, and the only page that needs it is the inquiry
         * details page, which hangs off either list a reader might use.
         *
         * Spatie's aliases are registered too, since the package ships them but
         * no longer registers them itself. Nothing in routes/ reaches for them
         * yet: the app authorizes through the Gate so that a denial is one
         * exception type with one rendering, and so that the super-admin
         * bypass in AppServiceProvider applies. They are here for a route that
         * genuinely needs the package's own behaviour — `role` and
         * `role_or_permission` in particular would be the first thing to reach
         * for who somebody is rather than what they may do.
         */
        $middleware->alias([
            'can_any' => AuthorizeAnyPermission::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        /*
         * A page the user may not open answers with the Inertia forbidden page
         * rather than a raw exception. The Gate and the permission middleware
         * both end up here, and by this point the handler has already
         * normalised them to a plain 403, so the status is what this matches on.
         *
         * It deliberately does not redirect: the button on the page is what
         * takes a user back to their own landing page, so nothing here can
         * bounce them between two pages they may not open.
         */
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if ($e->getStatusCode() !== 403 || $request->is('api/*') || $request->expectsJson()) {
                return null;
            }

            return Inertia::render('forbidden')->toResponse($request)->setStatusCode(403);
        });
    })->create();
