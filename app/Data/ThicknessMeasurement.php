<?php

namespace App\Data;

use App\Enums\ThicknessUnit;

/**
 * A thickness in one unit, as it is offered (`thicknesses`) and as a client
 * asks for it on a stone row (`inquiry_stones.thickness*`).
 *
 * The same reading as SizeMeasurement: one string from the form ("20 mm",
 * "0.75 in"), case and spacing free, the number refused rather than rounded
 * when it is not above 0 or does not fit decimal(6,2).
 */
final readonly class ThicknessMeasurement
{
    /** The largest thickness a decimal(6,2) column holds. */
    public const MAX = 9999.99;

    public function __construct(
        public string $value,
        public ThicknessUnit $unit,
    ) {}

    /** The thickness a client wrote, or null when it is not "number unit" within the column. */
    public static function parse(string $value): ?self
    {
        $parts = self::parts($value);

        if ($parts === null || self::problem($value) !== null) {
            return null;
        }

        return new self(Measurement::number($parts[0]), ThicknessUnit::from(strtolower($parts[1])));
    }

    /**
     * Why a written thickness is refused, or null when it stands: `format`
     * when it is not "number unit", `zero` at 0, `max` when too large.
     *
     * @return 'format'|'zero'|'max'|null
     */
    public static function problem(string $value): ?string
    {
        $parts = self::parts($value);

        return match (true) {
            $parts === null => 'format',
            (float) $parts[0] <= 0 => 'zero',
            (float) $parts[0] > self::MAX => 'max',
            default => null,
        };
    }

    /** @return array{0: string, 1: string}|null the number and unit as written */
    private static function parts(string $value): ?array
    {
        $number = Measurement::NUMBER_PATTERN;
        $units = implode('|', ThicknessUnit::values());

        return preg_match("/^({$number})\\s*({$units})$/i", trim($value), $parts) === 1
            ? [$parts[1], $parts[2]]
            : null;
    }

    /** A thickness in the URL form a filter link carries ("20-mm"), or null. */
    public static function fromKey(string $key): ?self
    {
        $number = Measurement::NUMBER_PATTERN;
        $units = implode('|', ThicknessUnit::values());

        if (preg_match("/^({$number})-({$units})$/", $key, $parts) !== 1) {
            return null;
        }

        return new self(Measurement::number($parts[1]), ThicknessUnit::from($parts[2]));
    }

    /** "20 mm", "0.75 in". */
    public function label(): string
    {
        return "{$this->value} {$this->unit->label()}";
    }

    /** "20-mm", "0.75-in". */
    public function key(): string
    {
        return "{$this->value}-{$this->unit->value}";
    }
}
