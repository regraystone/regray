<?php

/*
 * The two dropdowns of "What do you need?" on the Inquire page, in the order they
 * show them, with what is shown under the service once it is chosen.
 *
 * @var list<array{slug: string, name: string, services: list<array{slug: string, name: string, description: string, features: list<string>}>}>
 */
return [
    [
        'slug' => 'natural-stone-applications',
        'name' => 'Natural Stone & Applications',
        'services' => [
            [
                'slug' => 'slab-supply-only',
                'name' => 'Slab Supply Only',
                'description' => 'Premium natural stone slabs supplied for your project.',
                'features' => ['Marble, quartzite, onyx, granite and travertine', 'Sourcing based on your project requirements', 'Nationwide supply'],
            ],
            [
                'slug' => 'countertop',
                'name' => 'Countertop',
                'description' => 'Natural stone countertops cut and finished to your dimensions.',
                'features' => ['Precision cutting and fabrication', 'Custom dimensions', 'Edge profiling and finishing'],
            ],
            [
                'slug' => 'wall-cladding',
                'name' => 'Wall Cladding',
                'description' => 'Natural stone cladding for accent and feature walls.',
                'features' => ['Accent and feature walls', 'Nationwide installation'],
            ],
            [
                'slug' => 'flooring',
                'name' => 'Flooring',
                'description' => 'Natural stone flooring for your project.',
                'features' => ['Precision cutting and fabrication', 'Nationwide installation'],
            ],
            [
                'slug' => 'staircase',
                'name' => 'Staircase',
                'description' => 'Natural stone staircases, from treads to finished edges.',
                'features' => ['Custom dimensions', 'Edge profiling and finishing'],
            ],
            [
                'slug' => 'other-applications',
                'name' => 'Other Applications',
                'description' => 'Any other stone application. Tell us what you have in mind.',
                'features' => ['Custom dimensions and designs', 'Sourcing based on your project requirements'],
            ],
        ],
    ],
    [
        'slug' => 'custom-stone-furniture',
        'name' => 'Custom Stone Furniture',
        'services' => [
            [
                'slug' => 'tables',
                'name' => 'Tables',
                'description' => 'Made-to-order stone tables.',
                'features' => ['Dining tables', 'Center and coffee tables', 'Side tables', 'Console tables'],
            ],
            [
                'slug' => 'seating',
                'name' => 'Seating',
                'description' => 'Made-to-order stone seating.',
                'features' => ['Made to order to your specifications'],
            ],
            [
                'slug' => 'storage',
                'name' => 'Storage',
                'description' => 'Made-to-order stone storage pieces.',
                'features' => ['Made to order to your specifications'],
            ],
            [
                'slug' => 'decorative-furniture',
                'name' => 'Decorative Furniture',
                'description' => 'Bespoke decorative stone pieces.',
                'features' => ['Pedestals and bespoke stone pieces', 'Made to order to your specifications'],
            ],
        ],
    ],
];
