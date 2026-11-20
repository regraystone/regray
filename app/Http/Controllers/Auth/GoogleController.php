<?php

namespace App\Http\Controllers\Auth;

use App\Enums\SocialProvider;
use App\Exceptions\SocialSignInException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SocialSignUpRequest;
use App\Models\User;
use App\Services\SocialSignInService;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;

/**
 * Sign in and sign up with Google, and connecting Google to an account.
 *
 * Every trip to Google comes back to callback(). A visitor whose Google account
 * is connected is signed in. One whose verified Google email is an account's
 * gives that account's password first (confirm()), and a new one agrees to the
 * terms before an account is made (finish()).
 * A signed-in user who asked to connect Google from their settings has it
 * linked to their own account instead.
 */
class GoogleController extends Controller
{
    /** How long a Google sign-up may sit on the Finish signing up page. */
    private const PENDING_MINUTES = 15;

    private const PROVIDER = SocialProvider::Google;

    public function __construct(private readonly SocialSignInService $signIn) {}

    /** Send a visitor to Google to sign in or sign up. */
    public function redirect(): SymfonyRedirect
    {
        if (! self::PROVIDER->isConfigured()) {
            return $this->fail(route('login'), __('Google sign-in is not set up yet. Sign in with your email and password.'));
        }

        return $this->toGoogle();
    }

    /** Send a signed-in user to Google to connect it to their account. */
    public function connect(Request $request): SymfonyRedirect
    {
        if (! self::PROVIDER->isConfigured()) {
            return $this->fail(route('profile.edit'), __('Google sign-in is not set up yet.'));
        }

        $request->session()->put('social.link', $request->user()?->id);

        return $this->toGoogle();
    }

    public function callback(Request $request): RedirectResponse
    {
        $linkFor = $request->session()->pull('social.link');
        $failTo = $linkFor !== null ? route('profile.edit') : route('login');

        // Google sends `error` back when the visitor cancels on its page.
        if ($request->filled('error')) {
            return $this->fail($failTo, __('Google sign-in was cancelled.'));
        }

        try {
            $outside = Socialite::driver(self::PROVIDER->value)->user();
        } catch (InvalidStateException|GuzzleException $exception) {
            // Logged, since the visitor only sees "try again": an expired
            // request, a wrong client secret or an unreachable Google all end here.
            Log::warning('Google sign-in failed.', ['exception' => $exception::class, 'message' => $exception->getMessage()]);

            return $this->fail($failTo, __('Google sign-in did not go through. Try again.'));
        }

        if ($linkFor !== null) {
            return $this->linkTo($request, $linkFor, $outside);
        }

        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        $connected = $this->signIn->connectedAccount(self::PROVIDER, $outside);

        if ($connected !== null) {
            return $this->signInAs($request, $connected);
        }

        try {
            $existing = $this->signIn->accountWithEmail(self::PROVIDER, $outside);
        } catch (SocialSignInException $exception) {
            return $this->fail(route('login'), $exception->getMessage());
        }

        $request->session()->put('social.pending', [
            // Signing up, or connecting to the account that has this email.
            'kind' => $existing === null ? 'signup' : 'link',
            'user_id' => $existing?->id,
            'provider' => self::PROVIDER->value,
            'provider_user_id' => (string) $outside->getId(),
            'email' => (string) $outside->getEmail(),
            'name' => (string) ($outside->getName() ?? ''),
            'expires_at' => Date::now()->addMinutes(self::PENDING_MINUTES)->getTimestamp(),
        ]);

        return redirect()->route($existing === null ? 'auth.google.finish' : 'auth.google.confirm');
    }

    /**
     * Connect your Google account: the Google email is an existing account's,
     * and its password is asked for before the two are connected.
     */
    public function showConfirm(Request $request): Response|RedirectResponse
    {
        $pending = $this->pending($request, 'link');

        if ($pending === null) {
            return $this->fail(route('login'), __('Connecting Google timed out. Continue with Google again.'));
        }

        return Inertia::render('auth/google-confirm', [
            'email' => $pending['email'],
        ]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        $pending = $this->pending($request, 'link');

        if ($pending === null) {
            return $this->fail(route('login'), __('Connecting Google timed out. Continue with Google again.'));
        }

        $request->validate(['password' => ['required', 'string']], [
            'password.required' => __('Enter your password.'),
        ]);

        $user = User::query()->find($pending['user_id']);

        if ($user === null || ! Hash::check($request->string('password')->toString(), $user->password)) {
            throw ValidationException::withMessages([
                'password' => __('That password is not right for this account.'),
            ]);
        }

        $request->session()->forget('social.pending');

        try {
            $this->signIn->confirmLink($user, self::PROVIDER, $pending['provider_user_id'], $pending['email']);
        } catch (SocialSignInException $exception) {
            return $this->fail(route('login'), $exception->getMessage());
        }

        return $this->signInAs($request, $user);
    }

    /** Leave Connect your Google account without connecting anything. */
    public function cancelConfirm(Request $request): RedirectResponse
    {
        $request->session()->forget('social.pending');

        return redirect()->route('login');
    }

    /** The Finish signing up page: the terms, for a visitor new from Google. */
    public function showFinish(Request $request): Response|RedirectResponse
    {
        $pending = $this->pending($request, 'signup');

        if ($pending === null) {
            return $this->fail(route('register'), __('Your Google sign-up timed out. Continue with Google again.'));
        }

        return Inertia::render('auth/google-finish', [
            'name' => $pending['name'],
            'email' => $pending['email'],
            'termsVersion' => config('legal.terms_version'),
        ]);
    }

    public function finish(SocialSignUpRequest $request): RedirectResponse
    {
        $pending = $this->pending($request, 'signup');

        if ($pending === null) {
            return $this->fail(route('register'), __('Your Google sign-up timed out. Continue with Google again.'));
        }

        $request->session()->forget('social.pending');

        // Somebody made an account with this email while the page was open.
        // Going round again asks for its password, as for any other.
        $existing = $this->signIn->findByEmail($pending['email']);

        if ($existing !== null) {
            return $this->fail(route('login'), __('An account with this email was just made. Continue with Google again to connect it.'));
        }

        $user = $this->signIn->register(
            self::PROVIDER,
            $pending,
            $request->string('name')->trim()->toString(),
            $request->boolean('marketing_emails'),
            $request,
        );

        return $this->signInAs($request, $user);
    }

    /** Disconnect Google from the signed-in user's account. */
    public function disconnect(Request $request): RedirectResponse
    {
        $request->user()?->socialAccounts()->where('provider', self::PROVIDER)->delete();

        return redirect()->route('profile.edit')->with('socialNotice', [
            'type' => 'success',
            'message' => __('Google is disconnected from your account.'),
        ]);
    }

    private function toGoogle(): SymfonyRedirect
    {
        // Always offer the account chooser, so someone signed in to several
        // Google accounts picks the one they mean.
        $driver = Socialite::driver(self::PROVIDER->value);

        if ($driver instanceof AbstractProvider) {
            $driver->with(['prompt' => 'select_account']);
        }

        return $driver->redirect();
    }

    private function linkTo(Request $request, mixed $linkFor, SocialiteUser $outside): RedirectResponse
    {
        $user = $request->user();

        // The session that asked to connect is not the one that came back.
        if ($user === null || $user->id !== $linkFor) {
            return $this->fail(route('login'), __('Sign in first, then connect Google from Account & Security.'));
        }

        try {
            $this->signIn->assertVerifiedEmail(self::PROVIDER, $outside);
            $this->signIn->link($user, self::PROVIDER, (string) $outside->getId(), $outside->getEmail());
        } catch (SocialSignInException $exception) {
            return $this->fail(route('profile.edit'), $exception->getMessage());
        }

        return redirect()->route('profile.edit')->with('socialNotice', [
            'type' => 'success',
            'message' => __('Google is connected. You can now sign in with it.'),
        ]);
    }

    /**
     * Sign the user in, as the Sign In form would: a user with two-factor
     * authentication on is asked for their code first, exactly as after a
     * password.
     */
    private function signInAs(Request $request, User $user): RedirectResponse
    {
        try {
            $this->signIn->assertCanSignIn($user);
        } catch (SocialSignInException $exception) {
            return $this->fail(route('login'), $exception->getMessage());
        }

        if (Features::enabled(Features::twoFactorAuthentication()) && $user->hasEnabledTwoFactorAuthentication()) {
            $request->session()->put(['login.id' => $user->getKey(), 'login.remember' => false]);

            return redirect()->route('two-factor.login');
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    /**
     * The Google sign-in waiting on this visitor, while it is still fresh:
     * `signup` for Finish signing up, `link` for Connect your Google account.
     *
     * @param  'signup'|'link'  $kind
     * @return array{provider_user_id: string, email: string, name: string, user_id: int|null}|null
     */
    private function pending(Request $request, string $kind): ?array
    {
        $pending = $request->session()->get('social.pending');

        if (! is_array($pending) || ($pending['kind'] ?? null) !== $kind || ($pending['provider'] ?? null) !== self::PROVIDER->value || ($pending['expires_at'] ?? 0) < Date::now()->getTimestamp()) {
            $request->session()->forget('social.pending');

            return null;
        }

        return [
            'provider_user_id' => (string) $pending['provider_user_id'],
            'email' => (string) $pending['email'],
            'name' => (string) $pending['name'],
            'user_id' => isset($pending['user_id']) ? (int) $pending['user_id'] : null,
        ];
    }

    private function fail(string $to, string $message): RedirectResponse
    {
        return redirect($to)->with('socialNotice', ['type' => 'error', 'message' => $message]);
    }
}
