<?php

/*
 * The brands on the home page, in their order there. `logo_path` is where the
 * logo lives on the public disk; the matching source file is at
 * data/<logo_path>. BrandSeeder copies it there, so the path never changes.
 *
 * Each `description` is written from facts on the brand's own site or a
 * trade profile (checked November 2026), not copied from them. The taglines
 * are ours, not the brands' slogans; replace them with approved copy when
 * there is some. Taglines are at most 120 characters. A `website_url` is set
 * only where the brand's own site was confirmed; Faer Ambienti and Res
 * Italia have none found, so theirs are left empty.
 */
return [
    [
        'slug' => 'regray-stone',
        'name' => 'Regray Stone',
        'logo_path' => 'brands/regray_stone_logo.webp',
        'website_url' => 'https://regraystone.com/',
        'tagline' => 'Natural stone, cut for the way you live.',
        'description' => 'Luxury natural stone, bespoke furniture and interior solutions from Makati City, with sourcing, fabrication and installation nationwide. Over 1,000 spaces installed.',
    ],
    [
        'slug' => 'cucine',
        'name' => 'Cucine Lube',
        'logo_path' => 'brands/cucine_logo.webp',
        'website_url' => 'https://www.cucinelube.it/',
        'tagline' => 'Italian kitchens, built around the people in them.',
        'description' => 'Italy\'s largest kitchen manufacturer, founded in 1967 in Treia. Around 75,000 kitchens a year, sold in 76 countries.',
    ],
    [
        'slug' => 'ascale',
        'name' => 'Ascale',
        'logo_path' => 'brands/ascale_logo.webp',
        'website_url' => 'https://www.ascale.es/',
        'tagline' => 'Large-format surfaces, no compromises.',
        'description' => 'Large-format sintered stone from Spain\'s Pamesa group, in slabs up to 162 by 324 cm for countertops, floors, walls and façades.',
    ],
    [
        'slug' => 'faer',
        'name' => 'Faer Ambienti',
        'logo_path' => 'brands/faer_logo.webp',
        'website_url' => null,
        'tagline' => 'Living spaces, designed as one.',
        'description' => 'Gruppo Lube\'s brand for the rest of the home: wall units, wardrobes, bedrooms and desks, made in Treia.',
    ],
    [
        'slug' => 'reform',
        'name' => 'Reform',
        'logo_path' => 'brands/reform_logo.webp',
        'website_url' => 'https://www.reformcph.com/',
        'tagline' => 'Kitchens designed with architects.',
        'description' => 'Copenhagen kitchens and storage since 2014, designed with architects such as David Chipperfield, Norm Architects and Jean Nouvel.',
    ],
    [
        'slug' => 'res-italia',
        'name' => 'Res Italia',
        'logo_path' => 'brands/res_logo.webp',
        'website_url' => null,
        'tagline' => 'Italian doors, made in Brianza.',
        'description' => 'Interior doors and door systems, made in Seregno, Brianza, since 1962.',
    ],
];
