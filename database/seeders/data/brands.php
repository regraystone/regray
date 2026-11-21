<?php

/*
 * The brands on the home page, in their order there. `logo_path` and
 * `thumbnail_path` are where the images live on the public disk: committed at
 * storage/app/public/<path> locally, like the stone, project and furniture
 * photographs, and uploaded to the bucket by hand on AWS.
 *
 * `facts` and `makes` are what a brand's page says about the house: its facts
 * ("Made in", "Treia, Italy") and what it makes. They were BRAND_DETAILS in
 * public-links.ts. They are rows of their own (brand_facts, brand_makes), and
 * the migration that made those tables copied them from here.
 *
 * `socials` are the brand's social links ({platform, url}, SocialPlatform;
 * brand_socials), in the order its page shows them. Every brand's page used to show Regray's
 * own Facebook, Instagram and TikTok, so each brand starts with those; the
 * admin sets a brand's own on the Brands page.
 */
$regraySocials = [
    ['platform' => 'facebook', 'url' => 'https://www.facebook.com/Regraystone'],
    ['platform' => 'instagram', 'url' => 'https://www.instagram.com/regraystoneph/'],
    ['platform' => 'tiktok', 'url' => 'https://www.tiktok.com/@regraystone'],
];

return [
    [
        'slug' => 'regray-stone',
        'name' => 'Regray Stone',
        'logo_path' => 'brands/7eb55a8ed8d09d3237cd348c2a3a929e.png',
        'thumbnail_path' => 'brands/9c2e5a1f8b4d7c3e6a9f2c5e8a1d4b7f.png',
        'website_url' => 'https://regraystone.com/',
        'tagline' => 'Natural stone and bespoke furniture, from sourcing to installation nationwide.',
        'description' => 'Luxury natural stone, bespoke furniture and interior solutions from Makati City, with sourcing, fabrication and installation nationwide. Over 1,000 spaces installed.',
        'facts' => [
            ['label' => 'Based in', 'value' => 'Makati City, Philippines'],
            ['label' => 'Services', 'value' => 'Sourcing, fabrication and installation, nationwide'],
            ['label' => 'Installed', 'value' => 'Over 1,000 spaces'],
        ],
        'makes' => ['Natural stone', 'Bespoke furniture', 'Interior solutions'],
        'socials' => $regraySocials,
    ],
    [
        'slug' => 'cucine',
        'name' => 'Cucine Lube',
        'logo_path' => 'brands/4e8f2a6c1d9b5f3a7c0e1b8d4f6a9c2e.png',
        'thumbnail_path' => 'brands/7a2f5c9e1b6d3f8c4e0a1d7b5f2c9e6a.jpg',
        'website_url' => 'https://www.cucinelube.it/',
        'tagline' => 'Italian kitchens from Treia, founded in 1967, sold in 76 countries.',
        'description' => 'Italy\'s largest kitchen manufacturer, founded in 1967 in Treia. Around 75,000 kitchens a year, sold in 76 countries.',
        'facts' => [
            ['label' => 'Made in', 'value' => 'Treia, Italy'],
            ['label' => 'Founded', 'value' => '1967'],
            ['label' => 'Output', 'value' => 'Around 75,000 kitchens a year'],
            ['label' => 'Sold in', 'value' => '76 countries'],
        ],
        'makes' => ['Kitchens'],
        'socials' => $regraySocials,
    ],
    [
        'slug' => 'ascale',
        'name' => 'Ascale',
        'logo_path' => 'brands/1a5d7e3c6f8b2a4f9c1e5d8a3b6f0e9c.png',
        'thumbnail_path' => 'brands/4c1e9f6a3d8b2f7e5c0a1b6d9e2a4f7c.jpg',
        'website_url' => 'https://www.ascale.es/',
        'tagline' => 'Large-format sintered stone from Spain, in slabs up to 162 by 324 cm.',
        'description' => 'Large-format sintered stone from Spain\'s Pamesa group, in slabs up to 162 by 324 cm for countertops, floors, walls and façades.',
        'facts' => [
            ['label' => 'Made in', 'value' => 'Spain, by the Pamesa group'],
            ['label' => 'Slabs', 'value' => 'Up to 162 × 324 cm'],
        ],
        'makes' => ['Countertops', 'Floors', 'Walls', 'Façades'],
        'socials' => $regraySocials,
    ],
    [
        'slug' => 'faer',
        'name' => 'Faer Ambienti',
        'logo_path' => 'brands/89fd17555451595ef74f24db286ca730.png',
        'thumbnail_path' => 'brands/8b3a1f5e9c6d2a7f4e0c1b8d5f3e9a6c.png',
        'website_url' => null,
        'tagline' => "Gruppo Lube's wall units, wardrobes, bedrooms and desks, made in Treia.",
        'description' => 'Gruppo Lube\'s brand for the rest of the home: wall units, wardrobes, bedrooms and desks, made in Treia.',
        'facts' => [
            ['label' => 'Made in', 'value' => 'Treia, Italy'],
            ['label' => 'Group', 'value' => 'Gruppo Lube'],
        ],
        'makes' => ['Wall units', 'Wardrobes', 'Bedrooms', 'Desks'],
        'socials' => $regraySocials,
    ],
    [
        'slug' => 'reform',
        'name' => 'Reform',
        'logo_path' => 'brands/fa06348dbb0ab7454f4180f30881956c.png',
        'thumbnail_path' => 'brands/2b7e3a9f1c5d8a6b4e0f2c7d9a1b5e8f.png',
        'website_url' => null,
        // re/form, the sintered stone brand, as regraystone.com describes it.
        'tagline' => 'Sintered stone surfaces with refined contemporary design and sophisticated finishes.',
        'description' => 'Re/form redefines materiality through advanced technology, precision engineering and refined contemporary design: sintered stone surfaces with sophisticated finishes, for architectural solutions defined by performance, craftsmanship and understated luxury.',
        'facts' => [
            ['label' => 'Material', 'value' => 'Sintered stone'],
        ],
        'makes' => ['Sintered stone surfaces', 'Architectural solutions'],
        'socials' => $regraySocials,
    ],
    [
        'slug' => 'res-italia',
        'name' => 'Res Italia',
        'logo_path' => 'brands/5e1a9c6f3d8b2f7c4a0e1b6c9d2f5a8e.png',
        'thumbnail_path' => 'brands/6d9c2f1e8a5b3f7c4d0a1b6e9f2c5a8d.png',
        'website_url' => 'https://resitalia.it/',
        'tagline' => 'Italian interior doors and door systems, made in Brianza since 1962.',
        'description' => 'Interior doors and door systems, made in Seregno, Brianza, since 1962.',
        'facts' => [
            ['label' => 'Made in', 'value' => 'Seregno, Brianza, Italy'],
            ['label' => 'Founded', 'value' => '1962'],
        ],
        'makes' => ['Interior doors', 'Door systems'],
        'socials' => $regraySocials,
    ],
];
