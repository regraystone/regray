<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends a signed-in reader from a public catalogue URL to the same page under
 * /dashboard, where the sidebar and workspace header are.
 *
 * The 301 depends on who is asking, so it must not be stored: a browser that
 * kept it would keep sending the same person to the dashboard after signing out.
 */
class RedirectSignedInToDashboard
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() === null) {
            return $next($request);
        }

        $query = $request->getQueryString();

        return redirect('/dashboard/'.$request->path().($query === null ? '' : '?'.$query), 301)
            ->header('Cache-Control', 'no-store');
    }
}
