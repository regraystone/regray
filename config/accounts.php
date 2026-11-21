<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default password
    |--------------------------------------------------------------------------
    |
    | The temporary password every account made on the admin's Accounts page
    | starts with (AccountService::create()). It is set on the server only:
    | the Add account form shows it read-only and never sends it, and the
    | holder must choose their own on their first sign-in
    | (EnsurePasswordChanged), which may not be this one.
    |
    */

    'default_password' => env('ACCOUNTS_DEFAULT_PASSWORD', 'Regray100$'),

];
