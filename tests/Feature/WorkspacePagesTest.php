<?php

use App\Models\User;

test('guests are redirected to the login page from workspace pages', function () {
    $workspaceRoutes = [
        'overview.index',
        'saved.index',
        'sales.index',
        'quotation.index',
        'inquiry.index',
        'schedule.index',
        'message.index',
        'products.index',
        'services.index',
        'accounts.index',
    ];

    foreach ($workspaceRoutes as $name) {
        $this->get(route($name))->assertRedirect(route('login'));
    }
});

test('authenticated users can visit every workspace page', function () {
    $workspaceRoutes = [
        'overview.index',
        'saved.index',
        'sales.index',
        'quotation.index',
        'inquiry.index',
        'schedule.index',
        'message.index',
        'products.index',
        'services.index',
        'accounts.index',
    ];

    $user = User::factory()->create();

    foreach ($workspaceRoutes as $name) {
        $this->actingAs($user)->get(route($name))->assertOk();
    }
});
