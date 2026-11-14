<?php

namespace App\Enums;

/**
 * The unit a slab's width and length are measured in: a stone's listed size
 * (`sizes.unit`) and the one a client asks for on a stone row
 * (`inquiry_stones.slab_unit`). Both sides share one unit, as a size is
 * written: "160 x 320 cm", "5 x 10 ft".
 */
enum SizeUnit: string
{
    case Centimetre = 'cm';
    case Metre = 'm';
    case Inch = 'in';
    case Foot = 'ft';

    /** How the unit is written after the two sides: "160 x 320 cm". */
    public function label(): string
    {
        return $this->value;
    }

    /**
     * Where the unit's options come in a list: metric first, as the case
     * order says. A size's default `sort_order`, so an option list reads by
     * unit, then by number.
     */
    public function position(): int
    {
        return (int) array_search($this, self::cases(), true);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
