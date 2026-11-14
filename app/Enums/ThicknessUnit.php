<?php

namespace App\Enums;

/**
 * The unit a thickness is measured in: a stone's listed thickness
 * (`thicknesses.unit`) and the one a client asks for on a stone row
 * (`inquiry_stones.thickness_unit`). Slabs are mostly cut in millimetres,
 * but a supplier quoting from abroad may list centimetres or inches, and the
 * client may answer in whichever they were given.
 */
enum ThicknessUnit: string
{
    case Millimetre = 'mm';
    case Centimetre = 'cm';
    case Inch = 'in';

    /** How the unit is written after a number: "20 mm", "0.75 in". */
    public function label(): string
    {
        return $this->value;
    }

    /**
     * Where the unit's options come in a list: metric first, as the case
     * order says. A thickness's default `sort_order`, so an option list reads by
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
