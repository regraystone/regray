<?php

use App\Models\User;
use App\Notifications\QueuedVerifyEmail;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register and are redirected to the email verification prompt', function () {
    Notification::fake();

    $email = 'new-user-'.fake()->unique()->safeEmail();

    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => $email,
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('verification.notice', absolute: false));

    Notification::assertSentTo(User::where('email', $email)->first(), QueuedVerifyEmail::class);
});
