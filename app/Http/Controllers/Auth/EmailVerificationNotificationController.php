<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Http\Controllers\EmailVerificationNotificationController as FortifyController;
use Symfony\Component\HttpFoundation\Response;

class EmailVerificationNotificationController extends FortifyController
{
    /**
     * Send a new email verification notification and return to the verify page.
     */
    public function store(Request $request): Response
    {
        if ($request->user()->hasVerifiedEmail()) {
            return $request->wantsJson()
                ? new JsonResponse('', 204)
                : redirect()->intended(config('fortify.home'));
        }

        $request->user()->sendEmailVerificationNotification();

        return $request->wantsJson()
            ? new JsonResponse('', 202)
            : redirect()->route('verification.notice')->with('status', 'verification-link-sent');
    }
}
