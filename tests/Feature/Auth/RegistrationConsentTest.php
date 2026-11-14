<?php

use App\Enums\ConsentSource;
use App\Enums\ConsentType;
use App\Models\User;
use App\Models\UserConsent;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

/*
 * The sign-up form's two boxes: the terms, without which there is no account,
 * and promotional emails, which are optional and off unless ticked. Each is
 * kept as a consent record saying when and from where it was given.
 */
beforeEach(function (): void {
    Notification::fake();
    config(['legal.terms_version' => '2026-11-13']);
});

/** @param  array<string, mixed>  $overrides */
function signUp(array $overrides = []): TestResponse
{
    return test()->withHeader('User-Agent', 'ConsentTest/1.0')->post(route('register.store'), [
        'name' => 'New Client',
        'email' => 'new-client@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        ...$overrides,
    ]);
}

it('creates no account without agreeing to the terms', function (mixed $terms): void {
    signUp($terms === null ? [] : ['terms' => $terms])
        ->assertSessionHasErrors(['terms' => 'Agree to the Terms & Conditions to create an account.']);

    expect(User::query()->where('email', 'new-client@example.com')->exists())->toBeFalse()
        ->and(UserConsent::query()->count())->toBe(0);
})->with([
    'left out' => [null],
    'unticked' => [false],
]);

it('records the agreement to the terms, with its version, date and where it came from', function (): void {
    $this->freezeSecond();

    signUp(['terms' => true])->assertRedirect();

    $user = User::query()->where('email', 'new-client@example.com')->firstOrFail();
    $consent = $user->consents()->where('type', ConsentType::Terms)->sole();

    expect($user->terms_accepted_at?->equalTo(now()))->toBeTrue()
        ->and($user->terms_version)->toBe('2026-11-13')
        ->and($consent->granted)->toBeTrue()
        ->and($consent->version)->toBe('2026-11-13')
        ->and($consent->source)->toBe(ConsentSource::Registration)
        ->and($consent->ip_address)->toBe('127.0.0.1')
        ->and($consent->user_agent)->toBe('ConsentTest/1.0')
        ->and($consent->created_at->equalTo(now()))->toBeTrue();
});

it('leaves promotional emails off unless the box is ticked, and records the choice either way', function (array $input, bool $wanted): void {
    signUp(['terms' => true, ...$input])->assertRedirect();

    $user = User::query()->where('email', 'new-client@example.com')->firstOrFail();

    expect($user->wantsMarketingEmails())->toBe($wanted)
        ->and($user->consents()->where('type', ConsentType::MarketingEmails)->sole()->granted)->toBe($wanted);
})->with([
    'left out' => [[], false],
    'unticked' => [['marketing_emails' => false], false],
    'ticked' => [['marketing_emails' => true], true],
]);

it('keeps a consent record as it was written', function (): void {
    signUp(['terms' => true])->assertRedirect();

    $consent = UserConsent::query()->firstOrFail();

    expect(fn () => $consent->update(['granted' => false]))->toThrow(LogicException::class)
        ->and(fn () => $consent->delete())->toThrow(LogicException::class);
});

it('gives the sign-up page the version shown in its Terms & Conditions dialog', function (): void {
    $this->get(route('register'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/register')->where('termsVersion', '2026-11-13'));
});
