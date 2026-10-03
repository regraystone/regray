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
        Role::Client->value => '/home',
        Role::Admin->value => '/overview',
        Role::Owner->value => '/overview',
        Role::Acct->value => '/overview',
        Role::Tech->value => '/overview',
        Role::Mktg->value => '/overview',
        Role::Logi->value => '/overview',
        Role::Sales->value => '/overview',
    ],

    'default' => '/overview',
];
