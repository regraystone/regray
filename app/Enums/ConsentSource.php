<?php

namespace App\Enums;

/** Where a `user_consents` row was given: the sign-up form or the user's own settings. */
enum ConsentSource: string
{
    case Registration = 'registration';
    case Settings = 'settings';
}
