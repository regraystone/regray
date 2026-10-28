<?php

use App\Models\User;
use App\Notifications\QueuedResetPassword;
use App\Notifications\QueuedVerifyEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

test('queues the verification and the password reset emails', function () {
    Notification::fake();
    $unverified = User::factory()->unverified()->create();
    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email])->assertSessionHasNoErrors();
    $this->actingAs($unverified)->post(route('verification.send'));

    Notification::assertSentTo($unverified, QueuedVerifyEmail::class, fn ($notification) => $notification instanceof ShouldQueue);
    Notification::assertSentTo($user, QueuedResetPassword::class, fn ($notification) => $notification instanceof ShouldQueue);
});
