<?php

namespace App\Enums;

use Carbon\CarbonImmutable;

/**
 * The span the Sales page's figures cover: contracts won, cash collected,
 * refunds, the win rate and contract value by service. What is owed and the
 * open quotes are as of now, whatever the span.
 */
enum SalesPeriod: string
{
    case ThisMonth = 'this_month';
    case Last3Months = 'last_3_months';
    case ThisYear = 'this_year';
    case All = 'all';

    public const DEFAULT = self::ThisYear;

    public function label(): string
    {
        return match ($this) {
            self::ThisMonth => 'This month',
            self::Last3Months => 'Last 3 months',
            self::ThisYear => 'This year',
            self::All => 'All time',
        };
    }

    /**
     * Where the span starts, at midnight in the studio's timezone, or null
     * for all time. It runs until now.
     */
    public function startsAt(CarbonImmutable $now): ?CarbonImmutable
    {
        return match ($this) {
            self::ThisMonth => $now->startOfMonth(),
            self::Last3Months => $now->startOfMonth()->subMonths(2),
            self::ThisYear => $now->startOfYear(),
            self::All => null,
        };
    }
}
