<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RedirectController extends Controller
{
    /**
     * Redirect the authenticated user to their role-based landing page.
     */
    public function dashboard(Request $request): RedirectResponse
    {
        $role = $request->user()->role;

        $path = config('roles.redirects.'.$role, config('roles.default'));

        return new RedirectResponse($path);
    }
}
