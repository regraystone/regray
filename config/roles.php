<?php

use App\Enums\Role;

return [
    /*
     * Where each role lands after signing in.
     *
     * A role that is missing here falls through to `default` below, which is
     * also where a user with no role at all ends up.
     */
    'redirects' => [
        Role::Client->value => '/dashboard/home',
        Role::Admin->value => '/dashboard/overview',
        Role::Owner->value => '/dashboard/overview',
        Role::Accounting->value => '/dashboard/overview',
        Role::Technical->value => '/dashboard/overview',
        Role::Marketing->value => '/dashboard/overview',
        Role::Logistics->value => '/dashboard/overview',
        Role::Sales->value => '/dashboard/overview',
    ],

    'default' => '/dashboard/overview',
];
