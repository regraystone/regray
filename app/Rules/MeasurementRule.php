<?php

namespace App\Rules;

use App\Data\SizeMeasurement;
use App\Data\ThicknessMeasurement;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A stone row's slab size or thickness, as the form sends it: one string,
 * "160 x 320 cm" or "20 mm", read by SizeMeasurement / ThicknessMeasurement.
 *
 * Whether it is one of the stone's own options is deliberately not checked:
 * the dropdown lists them, but Other takes any size or thickness, so a
 * well-formed value always stands. The messages are in the page's words, and
 * inquiry-schema.ts says the same.
 */
final class MeasurementRule implements ValidationRule
{
    private function __construct(private readonly string $kind) {}

    public static function slabSize(): self
    {
        return new self('size');
    }

    public static function thickness(): self
    {
        return new self('thickness');
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $written = is_string($value) || is_int($value) || is_float($value) ? (string) $value : '';
        $problem = $this->kind === 'size' ? SizeMeasurement::problem($written) : ThicknessMeasurement::problem($written);

        if ($problem !== null) {
            $fail($this->message($problem));
        }
    }

    /** @param  'format'|'zero'|'max'  $problem */
    private function message(string $problem): string
    {
        return $this->kind === 'size'
            ? match ($problem) {
                'format' => 'Enter the slab size as width x length and a unit, like 160 x 320 cm.',
                'zero' => 'Enter a slab size greater than 0.',
                'max' => 'Enter a smaller slab size.',
            }
        : match ($problem) {
            'format' => 'Enter the thickness as a number and a unit, like 20 mm.',
            'zero' => 'Enter a thickness greater than 0.',
            'max' => 'Enter a smaller thickness.',
        };
    }
}
