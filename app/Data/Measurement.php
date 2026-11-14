<?php

namespace App\Data;

/**
 * How a measured number is written, shared by sizes and thicknesses so a
 * stone's listed option, a client's answer and every page that shows them
 * spell the same value the same way: no trailing zeros ("160", "0.75",
 * "1.6"), a dot for decimals, never a thousands separator.
 *
 * The database holds these as decimals with two places, so "160.00" is read
 * back for 160; writing it from here is what keeps "160 x 320 cm" from
 * becoming "160.00 x 320.00 cm" on the way to the page.
 */
final class Measurement
{
    /** A positive number with at most two decimals, as the decimal columns hold it. */
    public const NUMBER_PATTERN = '\d+(?:\.\d{1,2})?';

    public static function number(string|int|float $value): string
    {
        $fixed = number_format((float) $value, 2, '.', '');

        return rtrim(rtrim($fixed, '0'), '.');
    }
}
