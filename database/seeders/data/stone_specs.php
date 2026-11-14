<?php

/*
 * Regray's measured slab sizes, and the fixed specifications of the stones
 * not sold as measured slabs, keyed by stone type slug, then by the stone's
 * name as data/stones.php writes it (before StoneSeeder tells duplicate
 * names apart, so Monumental's "Gray" is not Cobblestone's).
 *
 * A measured size is height x length in metres, as Regray measured the slab,
 * kept in that order (`[first, second, unit]`) so its label reads "3.13 x
 * 1.97 m". One size measured on two stones is one `sizes` row.
 *
 * `mode`:
 * - `add`: the measured size is added to the stone's generated slabs, cut in
 *   every one of the stone's generated thicknesses, and listed first;
 * - `replace`: `variants` (`[size or null, [value, unit]]`) are the stone's
 *   only slabs. Crazy Cut is sold as irregular pieces, so it has no size,
 *   only thicknesses and a `size_note` saying how big the pieces run;
 *   Cobblestone's Warm Beige Granite comes in its two cut sizes.
 *
 * A stone not listed here keeps its generated slabs only. StoneSeeder refuses
 * an entry that names no stone.
 *
 * @var array<string, array<string, array{mode: 'add', size: array{0: string, 1: string, 2: string}}|array{mode: 'replace', variants: list<array{0: array{0: int|float|string, 1: int|float|string, 2: string}|null, 1: array{0: int|float|string, 1: string}}>, size_note?: string}>>
 */

$crazyCut = [
    'mode' => 'replace',
    'variants' => [
        [null, [2, 'cm']],
        [null, [3, 'cm']],
        [null, [4, 'cm']],
    ],
    'size_note' => 'Diameter 15–40 cm',
];

return [
    'patagonia-quartzite' => [
        'Arctic Pink' => ['mode' => 'add', 'size' => ['3.13', '1.97', 'm']],
        'Follaje Rosa II' => ['mode' => 'add', 'size' => ['3.42', '2.01', 'm']],
        'Ora Luz' => ['mode' => 'add', 'size' => ['3.33', '1.94', 'm']],
        'Glacier White' => ['mode' => 'add', 'size' => ['2.95', '2.11', 'm']],
        'Palazza Bianco' => ['mode' => 'add', 'size' => ['3.47', '2.16', 'm']],
        'La Luz' => ['mode' => 'add', 'size' => ['3.24', '2.06', 'm']],
        'Veymont Green' => ['mode' => 'add', 'size' => ['3.14', '1.98', 'm']],
        'Salmon Pink' => ['mode' => 'add', 'size' => ['3.16', '1.94', 'm']],
        'Volere II' => ['mode' => 'add', 'size' => ['3.16', '1.91', 'm']],
        'Piazza Royale' => ['mode' => 'add', 'size' => ['3.26', '2.12', 'm']],
        'Bellagio Amber' => ['mode' => 'add', 'size' => ['3.28', '2.05', 'm']],
        'Imperial Gold' => ['mode' => 'add', 'size' => ['3.03', '1.98', 'm']],
    ],
    'quartzite' => [
        'Imperial Green' => ['mode' => 'add', 'size' => ['2.81', '1.71', 'm']],
        'Avocado Green III' => ['mode' => 'add', 'size' => ['3.00', '1.97', 'm']],
        'Colorful Impression' => ['mode' => 'add', 'size' => ['3.45', '1.87', 'm']],
        'Picasso' => ['mode' => 'add', 'size' => ['3.33', '1.99', 'm']],
        'Taj Mahal (Polished)' => ['mode' => 'add', 'size' => ['2.96', '1.96', 'm']],
        'Taj Mahal (leather)' => ['mode' => 'add', 'size' => ['2.95', '1.89', 'm']],
        'Macabus White' => ['mode' => 'add', 'size' => ['3.20', '1.96', 'm']],
        'Orion' => ['mode' => 'add', 'size' => ['3.48', '1.97', 'm']],
        'Lumia' => ['mode' => 'add', 'size' => ['3.15', '1.98', 'm']],
        'Ocean Blue (Leather)' => ['mode' => 'add', 'size' => ['3.42', '1.97', 'm']],
        'Selene' => ['mode' => 'add', 'size' => ['3.25', '1.98', 'm']],
        'Bulgari White' => ['mode' => 'add', 'size' => ['3.14', '1.88', 'm']],
        'Mont Blanc' => ['mode' => 'add', 'size' => ['3.14', '1.98', 'm']],
    ],
    'onyx' => [
        'Pardo Brown I' => ['mode' => 'add', 'size' => ['2.50', '1.60', 'm']],
        'Pardo Brown II' => ['mode' => 'add', 'size' => ['2.84', '1.67', 'm']],
        'Classical Green' => ['mode' => 'add', 'size' => ['3.14', '1.73', 'm']],
        'Sienna White' => ['mode' => 'add', 'size' => ['2.98', '1.99', 'm']],
        'Royal White III' => ['mode' => 'add', 'size' => ['2.99', '1.70', 'm']],
        'Arancia' => ['mode' => 'add', 'size' => ['2.99', '1.70', 'm']],
        'Prisma II' => ['mode' => 'add', 'size' => ['2.93', '1.56', 'm']],
        'Baltico Blue III' => ['mode' => 'add', 'size' => ['2.70', '1.98', 'm']],
        'Ikarus White' => ['mode' => 'add', 'size' => ['2.93', '1.99', 'm']],
        'Golden Pearl Onyx' => ['mode' => 'add', 'size' => ['2.98', '1.90', 'm']],
    ],
    'travertine' => [
        'White' => ['mode' => 'add', 'size' => ['2.77', '1.60', 'm']],
        'Beige' => ['mode' => 'add', 'size' => ['2.80', '1.55', 'm']],
        'Silver' => ['mode' => 'add', 'size' => ['3.18', '2.00', 'm']],
    ],
    'marble' => [
        'Cippolino Ondualto II' => ['mode' => 'add', 'size' => ['2.96', '1.72', 'm']],
        'Verdi Alpi' => ['mode' => 'add', 'size' => ['2.87', '1.66', 'm']],
        'Rosso Levanto' => ['mode' => 'add', 'size' => ['2.60', '1.70', 'm']],
        'Emperador' => ['mode' => 'add', 'size' => ['3.16', '1.53', 'm']],
        'Nero Marquina' => ['mode' => 'add', 'size' => ['2.75', '1.65', 'm']],
        'Fendi Blu Gray' => ['mode' => 'add', 'size' => ['2.30', '1.94', 'm']],
        'Panda White' => ['mode' => 'add', 'size' => ['3.42', '2.00', 'm']],
        'Calacatta Viola I' => ['mode' => 'add', 'size' => ['1.75', '1.55', 'm']],
        'Calacatta Viola II' => ['mode' => 'add', 'size' => ['2.27', '1.80', 'm']],
        'Arabescato White' => ['mode' => 'add', 'size' => ['3.07', '2.01', 'm']],
        'Calacatta' => ['mode' => 'add', 'size' => ['2.18', '1.60', 'm']],
        'Carara White' => ['mode' => 'add', 'size' => ['2.44', '1.62', 'm']],
        'Star White' => ['mode' => 'add', 'size' => ['2.81', '1.73', 'm']],
        'Shangrila Pearl White' => ['mode' => 'add', 'size' => ['3.18', '1.88', 'm']],
        'Golden Black Marquina' => ['mode' => 'add', 'size' => ['2.92', '1.87', 'm']],
        'Crema Marfil' => ['mode' => 'add', 'size' => ['2.58', '1.40', 'm']],
        'Laurent Black' => ['mode' => 'add', 'size' => ['2.97', '1.83', 'm']],
        'Burberry Pink' => ['mode' => 'add', 'size' => ['1.99', '1.97', 'm']],
    ],
    'granite' => [
        'Golden Fusion' => ['mode' => 'add', 'size' => ['3.16', '1.90', 'm']],
        'Cosmic Black' => ['mode' => 'add', 'size' => ['3.15', '1.98', 'm']],
    ],
    'monumental' => [
        'Gray' => ['mode' => 'add', 'size' => ['1.20', '0.60', 'm']],
        'Limestone II' => ['mode' => 'add', 'size' => ['2.50', '1.60', 'm']],
        'Gold Vein Limetone' => ['mode' => 'add', 'size' => ['0.60', '0.30', 'm']],
        'Flamed + Brushed - Black' => ['mode' => 'add', 'size' => ['1.20', '0.60', 'm']],
        'Flamed - Gray' => ['mode' => 'add', 'size' => ['1.20', '0.60', 'm']],
        'Misty Beige' => ['mode' => 'add', 'size' => ['1.20', '0.60', 'm']],
    ],
    'crazy-cut' => [
        'Bronze Sand Granite' => $crazyCut,
        'Crystal Beige Marble' => $crazyCut,
        'Crystal Gray Marble' => $crazyCut,
        'Crystal White Marble' => $crazyCut,
        'Ivory Limestone' => $crazyCut,
        'Nimbus Granite' => $crazyCut,
        'Shutter Bronze Granite' => $crazyCut,
        'Shutter Grey Granite' => $crazyCut,
    ],
    'cobblestone' => [
        'Warm Beige Granite' => [
            'mode' => 'replace',
            'variants' => [
                [[43, 43, 'cm'], [2, 'cm']],
                [[10, 10, 'cm'], [2, 'cm']],
            ],
        ],
    ],
];
