<?php

/*
 * The demo clients and staff UserSeeder adds. A client's `addresses` is their
 * address book (Profile → Addresses), the first one their default: each a
 * label and a key of data/places.php. `company` and `address` are their
 * Profile's free text.
 */
return [
    'clients' => [
        [
            'id' => 'cl-elena',
            'name' => 'Elena Villanueva',
            'email' => 'elena.villanueva@brightleafdesign.example',
            'phone' => '+63 917 555 0142',
            'company' => 'Brightleaf Design Studio',
            'address' => '12 Kalachuchi Street, Barangay Commonwealth, Quezon City, 1103',
            'addresses' => [['label' => 'Office', 'place' => 'Quezon City'], ['label' => 'Site', 'place' => 'Antipolo']],
        ],
        [
            'id' => 'cl-rafael',
            'name' => 'Rafael Domingo',
            'email' => 'rafael.doming@thenorthgatecafe.example',
            'phone' => '+63 2 8123 4409',
            'company' => 'The Northgate Cafe',
            'address' => 'Unit 4, Dela Paz Compound, Legazpi Village, Makati, 1229',
            'addresses' => [['label' => 'Cafe', 'place' => 'Makati']],
        ],
        [
            'id' => 'cl-nadine',
            'name' => 'Nadine Delacroix',
            'email' => 'nadine.delacroix@lumenandco.example',
            'phone' => '+63 998 401 2277',
            'company' => 'Lumen & Co. Architects',
            'address' => '88 Katipunan Avenue, Barangay Juan Lopez, Quezon City, 1108',
            'addresses' => [['label' => 'Office', 'place' => 'Loyola Heights'], ['label' => 'Home', 'place' => 'Bel-Air'], ['label' => 'Cebu site', 'place' => 'Cebu']],
        ],
        [
            'id' => 'cl-ivan',
            'name' => 'Ivan Mercado',
            'email' => 'ivan.mercado@hillcrestshowroom.example',
            'phone' => '+63 917 220 8815',
            'company' => 'Hillcrest Showroom',
            'address' => '3 Greenwood Street, Barangay Addition Hills, San Juan, 1050',
            'addresses' => [['label' => 'Showroom', 'place' => 'San Juan'], ['label' => 'Warehouse', 'place' => 'Addition Hills']],
        ],
        [
            'id' => 'cl-haruki',
            'name' => 'Haruki Nakamura',
            'email' => 'haruki.nakamura@groveresidences.example',
            'phone' => '+63 917 630 3345',
            'company' => 'The Grove Residences',
            'address' => '17 Balete Drive, Barangay Ayala Alabang, Muntinlupa, 1780',
            'addresses' => [['label' => 'Site', 'place' => 'Muntinlupa']],
        ],
        [
            'id' => 'cl-grace',
            'name' => 'Grace Lim',
            'email' => 'grace.lim@maplelane.example',
            'phone' => '+63 2 7441 9022',
            'company' => null,
            'address' => '5 Maple Lane, Barangay Tunay, Quezon City, 1105',
            'addresses' => [['label' => 'Home', 'place' => 'Quezon City'], ['label' => 'Office', 'place' => 'Pasig'], ['label' => 'Condo', 'place' => 'Taguig']],
        ],
    ],
    'people' => [
        ['id' => 'maria', 'name' => 'Maria Alvarez', 'role' => 'Sales Officer'],
        ['id' => 'daniel', 'name' => 'Daniel Okafor', 'role' => 'Technical Officer'],
        ['id' => 'sofia', 'name' => 'Sofia Bianchi', 'role' => 'Sales Officer'],
        ['id' => 'kenji', 'name' => 'Kenji Watanabe', 'role' => 'Procurement Lead'],
        ['id' => 'priya', 'name' => 'Priya Raman', 'role' => 'Technical Officer'],
        ['id' => 'lucas', 'name' => 'Lucas Moreau', 'role' => 'Kitchen Studio Owner'],
        ['id' => 'amara', 'name' => 'Amara Nwosu', 'role' => 'Sales Director'],
        ['id' => 'tomas', 'name' => 'Tomas Lindqvist', 'role' => 'Logistics Coordinator'],
        ['id' => 'beatriz', 'name' => 'Beatriz Vasconcelos Cummings', 'role' => 'Architectural Stone Specialist'],
        ['id' => 'hassan', 'name' => 'Hassan Al-Farsi', 'role' => 'Facade & Cladding Advisor'],
    ],
];
