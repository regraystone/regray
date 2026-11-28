<?php

namespace App\Data;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * The dates the Overview's figures cover: From and To, whole days on the
 * studio's clock. By default the first of this month to today.
 *
 * A bad date falls back to its default, as a bad filter on any list is no
 * filter: To is never after today, and From never after To.
 *
 * `previous()` is as many days just before, for "▲ 3 vs the 7 days before".
 */
final readonly class OverviewRange
{
    public function __construct(
        /** The start of From's day. */
        public CarbonImmutable $from,
        /** The end of To's day. */
        public CarbonImmutable $to,
        public string $timezone,
    ) {}

    public static function fromQuery(mixed $from, mixed $to, CarbonImmutable $now, string $timezone): self
    {
        $today = $now->setTimezone($timezone)->startOfDay();
        $end = self::date($to, $timezone) ?? $today;
        $end = $end->greaterThan($today) ? $today : $end;
        $start = self::date($from, $timezone) ?? $today->startOfMonth();
        $start = $start->greaterThan($end) ? $end : $start;

        return new self($start, $end->endOfDay(), $timezone);
    }

    /** How many days it covers, both ends included. */
    public function days(): int
    {
        return (int) $this->from->diffInDays($this->to->startOfDay()) + 1;
    }

    /** As many days, ending the day before From. */
    public function previous(): self
    {
        $end = $this->from->subDay();

        return new self($end->subDays($this->days() - 1)->startOfDay(), $end->endOfDay(), $this->timezone);
    }

    /**
     * How the trend chart groups it: by day up to a month, by week up to six
     * months, by month after that.
     */
    public function bucket(): string
    {
        return match (true) {
            $this->days() <= 31 => 'day',
            $this->days() <= 184 => 'week',
            default => 'month',
        };
    }

    /**
     * The start of every bucket the chart shows, oldest first, as `Y-m-d`.
     *
     * @return list<string>
     */
    public function buckets(): array
    {
        $unit = $this->bucket();
        $cursor = match ($unit) {
            'week' => $this->from->startOfWeek(CarbonImmutable::MONDAY),
            'month' => $this->from->startOfMonth(),
            default => $this->from,
        };
        $starts = [];

        while ($cursor->lessThanOrEqualTo($this->to)) {
            $starts[] = $cursor->toDateString();
            $cursor = $cursor->add(1, $unit);
        }

        return $starts;
    }

    /** @return array{from: string, to: string, days: int, bucket: string, previousFrom: string, previousTo: string} */
    public function toArray(): array
    {
        $previous = $this->previous();

        return [
            'from' => $this->from->toDateString(),
            'to' => $this->to->toDateString(),
            'days' => $this->days(),
            'bucket' => $this->bucket(),
            'previousFrom' => $previous->from->toDateString(),
            'previousTo' => $previous->to->toDateString(),
        ];
    }

    private static function date(mixed $value, string $timezone): ?CarbonImmutable
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, $timezone);
        } catch (Throwable) {
            return null;
        }

        // "2026-02-31" rolls over to March: not a date.
        return $date instanceof CarbonImmutable && $date->toDateString() === $value ? $date : null;
    }
}
