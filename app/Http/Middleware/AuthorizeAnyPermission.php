<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admit a user who holds any one of the given permissions.
 *
 * `can:` takes a single ability, which is all a page of its own needs. The
 * inquiry details page is the exception: it hangs off whichever list a reader
 * uses, so `inquiries.view` and `quotations.view` are both answers and neither
 * is the wrong one. Gate::any() is how that is asked.
 *
 * The denial is the framework's own AuthorizationException rather than an
 * abort, so a 403 from here is indistinguishable from a 403 from `can:` and the
 * same handler renders the Inertia forbidden page for both. Asking the Gate
 * instead of the permission table is what keeps AppServiceProvider's
 * super-admin bypass in play: Gate::any() runs the same before callbacks `can:`
 * does, so the super-admin is admitted here too.
 */
class AuthorizeAnyPermission
{
    /**
     * @param  string  ...$permissions  Permission names, comma-separated on the route.
     *
     * @throws AuthorizationException
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        if ($permissions === []) {
            throw new AuthorizationException;
        }

        if (! Gate::any($permissions)) {
            throw new AuthorizationException;
        }

        return $next($request);
    }
}
