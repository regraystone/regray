<?php

/*
 * The library of choices the list questions pick from (the Choices page under
 * Services), in the library's order. A question lists them by slug
 * (`choices` in data/detail_fields.php), so one choice, written once, can be
 * listed by several questions ("Custom Design", "Not sure yet, advise me").
 *
 * Each has a `slug` and `name` (unique, ignoring case), and may have a
 * `description` (shown once it is picked), an `image` (its picture on the
 * public disk, committed at storage/app/public/question-choices/<random name>
 * like the furniture photographs and put in the bucket on AWS with
 * scripts/upload-catalog-images.sh; listed in docs/sample-pictures.md; a
 * question with a pictured choice is asked with picture cards), a `kind`
 * (`custom` asks the client to describe their own, `advisory` is "Not sure
 * yet, advise me", `stone` is "Same as stone", which needs a stone row; none
 * is an ordinary choice), a `size` (how a table shape asks the size:
 * `diameter` or `width_length`; none takes the client's own words) and
 * `services` (the slugs of the services it is offered for; none is every
 * service that asks a question listing it, ChoiceSeeder links them).
 *
 * @var list<array{slug: string, name: string, description?: string, image?: string, kind?: string, size?: string, services?: list<string>}>
 */
return [
    ['slug' => 'rectangular', 'name' => 'Rectangular', 'size' => 'width_length', 'image' => 'question-choices/11a89a838eee51dc81bce3d3a356fef4.jpg',
        'description' => 'The classic long table. Seats the most people along its sides and suits long rooms.'],
    ['slug' => 'round', 'name' => 'Round', 'size' => 'diameter', 'image' => 'question-choices/c6087ab94cd1474f4e92727f0f0e2240.jpg',
        'description' => 'No corners, so everyone faces each other. Best for small groups and square rooms.'],
    ['slug' => 'oval', 'name' => 'Oval', 'size' => 'width_length', 'image' => 'question-choices/0d1f7d9974b52d2f99d9a7f6b065d18a.jpg',
        'description' => 'The length of a rectangle with softened ends, so it is easier to walk around.'],
    ['slug' => 'square', 'name' => 'Square', 'size' => 'width_length', 'image' => 'question-choices/1a2b82c3d4e67d70bc0910db4fb104e5.jpg',
        'description' => 'Equal sides for an even, close setting. Suits square rooms and small groups.'],
    ['slug' => 'rounded-rectangle', 'name' => 'Rounded Rectangle', 'size' => 'width_length', 'image' => 'question-choices/e9aeae29047a7f6362f16abcdebdf313.jpg',
        'description' => 'A rectangle with rounded corners: the same seating, gentler on hips and walkways.'],
    ['slug' => 'organic', 'name' => 'Organic / Freeform', 'image' => 'question-choices/1a08d65bfa27ca46f99685c7bc0e8e4a.jpg',
        'description' => 'A flowing, irregular outline that follows the stone or your design, for a sculptural look.'],
    ['slug' => 'custom-shape', 'name' => 'Custom Shape', 'kind' => 'custom',
        'description' => 'Have a shape in mind? Describe it and we will work it out with you.'],
    ['slug' => 'not-sure', 'name' => 'Not sure yet, advise me', 'kind' => 'advisory',
        'description' => 'We will recommend one that suits your room and how you use it.'],
    ['slug' => 'sphere-accent-pedestal', 'name' => 'Sphere Accent Pedestal', 'image' => 'question-choices/700f38ecf9c2e65d4676967303924044.jpg',
        'description' => 'A curved pedestal beside a polished sphere, under a ring that holds the top. A sculptural centrepiece.'],
    ['slug' => 'u-base', 'name' => 'U-Base', 'image' => 'question-choices/f0f2b8e6a82e4d9b9424537c84935f04.jpg',
        'description' => 'One arched panel that flares at the floor, open in the middle. Light-looking, with a clear view under the top.'],
    ['slug' => 'half-moon', 'name' => 'Half Moon', 'image' => 'question-choices/98ef8163023fbf0a5360f2ce8cb66fed.jpg',
        'description' => 'A pair of half-round columns, flat side in. Clean and modern under a long or oval top.'],
    ['slug' => 'custom-design', 'name' => 'Custom Design', 'kind' => 'custom',
        'description' => 'Have a base in mind? Describe it and we will design it with you.'],
    ['slug' => 'c-legs-v1', 'name' => 'C-Legs V1', 'image' => 'question-choices/9ab33da52cced2c01686b459a63b3dea.jpg',
        'description' => 'A C-shaped frame in black that slides under a sofa, so the top sits over your lap.'],
    ['slug' => 'c-legs-v2', 'name' => 'C-Legs V2', 'image' => 'question-choices/dc9084aa8f1f5b78e81dca38fa5771a5.jpg',
        'description' => 'The same C-shaped frame in brass, for a warmer, softer look beside a sofa or bed.'],
    ['slug' => 'cylindrical-pedestal', 'name' => 'Cylindrical Pedestal', 'image' => 'question-choices/d10426636a40d7745007036e885eb718.jpg',
        'description' => 'A slim cylinder on a round foot with a sphere accent. Compact, for a small round top.'],
    ['slug' => 'hourglass-v2', 'name' => 'Hourglass V2', 'image' => 'question-choices/8f2c88b2ec5a86f3ca06945ea8135d94.jpg',
        'description' => 'Faceted panels that pinch at the middle like an hourglass. A sturdy, sculptural side table.'],
    ['slug' => 'diamond', 'name' => 'Diamond', 'image' => 'question-choices/11c1fb975bd0c2d2782938c345cae961.jpg',
        'description' => 'A pair of faceted, diamond-section columns on steel feet. Strong support under a long top.'],
    ['slug' => 'close-cage', 'name' => 'Close Cage', 'image' => 'question-choices/de03674ee6c1ef5dd94589c0a1318b92.jpg',
        'description' => 'A pair of open cages of stacked bars. Architectural, with steady support at each end.'],
    ['slug' => 'tubular-v1', 'name' => 'Tubular V1', 'image' => 'question-choices/d1f04c36d235a8f9f6595e849822dff8.jpg',
        'description' => 'A slim rectangular frame of square tubes. Minimal, with legroom along both sides.'],
    ['slug' => 'tubular-v2', 'name' => 'Tubular V2', 'image' => 'question-choices/adf33c75e17cb5330737b1c549e71975.jpg',
        'description' => 'The tubular frame with crossed bracing along its length, for a bolder, trestle-like look.'],
    ['slug' => 'x-base-v1', 'name' => 'X-Base V1', 'image' => 'question-choices/768b736608b236eed3c5e2ec434342ae.jpg',
        'description' => 'Four fins that cross in an X, in a warm metal finish. A single base for a round or square top.'],
    ['slug' => 'x-base-v2', 'name' => 'X-Base V2', 'image' => 'question-choices/3af0ba8e2fef9dfa9a626a24cb2aece5.jpg',
        'description' => 'The crossed X-base in a dark finish, for a bolder, architectural look.'],
    ['slug' => 'clover-v1', 'name' => 'Clover V1', 'image' => 'question-choices/8025c7b3e7cbbaf8325a5e54621035d7.jpg',
        'description' => 'Four rounded lobes in a dark polished finish. A single sculptural base, ideal under a round top.'],
    ['slug' => 'clover-v2', 'name' => 'Clover V2', 'image' => 'question-choices/49816b04ab21e365f44f1fafc9e7223a.jpg',
        'description' => 'The clover base in polished brass, for a warm, statement centrepiece.'],
    ['slug' => 'y-base', 'name' => 'Y-Base', 'image' => 'question-choices/de3b5f52b6ec6726f8ae4077e352d324.jpg',
        'description' => 'Three fins that meet in a Y. A single base that leaves legroom all around.'],
    ['slug' => 'hourglass-v1', 'name' => 'Hourglass V1', 'image' => 'question-choices/eba8e1c79fe4a8ee70b56e18894f3ebd.jpg',
        'description' => 'Curved panels that pinch at the middle, on a round foot. A pedestal for a round dining top.'],
    ['slug' => 'same-as-stone', 'name' => 'Same as stone', 'kind' => 'stone'],
];
