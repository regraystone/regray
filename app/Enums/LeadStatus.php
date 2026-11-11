<?php

namespace App\Enums;

/**
 * Marketing's decision on an inquiry (`inquiries.marketing_status`): won
 * starts its sales, lost shows it cancelled, and both are final. Idle is an
 * inquiry still waiting for one, which is stored as NULL. A lead shows the
 * tally of its inquiries' decisions, and its log opens at idle.
 */
enum LeadStatus: string
{
    case Idle = 'idle';
    case Won = 'won';
    case Lost = 'lost';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** The badge's words, as in lib/lead-statuses.ts. */
    public function label(): string
    {
        return match ($this) {
            self::Idle => 'Idle',
            self::Won => 'Won',
            self::Lost => 'Lost',
        };
    }
}
