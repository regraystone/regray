<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs out a user whose account was deactivated or archived on the Accounts
 * page while they were signed in, on their very next request. Signing in again
 * is refused too (FortifyServiceProvider, SocialSignInService).
 */
class EnsureAccountActive
{
    public const MESSAGE = 'This account has been deactivated.';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $user->canSignIn()) {
            return $next($request);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json(['message' => __(self::MESSAGE)], 401);
        }

        return redirect()->route('login')->withErrors(['email' => __(self::MESSAGE)]);
    }
}
