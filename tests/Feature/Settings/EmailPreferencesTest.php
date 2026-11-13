<?php

use App\Enums\ConsentSource;
use App\Enums\ConsentType;
use Database\Seeders\RolePermissionSeeder;

/*
 * Promotional emails and announcements, turned on and off from Account &
 * Security. Each change is a consent record of its own.
 */
beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

it('shows the user where they stand on the account page', function (): void {
    $user = inquiryClient();
    $user->forceFill(['terms_accepted_at' => now(), 'marketing_emails_opted_in_at' => now()])->save();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertInertia(fn ($page) => $page
            ->where('emailPreferences.marketingEmails', true)
            ->whereType('emailPreferences.termsAcceptedAt', 'string')
            ->where('emailPreferences.termsVersion', config('legal.terms_version')));
});

it('turns promotional emails on and off, recording each change', function (): void {
    $user = inquiryClient();

    $this->actingAs($user)
        ->patch(route('email-preferences.update'), ['marketing_emails' => '1'])
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->wantsMarketingEmails())->toBeTrue();

    $this->actingAs($user)
        ->patch(route('email-preferences.update'), ['marketing_emails' => '0'])
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->wantsMarketingEmails())->toBeFalse()
        ->and($user->consents()->where('type', ConsentType::MarketingEmails)->orderBy('id')->pluck('granted')->all())->toBe([true, false])
        ->and($user->consents()->pluck('source')->unique()->all())->toBe([ConsentSource::Settings]);
});

it('records nothing when the choice is saved unchanged', function (): void {
    $user = inquiryClient();

    $this->actingAs($user)
        ->patch(route('email-preferences.update'), ['marketing_emails' => '0'])
        ->assertRedirect(route('profile.edit'));

    expect($user->consents()->count())->toBe(0);
});

it('refuses a choice that is not yes or no', function (array $input): void {
    $this->actingAs(inquiryClient())
        ->patch(route('email-preferences.update'), $input)
        ->assertSessionHasErrors('marketing_emails');
})->with([
    'missing' => [[]],
    'not a boolean' => [['marketing_emails' => 'sometimes']],
]);

it('lets nobody change another user\'s preferences: the signed-in user is the only one it touches', function (): void {
    $user = inquiryClient();
    $other = inquiryClient();

    $this->actingAs($user)
        ->patch(route('email-preferences.update'), ['marketing_emails' => '1', 'user_id' => $other->id]);

    expect($other->refresh()->wantsMarketingEmails())->toBeFalse()
        ->and($user->refresh()->wantsMarketingEmails())->toBeTrue();
});

it('needs a signed-in user', function (): void {
    $this->patch(route('email-preferences.update'), ['marketing_emails' => '1'])
        ->assertRedirect(route('login'));
});
