<?php

use App\Models\User;
use App\Notifications\QueuedResetPassword;
use App\Notifications\QueuedVerifyEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

test('verification requests send a queued verification notification', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->post(route('verification.send'))
        ->assertRedirect(route('verification.notice', absolute: false));

    Notification::assertSentTo($user, QueuedVerifyEmail::class, function (QueuedVerifyEmail $notification) {
        expect($notification)->toBeInstanceOf(ShouldQueue::class);

        return true;
    });
});

test('password reset requests send a queued reset notification', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', __('passwords.sent'));

    Notification::assertSentTo($user, QueuedResetPassword::class, function (QueuedResetPassword $notification) {
        expect($notification)->toBeInstanceOf(ShouldQueue::class);

        return true;
    });
});
