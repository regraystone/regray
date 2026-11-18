<?php

namespace App\Http\Controllers\Settings;

use App\Enums\SocialProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the Profile hub.
     *
     * Not a form: this is the page the sidebar and the header dropdown point at,
     * and it only lists the doors this reader has. Which of them are drawn is
     * decided in the front end from the shared `auth.permissions` prop, the same
     * way the sidebar decides what to hide, so there is nothing to pass here.
     */
    public function show(): Response
    {
        return Inertia::render('profile/index');
    }

    /**
     * Show the Settings hub.
     *
     * Like the Profile hub, a list of doors: Account & Security, Help & Support
     * (coming soon), Switch account and Log out. Every reader of the profile has
     * all four, so there is nothing to pass.
     */
    public function settings(): Response
    {
        return Inertia::render('profile/settings');
    }

    /**
     * Show the user's Account & Security page.
     *
     * The name, email, security and delete-account settings, which is what the
     * old /profile page showed and what it still shows.
     */
    public function edit(Request $request): Response
    {
        $google = $request->user()->socialAccounts()->where('provider', SocialProvider::Google)->first();

        return Inertia::render('profile/account-security', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'google' => [
                'available' => SocialProvider::Google->isConfigured(),
                // The Google account's email when connected, null when not.
                'connectedEmail' => $google?->email,
                'connected' => $google !== null,
            ],
            'socialNotice' => $request->session()->get('socialNotice'),
            'emailPreferences' => [
                'marketingEmails' => $request->user()->wantsMarketingEmails(),
                'termsAcceptedAt' => $request->user()->terms_accepted_at?->toIso8601String(),
                'termsVersion' => config('legal.terms_version'),
            ],
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
