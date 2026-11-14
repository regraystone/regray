<?php

/*
 * The questions a service can ask besides the stones, in the order they are
 * listed, with their listed answers. Which services ask which, and in what
 * order, is `fields` in data/services.php.
 *
 * `scope` is where it is answered: `stone` on each stone row, `project` once
 * under "Other details". `allows_custom` takes a value besides the listed
 * ones. Edge profile and finish list the active rows of their own tables, so
 * they have no `options` here. Slab size and thickness have none either: each
 * stone row lists the sizes and thicknesses its own stone comes in
 * (`size_stone`, `stone_thickness`), and Other takes any width x length or
 * thickness with its unit. Waterfall effect and fascia are yes or no.
 *
 * Each option is `[slug, name]`. `suggestions` are sample values shown beside
 * a free entry and never checked against; `options` are closed, picked from
 * the list, and the one marked `links_to_stone` is "Same as stone".
 *
 * @var list<array{slug: string, name: string, scope: string, answer_type: string, allows_custom: bool, suggestions?: list<array{0: string, 1: string}>, options?: list<array{0: string, 1: string, links_to_stone?: bool}>}>
 */
return [
    [
        'slug' => 'quantity',
        'name' => 'How many pieces?',
        'scope' => 'stone',
        'answer_type' => 'number',
        'allows_custom' => true,
    ],
    [
        'slug' => 'slab_size',
        'name' => 'Slab size',
        'scope' => 'stone',
        'answer_type' => 'text',
        'allows_custom' => true,
    ],
    [
        'slug' => 'thickness',
        'name' => 'Thickness',
        'scope' => 'stone',
        'answer_type' => 'text',
        'allows_custom' => true,
    ],
    [
        'slug' => 'edge_profile',
        'name' => 'Edge profile',
        'scope' => 'stone',
        'answer_type' => 'choice',
        'allows_custom' => false,
    ],
    [
        'slug' => 'finish',
        'name' => 'Finish',
        'scope' => 'stone',
        'answer_type' => 'choice',
        'allows_custom' => false,
    ],
    [
        'slug' => 'area_sqm',
        'name' => 'Project area (sqm)',
        'scope' => 'project',
        'answer_type' => 'number',
        'allows_custom' => true,
        'suggestions' => [
            ['1', '1'],
            ['2', '2'],
            ['5', '5'],
            ['10', '10'],
        ],
    ],
    [
        'slug' => 'size',
        'name' => 'Furniture size',
        'scope' => 'project',
        'answer_type' => 'text',
        'allows_custom' => true,
        'suggestions' => [
            ['60x60', '60 x 60 cm'],
            ['80x80', '80 x 80 cm'],
            ['100x100', '100 x 100 cm'],
            ['120x120', '120 x 120 cm'],
            ['150x150', '150 x 150 cm'],
        ],
    ],
    [
        'slug' => 'seaters',
        'name' => 'Number of seaters',
        'scope' => 'project',
        'answer_type' => 'number',
        'allows_custom' => true,
        'suggestions' => [
            ['2', '2'],
            ['4', '4'],
            ['6', '6'],
            ['8', '8'],
            ['10', '10'],
            ['12', '12'],
        ],
    ],
    [
        'slug' => 'table_base',
        'name' => 'Table base',
        'scope' => 'project',
        'answer_type' => 'choice',
        'allows_custom' => false,
        'options' => [
            ['wood', 'Wood'],
            ['steel', 'Steel'],
            ['same-as-stone', 'Same as stone', 'links_to_stone' => true],
        ],
    ],
    [
        'slug' => 'waterfall_effect',
        'name' => 'Waterfall effect',
        'scope' => 'project',
        'answer_type' => 'boolean',
        'allows_custom' => false,
    ],
    [
        'slug' => 'fascia',
        'name' => 'Fascia',
        'scope' => 'project',
        'answer_type' => 'boolean',
        'allows_custom' => false,
    ],
];
