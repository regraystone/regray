<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page from workspace pages', function () {
    $workspaceRoutes = [
        'overview.index',
        'saved.index',
        'sales.index',
        'quote.index',
        'inquiry.index',
        'schedule.index',
        'message.index',
        'stones.index',
        'services.index',
        'accounts.index',
    ];

    foreach ($workspaceRoutes as $name) {
        $this->get(route($name))->assertRedirect(route('login'));
    }

    $this->get(route('home.show', 'patagonia-quartzite'))
        ->assertRedirect(route('login'));
});

test('authenticated users can visit every workspace page', function () {
    $workspaceRoutes = [
        'overview.index',
        'saved.index',
        'sales.index',
        'quote.index',
        'inquiry.index',
        'schedule.index',
        'message.index',
        'stones.index',
        'services.index',
        'accounts.index',
    ];

    $user = User::factory()->create();

    foreach ($workspaceRoutes as $name) {
        $this->actingAs($user)->get(route($name))->assertOk();
    }
});

test('authenticated users can visit a stone profile page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('home.show', 'patagonia-quartzite'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('stone-profile')
                ->has('stoneId'),
        );
});
