<?php

namespace App\Data;

use App\Enums\SizeUnit;
use App\Models\DetailFieldOption;

/**
 * A table's size as the Inquire page asks it, by the table shape picked
 * (DetailFieldOption::$size_kind): a round table by its diameter, "Ø 120 cm";
 * a rectangular, square or oval one by width x length, "120 x 80 cm"
 * (SizeMeasurement). A shape with no size kind (organic, custom, not sure)
 * takes the client's own words, so it is not checked here.
 *
 * The answer stays one line of text (`inquiry_detail_answers.text_value`), as
 * every furniture size is; this only checks it is written the way its shape
 * asks. resources/js/lib/inquiry-flow.ts (furnitureSizeProblem) words it the
 * same.
 */
final class FurnitureSize
{
    /** The largest side or diameter, as a slab size column holds it. */
    public const MAX = SizeMeasurement::MAX;

    /**
     * Why a written size does not fit its shape, or null when it does.
     */
    public static function problem(string $sizeKind, string $value): ?string
    {
        if ($sizeKind === DetailFieldOption::SIZE_WIDTH_LENGTH) {
            return match (SizeMeasurement::problem($value)) {
                'format' => 'Enter the size as width x length and a unit, like 120 x 80 cm.',
                'zero' => 'Enter a size greater than 0.',
                'max' => 'Enter a smaller size.',
                default => null,
            };
        }

        $number = Measurement::NUMBER_PATTERN;
        $units = implode('|', SizeUnit::values());

        if (preg_match("/^Ø\\s*({$number})\\s*({$units})$/iu", trim($value), $parts) !== 1) {
            return 'Enter the diameter and a unit, like Ø 120 cm.';
        }

        if ((float) $parts[1] <= 0) {
            return 'Enter a diameter greater than 0.';
        }

        return (float) $parts[1] > self::MAX ? 'Enter a smaller diameter.' : null;
    }
}
