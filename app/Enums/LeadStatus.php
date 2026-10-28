<?php

namespace App\Enums;

/**
 * A lead's status: idle until marketing has engaged the client (won) or dropped
 * them (lost). Won and lost are final. Never an inquiry status: a lost lead
 * shows its inquiries as cancelled, and a won lead starts their sales.
 */
enum LeadStatus: string
{
    case Idle = 'idle';
    case Won = 'won';
    case Lost = 'lost';

    /** Whether the client's new inquiries still link to this lead. */
    public function isOpen(): bool
    {
        return $this !== self::Lost;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
