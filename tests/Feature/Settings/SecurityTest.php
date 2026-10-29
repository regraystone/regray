<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Fortify\Features;

/*
 * The account settings' own guards. The rest of the settings pages are the
 * starter kit's and Fortify's.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('asks for the password again before the security page opens', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true]);

    $this->actingAs(User::factory()->create())
        ->get(route('security.edit'))
        ->assertRedirect(route('password.confirm'));
});

test('changes the password only with the current one', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
        ->assertSessionHasErrors('current_password');

    expect(Hash::check('password', $user->refresh()->password))->toBeTrue();
});

test('deletes the account only with the password', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), ['password' => 'wrong-password'])
        ->assertSessionHasErrors('password');

    expect($user->fresh())->not->toBeNull();

    $this->actingAs($user)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertSessionHasNoErrors();

    $this->assertGuest();
    expect($user->fresh())->toBeNull();
});
