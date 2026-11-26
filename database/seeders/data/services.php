<?php

/*
 * The two dropdowns of "What do you need?" on the Inquire page, in the order they
 * show them, with what is shown under the service once it is chosen.
 *
 * `requires_stones` is false for a service that asks for no stones: the page
 * skips the stones question for it. `fulfillment` is how the work reaches the
 * client: `delivery_or_pickup` asks Delivery or Pickup (an address only for a
 * delivery), `installed` asks no method and needs the installation address.
 * `fields` are the questions it asks besides
 * the stones (slugs of data/detail_fields.php), in the order they are asked,
 * each with whether it has to be answered.
 *
 * @var list<array{slug: string, name: string, services: list<array{slug: string, name: string, description: string, requires_stones: bool, fulfillment: 'delivery_or_pickup'|'installed', features: list<string>, fields: array<string, bool>}>}>
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
                'requires_stones' => true,
                'fulfillment' => 'delivery_or_pickup',
                'features' => ['Every stone type Regray carries', 'Sourcing based on your project requirements', 'Nationwide supply'],
                // Slabs supplied alone may come uncut: the edge is asked, not required.
                'fields' => ['quantity' => true, 'slab_size' => true, 'thickness' => true, 'edge_profile' => false, 'finish' => true],
            ],
            [
                'slug' => 'slab-supply-and-installation',
                'name' => 'Slab Supply and Installation',
                'description' => 'Premium natural stone slabs supplied and installed for your project.',
                'requires_stones' => true,
                'fulfillment' => 'installed',
                'features' => ['Every stone type Regray carries', 'Sourcing based on your project requirements', 'Nationwide installation'],
                'fields' => ['quantity' => true, 'slab_size' => true, 'thickness' => true, 'edge_profile' => true, 'finish' => true],
            ],
            [
                'slug' => 'installation-only',
                'name' => 'Installation Only',
                'description' => 'Natural stone installation for your project.',
                'requires_stones' => false,
                'fulfillment' => 'installed',
                'features' => ['Nationwide installation'],
                'fields' => [],
            ],
            [
                'slug' => 'countertop-backsplash',
                'name' => 'Countertop and Backsplash',
                'description' => 'Natural stone countertops cut and finished to your dimensions.',
                'requires_stones' => true,
                'fulfillment' => 'installed',
                'features' => ['Precision cutting and fabrication', 'Custom dimensions', 'Edge profiling and finishing'],
                'fields' => ['area_sqm' => true, 'edge_profile' => true, 'finish' => true, 'fascia' => true],
            ],
            [
                'slug' => 'wall-cladding',
                'name' => 'Wall Cladding',
                'description' => 'Natural stone cladding for accent and feature walls.',
                'requires_stones' => true,
                'fulfillment' => 'installed',
                'features' => ['Accent and feature walls', 'Nationwide installation'],
                'fields' => ['area_sqm' => true, 'finish' => true],
            ],
            [
                'slug' => 'flooring',
                'name' => 'Flooring',
                'description' => 'Natural stone flooring for your project.',
                'requires_stones' => true,
                'fulfillment' => 'installed',
                'features' => ['Precision cutting and fabrication', 'Nationwide installation'],
                'fields' => ['area_sqm' => true, 'finish' => true],
            ],
            [
                'slug' => 'staircase',
                'name' => 'Staircase',
                'description' => 'Natural stone staircases, from treads to finished edges.',
                'requires_stones' => true,
                'fulfillment' => 'installed',
                'features' => ['Custom dimensions', 'Edge profiling and finishing'],
                'fields' => ['area_sqm' => true, 'edge_profile' => true, 'finish' => true],
            ],
            [
                'slug' => 'kitchen-islands',
                'name' => 'Kitchen Islands',
                'description' => 'Natural stone kitchen islands cut and finished to your dimensions.',
                'requires_stones' => true,
                'fulfillment' => 'installed',
                'features' => ['Precision cutting and fabrication', 'Waterfall sides on request', 'Edge profiling and finishing'],
                'fields' => ['area_sqm' => true, 'edge_profile' => true, 'finish' => true, 'waterfall_effect' => true],
            ],
            [
                'slug' => 'lavatories',
                'name' => 'Lavatories',
                'description' => 'Natural stone lavatory and vanity tops for your bathrooms.',
                'requires_stones' => true,
                'fulfillment' => 'installed',
                'features' => ['Precision cutting and fabrication', 'Custom dimensions', 'Edge profiling and finishing'],
                'fields' => ['area_sqm' => true, 'edge_profile' => true, 'finish' => true],
            ],
        ],
    ],
    [
        'slug' => 'custom-stone-furniture',
        'name' => 'Custom Stone Furniture',
        'services' => [
            [
                'slug' => 'center-tables',
                'name' => 'Center Tables',
                'description' => 'Made-to-order stone center and coffee tables.',
                'requires_stones' => true,
                'fulfillment' => 'delivery_or_pickup',
                'features' => ['Center and coffee tables', 'Made to order to your specifications'],
                'fields' => ['table_shape' => true, 'table_base_design_center' => true, 'table_base' => true, 'size' => true, 'edge_profile' => true, 'finish' => true],
            ],
            [
                'slug' => 'side-tables',
                'name' => 'Side Tables',
                'description' => 'Made-to-order stone side tables.',
                'requires_stones' => true,
                'fulfillment' => 'delivery_or_pickup',
                'features' => ['Side and end tables', 'Made to order to your specifications'],
                'fields' => ['table_shape' => true, 'table_base_design_side' => true, 'table_base' => true, 'size' => true, 'edge_profile' => true, 'finish' => true],
            ],
            [
                'slug' => 'dining-tables',
                'name' => 'Dining Tables',
                'description' => 'Made-to-order stone dining tables.',
                'requires_stones' => true,
                'fulfillment' => 'delivery_or_pickup',
                'features' => ['Dining tables for 2 to 12 seaters', 'A base in wood, steel or the same stone', 'Made to order to your specifications'],
                'fields' => ['table_shape' => true, 'table_base_design_dining' => true, 'table_base' => true, 'area_sqm' => true, 'seaters' => true, 'edge_profile' => true, 'finish' => true],
            ],
            [
                'slug' => 'other-customized-furniture',
                'name' => 'Other Customized Furniture',
                'description' => 'Any other stone furniture, made to order. Tell us what you have in mind.',
                'requires_stones' => true,
                'fulfillment' => 'delivery_or_pickup',
                'features' => ['Custom dimensions and designs', 'Made to order to your specifications'],
                'fields' => ['area_sqm' => true, 'size' => true, 'finish' => true],
            ],
        ],
    ],
];
