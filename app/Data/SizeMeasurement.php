<?php

namespace App\Data;

use App\Enums\SizeUnit;

/**
 * A slab's width and length in one unit, as it is offered (`sizes`) and as a
 * client asks for it on a stone row (`inquiry_stones.slab_*`).
 *
 * The form sends one string per row ("160 x 320 cm") so the dropdown of a
 * stone's own sizes and the free entry behind Other answer the same way;
 * parse() is the server's reading of it, and label() writes it back. Case and
 * spacing are the client's own ("160X320CM" is the same size); the numbers are
 * not, so a value too precise or too large for the decimal(8,2) columns is
 * refused rather than rounded.
 */
final readonly class SizeMeasurement
{
    /** The largest side a decimal(8,2) column holds. */
    public const MAX = 999999.99;

    public function __construct(
        public string $width,
        public string $length,
        public SizeUnit $unit,
    ) {}

    /**
     * The size a client wrote, or null when it is not "width x length unit"
     * with both sides above 0 and within the columns (problem() says which).
     */
    public static function parse(string $value): ?self
    {
        $parts = self::parts($value);

        if ($parts === null || self::problem($value) !== null) {
            return null;
        }

        return new self(Measurement::number($parts[0]), Measurement::number($parts[1]), SizeUnit::from(strtolower($parts[2])));
    }

    /**
     * Why a written size is refused, or null when it stands: `format` when it
     * is not "width x length unit", `zero` when a side is 0, `max` when a side
     * is too large for the column.
     *
     * @return 'format'|'zero'|'max'|null
     */
    public static function problem(string $value): ?string
    {
        $parts = self::parts($value);

        if ($parts === null) {
            return 'format';
        }

        foreach ([$parts[0], $parts[1]] as $side) {
            if ((float) $side <= 0) {
                return 'zero';
            }

            if ((float) $side > self::MAX) {
                return 'max';
            }
        }

        return null;
    }

    /** @return array{0: string, 1: string, 2: string}|null width, length and unit as written */
    private static function parts(string $value): ?array
    {
        $number = Measurement::NUMBER_PATTERN;
        $units = implode('|', SizeUnit::values());

        return preg_match("/^({$number})\\s*x\\s*({$number})\\s*({$units})$/i", trim($value), $parts) === 1
            ? [$parts[1], $parts[2], $parts[3]]
            : null;
    }

    /**
     * A size in the URL form a filter link carries ("160x320-cm"), or null
     * when the key is not one.
     */
    public static function fromKey(string $key): ?self
    {
        $number = Measurement::NUMBER_PATTERN;
        $units = implode('|', SizeUnit::values());

        if (preg_match("/^({$number})x({$number})-({$units})$/", $key, $parts) !== 1) {
            return null;
        }

        return new self(Measurement::number($parts[1]), Measurement::number($parts[2]), SizeUnit::from($parts[3]));
    }

    /** "160 x 320 cm". */
    public function label(): string
    {
        return "{$this->width} x {$this->length} {$this->unit->label()}";
    }

    /** "160x320-cm": stable in a URL, and the same for 160 and 160.00. */
    public function key(): string
    {
        return "{$this->width}x{$this->length}-{$this->unit->value}";
    }
}
