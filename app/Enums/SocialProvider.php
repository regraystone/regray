<?php

namespace App\Enums;

/** An outside account a user may sign in with (`social_accounts.provider`). */
enum SocialProvider: string
{
    case Google = 'google';

    public function label(): string
    {
        return match ($this) {
            self::Google => 'Google',
        };
    }

    /** Whether the app has this provider's client id and secret. */
    public function isConfigured(): bool
    {
        return filled(config("services.{$this->value}.client_id"))
            && filled(config("services.{$this->value}.client_secret"));
    }
}
