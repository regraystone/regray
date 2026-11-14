<?php

use App\Data\Measurement;
use App\Data\SizeMeasurement;
use App\Data\ThicknessMeasurement;
use App\Enums\SizeUnit;
use App\Enums\ThicknessUnit;

/*
 * How a size or thickness is read from what the form sends, and written back
 * for every page: one reading for the request, the seeders and the labels.
 */

it('writes a measured number without trailing zeros', function (string|int|float $value, string $written): void {
    expect(Measurement::number($value))->toBe($written);
})->with([
    ['160.00', '160'],
    ['0.75', '0.75'],
    ['1.60', '1.6'],
    [20, '20'],
    [3.2, '3.2'],
]);

it('reads a slab size in every unit, whatever its case and spacing', function (string $written, string $label, string $key, SizeUnit $unit): void {
    $size = SizeMeasurement::parse($written);

    expect($size?->label())->toBe($label)
        ->and($size?->key())->toBe($key)
        ->and($size?->unit)->toBe($unit);
})->with([
    ['160 x 320 cm', '160 x 320 cm', '160x320-cm', SizeUnit::Centimetre],
    ['160X320CM', '160 x 320 cm', '160x320-cm', SizeUnit::Centimetre],
    ['  1.6 x 3.20 m ', '1.6 x 3.2 m', '1.6x3.2-m', SizeUnit::Metre],
    ['63 x 126 in', '63 x 126 in', '63x126-in', SizeUnit::Inch],
    ['5x10 Ft', '5 x 10 ft', '5x10-ft', SizeUnit::Foot],
]);

it('refuses a slab size that is not width x length and a unit within the columns', function (string $written, string $problem): void {
    expect(SizeMeasurement::parse($written))->toBeNull()
        ->and(SizeMeasurement::problem($written))->toBe($problem);
})->with([
    'no unit' => ['160x320', 'format'],
    'an unknown unit' => ['160 x 320 yd', 'format'],
    'one side' => ['160 cm', 'format'],
    'not a number' => ['abc', 'format'],
    'too many decimals' => ['160.555 x 320 cm', 'format'],
    'a side of 0' => ['0 x 320 cm', 'zero'],
    'too large' => ['1000000 x 320 cm', 'max'],
]);

it('reads a thickness in every unit', function (string $written, string $label, string $key, ThicknessUnit $unit): void {
    $thickness = ThicknessMeasurement::parse($written);

    expect($thickness?->label())->toBe($label)
        ->and($thickness?->key())->toBe($key)
        ->and($thickness?->unit)->toBe($unit);
})->with([
    ['20 mm', '20 mm', '20-mm', ThicknessUnit::Millimetre],
    ['20MM', '20 mm', '20-mm', ThicknessUnit::Millimetre],
    ['2 cm', '2 cm', '2-cm', ThicknessUnit::Centimetre],
    ['0.75in', '0.75 in', '0.75-in', ThicknessUnit::Inch],
]);

it('refuses a thickness that is not a number and a unit within the column', function (string $written, string $problem): void {
    expect(ThicknessMeasurement::parse($written))->toBeNull()
        ->and(ThicknessMeasurement::problem($written))->toBe($problem);
})->with([
    'no unit' => ['20', 'format'],
    'an unknown unit' => ['20 ft', 'format'],
    'not a number' => ['abc', 'format'],
    'too many decimals' => ['20.555 mm', 'format'],
    '0' => ['0 mm', 'zero'],
    'too large' => ['10000 mm', 'max'],
]);

it('reads back the key a filter link carries, and nothing else', function (): void {
    expect(SizeMeasurement::fromKey('160x320-cm')?->label())->toBe('160 x 320 cm')
        ->and(ThicknessMeasurement::fromKey('0.75-in')?->label())->toBe('0.75 in')
        ->and(SizeMeasurement::fromKey('160 x 320 cm'))->toBeNull()
        ->and(ThicknessMeasurement::fromKey('20mm'))->toBeNull();
});
