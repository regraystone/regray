<?php

namespace App\Enums;

/**
 * Whether a client has a Regray account: `platform` when they do (the
 * client record has a `user_id`), `direct` when they do not, a walk-in staff
 * took down by hand.
 */
enum ClientKind: string
{
    case Platform = 'platform';
    case Direct = 'direct';

    public function label(): string
    {
        return match ($this) {
            self::Platform => 'Platform',
            self::Direct => 'Direct',
        };
    }
}
