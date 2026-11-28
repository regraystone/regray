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
 * (`size_stone`, `stone_thickness`). A slab size's Other takes any width x
 * length with its unit; a thickness has no Other, and a stone with none
 * lists "To be advised by Regray". Waterfall effect and fascia are yes or no.
 *
 * `suggestions` are sample values shown beside a free entry, each `[slug,
 * name]` or `[slug, name, note]`, and never checked against: the client's
 * answer is the name, and the list reads "name – note" ("2 – approx. 0.6
 * sqm"). `custom_label` is what the entry that opens the free entry reads
 * (none: Other), and `custom_hint` the line under the box it opens. A list question's `choices` are the
 * slugs of the library choices it lists (data/choices.php), in its order:
 * the client picks one, and "Same as stone" is the library's `stone` kind.
 * The Choices page under Services edits a choice; the Questions page picks a
 * question's choices.
 *
 * @var list<array{slug: string, name: string, scope: string, answer_type: string, allows_custom: bool, custom_label?: string, custom_hint?: string, suggestions?: list<array{0: string, 1: string, 2?: string}>, choices?: list<string>}>
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
        'custom_label' => 'Custom size',
        'custom_hint' => 'Enter the width and length you need.',
    ],
    [
        'slug' => 'thickness',
        'name' => 'Thickness',
        'scope' => 'stone',
        'answer_type' => 'text',
        // Picked from the stone's own; a stone with none is left to Regray to advise.
        'allows_custom' => false,
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
        'custom_label' => 'Custom area',
        'custom_hint' => 'Enter your project area in square metres.',
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
        'custom_label' => 'Custom size',
        'custom_hint' => 'Enter your desired size.',
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
        'custom_label' => 'Custom number',
        'custom_hint' => 'Enter how many seats you need.',
        'suggestions' => [
            // 6, 8 and 12 for now, with about how big a table top seats them; any other through Custom number.
            ['6', '6', 'approx. 1.6 sqm'],
            ['8', '8', 'approx. 2.2 sqm'],
            ['12', '12', 'approx. 3.3 sqm'],
        ],
    ],
    [
        'slug' => 'table_shape',
        'name' => 'Table shape',
        'scope' => 'project',
        'answer_type' => 'choice',
        'allows_custom' => false,
        'choices' => [
            'rectangular',
            'round',
            'oval',
            'square',
            'rounded-rectangle',
            'organic',
            'custom-shape',
            'not-sure',
        ],
    ],
    [
        'slug' => 'table_base_design_center',
        'name' => 'Center table base design',
        'scope' => 'project',
        'answer_type' => 'choice',
        'allows_custom' => false,
        'choices' => [
            'sphere-accent-pedestal',
            'u-base',
            'half-moon',
            'custom-design',
            'not-sure',
        ],
    ],
    [
        'slug' => 'table_base_design_side',
        'name' => 'Side table base design',
        'scope' => 'project',
        'answer_type' => 'choice',
        'allows_custom' => false,
        'choices' => [
            'c-legs-v1',
            'c-legs-v2',
            'cylindrical-pedestal',
            'hourglass-v2',
            'custom-design',
            'not-sure',
        ],
    ],
    [
        'slug' => 'table_base_design_dining',
        'name' => 'Dining table base design',
        'scope' => 'project',
        'answer_type' => 'choice',
        'allows_custom' => false,
        'choices' => [
            'diamond',
            'close-cage',
            'tubular-v1',
            'tubular-v2',
            'x-base-v1',
            'x-base-v2',
            'clover-v1',
            'clover-v2',
            'y-base',
            'hourglass-v1',
            'custom-design',
            'not-sure',
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
    [
        // Whether a wall's slabs mirror each other's veins, and how; a custom layout is described.
        'slug' => 'bookmatch',
        'name' => 'Bookmatch',
        'scope' => 'project',
        'answer_type' => 'choice',
        'allows_custom' => false,
        'choices' => [
            'no-bookmatch',
            'bookmatch-vertical',
            'bookmatch-horizontal',
            'bookmatch-custom',
        ],
    ],
];
