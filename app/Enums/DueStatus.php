<?php

namespace App\Enums;

use Carbon\CarbonInterface;

/**
 * How an inquiry stands against its deadline, the date its schedule says the
 * project is needed by. Read by the day in the studio's zone, so a project
 * needed today is due soon rather than overdue, whatever the hour.
 *
 * While the project is open:
 * - `overdue`: the deadline's date has passed.
 * - `due_soon`: it is today or within the next DUE_SOON_DAYS days.
 * - `on_track`: it is further off than that.
 *
 * Once completed, it is settled by the day it was completed: `on_track` on or
 * before the deadline's date, `overdue` after it, and never `due_soon`.
 */
enum DueStatus: string
{
    case OnTrack = 'on_track';
    case DueSoon = 'due_soon';
    case Overdue = 'overdue';

    /** How many days before the deadline an inquiry counts as due soon. */
    public const DUE_SOON_DAYS = 7;

    public static function forDeadline(CarbonInterface $deadline, CarbonInterface $now, string $timezone, ?CarbonInterface $completedAt = null): self
    {
        $daysLeft = (int) ($completedAt ?? $now)->setTimezone($timezone)->startOfDay()
            ->diffInDays($deadline->setTimezone($timezone)->startOfDay(), false);

        return match (true) {
            $daysLeft < 0 => self::Overdue,
            $completedAt !== null || $daysLeft > self::DUE_SOON_DAYS => self::OnTrack,
            default => self::DueSoon,
        };
    }
}
