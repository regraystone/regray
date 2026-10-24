<?php

use App\Console\Commands\CreateAdminCommand;
use App\Http\Middleware\AuthorizeAnyPermission;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Sentry\Laravel\Integration;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withCommands([
        CreateAdminCommand::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        /*
         * Trusted proxies come from config/trustedproxy.php (TRUSTED_PROXIES),
         * which trusts nobody unless a proxy is named.
         */
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
        /*
         * Reports every reported exception to Sentry, including the ones the
         * app hands to report() itself. It does nothing while no DSN is set.
         */
        Integration::handles($exceptions);

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        /*
         * A forbidden or missing page answers with its Inertia error page
         * rather than a raw exception. The Gate and the permission middleware
         * both end up here, and by this point the handler has already
         * normalised denials to 403 and missing models to 404, so this matches status.
         *
         * It deliberately does not redirect: the button on the page is what
         * takes a user back to their own landing page, so nothing here can
         * bounce them between two pages they may not open.
         */
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            $status = $e->getStatusCode();

            if (! in_array($status, [403, 404], true) || $request->is('api/*') || $request->expectsJson()) {
                return null;
            }

            return Inertia::render($status === 403 ? 'forbidden' : 'not-found')
                ->toResponse($request)
                ->setStatusCode($status);
        });
    })->create();
