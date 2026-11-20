<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps a user on Set your password while they still hold the temporary one
 * an account made on the Accounts page starts with (`must_change_password`).
 *
 * Every other request goes there: a page or form with a redirect (303 for
 * anything but a GET, so an Inertia visit follows it as a GET), and a JSON
 * request, such as the address picker's, with a 403. Signing out, verifying
 * the email and the two-factor challenge still work meanwhile.
 */
class EnsurePasswordChanged
{
    /** @var list<string> */
    private const ALLOWED_ROUTES = [
        'password.change',
        'password.change.update',
        'logout',
        'verification.notice',
        'verification.verify',
        'verification.send',
        'two-factor.login',
        'two-factor.login.store',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->must_change_password || $request->routeIs(...self::ALLOWED_ROUTES)) {
            return $next($request);
        }

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json(['message' => __('Set your password first.')], 403);
        }

        return new RedirectResponse(route('password.change'), $request->isMethod('GET') ? 302 : 303);
    }
}
