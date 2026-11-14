<?php

use App\Enums\ConsentType;
use App\Enums\Role;
use App\Enums\SocialProvider;
use App\Models\Conversation;
use App\Models\SocialAccount;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as GoogleUser;

/*
 * Sign in and sign up with Google. Google itself is faked: each test says what
 * Google answers on the way back, as Socialite would hand it over.
 */
beforeEach(function (): void {
    Notification::fake();
    $this->seed(RolePermissionSeeder::class);
    config([
        'services.google.client_id' => 'test-client-id',
        'services.google.client_secret' => 'test-client-secret',
        'services.google.redirect' => 'http://localhost/auth/google/callback',
        'legal.terms_version' => '2026-11-13',
    ]);
});

/** What Google says about the account that signed in. */
function googleAnswers(string $id = 'google-123', string $email = 'ana@gmail.com', bool $verified = true, string $name = 'Ana Cruz'): GoogleUser
{
    $user = (new GoogleUser)
        ->setRaw(['sub' => $id, 'email' => $email, 'email_verified' => $verified, 'name' => $name])
        ->map(['id' => $id, 'email' => $email, 'name' => $name]);

    Socialite::shouldReceive('driver->user')->andReturn($user);

    return $user;
}

function linkGoogle(User $user, string $id = 'google-123'): SocialAccount
{
    return $user->socialAccounts()->create([
        'provider' => SocialProvider::Google,
        'provider_user_id' => $id,
        'email' => $user->email,
    ]);
}

it('sends a visitor to Google, offering the account chooser', function (): void {
    $response = $this->get(route('auth.google.redirect'));

    expect($response->headers->get('Location'))
        ->toStartWith('https://accounts.google.com/o/oauth2/auth')
        ->toContain('prompt=select_account')
        ->toContain('client_id=test-client-id');
});

it('says Google sign-in is not set up when it has no client', function (): void {
    config(['services.google.client_id' => null]);

    $this->get(route('auth.google.redirect'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('socialNotice.type', 'error');
});

it('signs in the account a Google account is already connected to', function (): void {
    $user = inquiryClient();
    linkGoogle($user);
    googleAnswers(email: 'a-different-address@gmail.com');

    $this->get(route('auth.google.callback'))->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

it('recognises an existing account by its Google-verified email, but asks for its password before connecting', function (): void {
    $user = User::factory()->role(Role::Client)->unverified()->create(['email' => 'Ana@Gmail.com']);
    googleAnswers();

    $this->get(route('auth.google.callback'))->assertRedirect(route('auth.google.confirm'));

    $this->assertGuest();
    expect($user->socialAccounts()->count())->toBe(0);

    $this->get(route('auth.google.confirm'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/google-confirm')->where('email', 'ana@gmail.com'));

    $this->post(route('auth.google.confirm.store'), ['password' => 'password'])
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
    expect($user->socialAccounts()->sole()->provider_user_id)->toBe('google-123')
        ->and($user->refresh()->hasVerifiedEmail())->toBeTrue();
});

it('connects nothing on the wrong password: owning the email at Google is not enough', function (): void {
    $user = inquiryClient();
    googleAnswers(email: $user->email);
    $this->get(route('auth.google.callback'));

    $this->post(route('auth.google.confirm.store'), ['password' => 'not-the-password'])
        ->assertSessionHasErrors(['password' => 'That password is not right for this account.']);

    $this->assertGuest();
    expect($user->socialAccounts()->count())->toBe(0);
});

it('asks for a password before checking one', function (): void {
    $user = inquiryClient();
    googleAnswers(email: $user->email);
    $this->get(route('auth.google.callback'));

    $this->post(route('auth.google.confirm.store'), ['password' => ''])
        ->assertSessionHasErrors(['password' => 'Enter your password.']);
});

it('leaves Connect your Google account without connecting, and has no such page once left or expired', function (): void {
    $user = inquiryClient();
    googleAnswers(email: $user->email);
    $this->get(route('auth.google.callback'));

    $this->delete(route('auth.google.confirm.cancel'))->assertRedirect(route('login'));
    $this->get(route('auth.google.confirm'))->assertRedirect(route('login'));

    $this->get(route('auth.google.callback'));
    $this->travel(16)->minutes();

    $this->post(route('auth.google.confirm.store'), ['password' => 'password'])
        ->assertRedirect(route('login'))
        ->assertSessionHas('socialNotice.type', 'error');

    $this->assertGuest();
    expect($user->socialAccounts()->count())->toBe(0);
});

it('keeps a Google sign-up and a connection apart: neither page answers for the other', function (): void {
    googleAnswers();
    $this->get(route('auth.google.callback'))->assertRedirect(route('auth.google.finish'));

    $this->post(route('auth.google.confirm.store'), ['password' => 'password'])
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('limits password guesses on Connect your Google account', function (): void {
    expect(Route::getRoutes()->getByName('auth.google.confirm.store')?->gatherMiddleware())->toContain('throttle:6,1');
});

it('will not match an account on an email Google has not verified', function (): void {
    $user = inquiryClient();
    googleAnswers(email: $user->email, verified: false);

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('socialNotice.type', 'error');

    $this->assertGuest();
    expect(SocialAccount::query()->count())->toBe(0);
});

it('asks for the two-factor code first when the account has it on', function (): void {
    $user = inquiryClient();
    $user->forceFill([
        'two_factor_secret' => encrypt('secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code'])),
        'two_factor_confirmed_at' => now(),
    ])->save();
    linkGoogle($user);
    googleAnswers();

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('two-factor.login'))
        ->assertSessionHas('login.id', $user->id);

    $this->assertGuest();
})->skip(fn () => ! Features::enabled(Features::twoFactorAuthentication()), 'Two-factor authentication is off.');

it('goes back to Sign In when the visitor cancels on Google or the request has expired', function (): void {
    $this->get(route('auth.google.callback', ['error' => 'access_denied']))
        ->assertRedirect(route('login'))
        ->assertSessionHas('socialNotice.message', 'Google sign-in was cancelled.');

    Socialite::shouldReceive('driver->user')->andThrow(new InvalidStateException);

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('socialNotice.message', 'Google sign-in did not go through. Try again.');

    $this->assertGuest();
});

it('makes a new client account only once the visitor has agreed to the terms', function (): void {
    googleAnswers();

    $this->get(route('auth.google.callback'))->assertRedirect(route('auth.google.finish'));
    $this->assertGuest();

    $this->get(route('auth.google.finish'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('auth/google-finish')
            ->where('email', 'ana@gmail.com')
            ->where('name', 'Ana Cruz')
            ->where('termsVersion', '2026-11-13'));

    $this->post(route('auth.google.finish.store'), ['name' => 'Ana Cruz', 'terms' => false])
        ->assertSessionHasErrors(['terms' => 'Agree to the Terms & Conditions to create an account.']);
    expect(User::query()->where('email', 'ana@gmail.com')->exists())->toBeFalse();

    $this->post(route('auth.google.finish.store'), ['name' => 'Ana C.', 'terms' => true, 'marketing_emails' => true])
        ->assertRedirect(route('dashboard'));

    $user = User::query()->where('email', 'ana@gmail.com')->sole();

    $this->assertAuthenticatedAs($user);
    expect($user->name)->toBe('Ana C.')
        ->and($user->isClient())->toBeTrue()
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and($user->terms_version)->toBe('2026-11-13')
        ->and($user->wantsMarketingEmails())->toBeTrue()
        ->and($user->consents()->where('type', ConsentType::Terms)->sole()->granted)->toBeTrue()
        ->and($user->socialAccounts()->sole()->provider_user_id)->toBe('google-123')
        ->and(Conversation::query()->where('support_client_id', $user->id)->count())->toBe(1);
});

it('sends a Google sign-up that has waited too long back to start again', function (): void {
    googleAnswers();
    $this->get(route('auth.google.callback'));

    $this->travel(16)->minutes();

    $this->post(route('auth.google.finish.store'), ['name' => 'Ana Cruz', 'terms' => true])
        ->assertRedirect(route('register'))
        ->assertSessionHas('socialNotice.type', 'error');

    expect(User::query()->where('email', 'ana@gmail.com')->exists())->toBeFalse();
});

it('has no Finish signing up page without a Google sign-up waiting', function (): void {
    $this->get(route('auth.google.finish'))->assertRedirect(route('register'));
});

it('connects Google to the signed-in user from their settings', function (): void {
    $user = inquiryClient();

    $this->actingAs($user)->get(route('social.google.connect'))
        ->assertRedirectContains('accounts.google.com');

    googleAnswers(email: 'another-address@gmail.com');

    $this->actingAs($user)->get(route('auth.google.callback'))
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHas('socialNotice.type', 'success');

    expect($user->socialAccounts()->sole()->email)->toBe('another-address@gmail.com');
});

it('will not connect a Google account that is already another user\'s', function (): void {
    $user = inquiryClient();
    linkGoogle(inquiryClient());

    $this->actingAs($user)->get(route('social.google.connect'));
    googleAnswers();

    $this->actingAs($user)->get(route('auth.google.callback'))
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHas('socialNotice.type', 'error');

    expect($user->socialAccounts()->count())->toBe(0);
});

it('disconnects Google, and shows where the account stands on Account & Security', function (): void {
    $user = inquiryClient();
    linkGoogle($user);

    $this->actingAs($user)->get(route('profile.edit'))
        ->assertInertia(fn ($page) => $page
            ->where('google.connected', true)
            ->where('google.available', true)
            ->where('google.connectedEmail', $user->email));

    $this->actingAs($user)->delete(route('social.google.disconnect'))
        ->assertRedirect(route('profile.edit'));

    expect($user->socialAccounts()->count())->toBe(0);
});
