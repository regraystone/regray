<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * A place name folded for searching: lowercase, without accents, without the
 * words a name is written with or without ("City of", "Barangay", "Pob."),
 * and with its punctuation as spaces. "City of Las Piñas" and "las pinas"
 * fold to the same thing.
 *
 * resources/js/lib/ph-address.ts folds names the same way, and
 * scripts/build-psgc-data.py does for matching ZIP codes.
 */
final class PhPlaceName
{
    public static function fold(string $name): string
    {
        $text = Str::lower(Str::ascii($name));
        $text = (string) preg_replace('/\(.*?\)/', ' ', $text);
        $text = (string) preg_replace('/\b(city of|province of|municipality of|barangay|brgy\.?|pob\.?|poblacion)\b/', ' ', $text);
        $text = (string) preg_replace('/\bcity\b/', ' ', $text);
        $text = (string) preg_replace('/\bsta\.?(?=\s|$)/', 'santa', $text);
        $text = (string) preg_replace('/\bsto\.?(?=\s|$)/', 'santo', $text);
        $text = (string) preg_replace('/[^a-z0-9]+/', ' ', $text);

        return trim((string) preg_replace('/\s+/', ' ', $text));
    }

    /**
     * What a search box's text has to contain, folded the same way, for
     * `search_name LIKE`. Kept apart from fold() because a half-typed "cit"
     * is not yet a word to drop: only a filler word typed out in full goes,
     * one followed by more text ("City of Ma"), or a "city" after a name
     * ("Quezon City"). LIKE's own wildcards are escaped.
     */
    public static function searchPattern(string $query): string
    {
        $text = Str::lower(Str::ascii($query));
        $text = (string) preg_replace('/\b(city of|province of|municipality of|barangay|brgy\.?|pob\.?|poblacion)\s/', ' ', $text);
        $text = (string) preg_replace('/\bcity\s/', ' ', $text);
        $text = (string) preg_replace('/(?<=[a-z0-9])\s+city\s*$/', '', $text);
        $text = trim((string) preg_replace('/\s+/', ' ', (string) preg_replace('/[^a-z0-9]+/', ' ', $text)));

        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $text).'%';
    }
}
