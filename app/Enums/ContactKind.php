<?php

namespace App\Enums;

/** How a direct client can be reached: one `client_contacts` row each. */
enum ContactKind: string
{
    case Phone = 'phone';
    case Viber = 'viber';
    case WhatsApp = 'whatsapp';
    case Email = 'email';

    /**
     * The value as it is stored and compared: an email trimmed and lowercased,
     * a number without spaces, dashes, dots or brackets. Two ways of writing
     * the same contact are one contact.
     */
    public function normalise(string $value): string
    {
        $value = trim($value);

        return $this === self::Email
            ? mb_strtolower($value)
            : (string) preg_replace('/[\s\-().]/', '', $value);
    }

    public function label(): string
    {
        return match ($this) {
            self::Phone => 'Phone',
            self::Viber => 'Viber',
            self::WhatsApp => 'WhatsApp',
            self::Email => 'Email',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
