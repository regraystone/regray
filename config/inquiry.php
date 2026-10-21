<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Disk
    |--------------------------------------------------------------------------
    |
    | Where inquiry attachments are written. Always the private disk: a plan or
    | a drawing sent with an inquiry is never served from a public URL.
    |
    */

    'disk' => env('INQUIRY_DISK', env('FILESYSTEM_PRIVATE_DISK', 'local')),

    /*
    |--------------------------------------------------------------------------
    | Limits
    |--------------------------------------------------------------------------
    |
    | The front end mirrors these in resources/js/lib/inquiry-schema.ts, so a
    | change here has to be made there too.
    |
    */

    'limits' => [
        'projects' => 10,
        'stone_rows' => 20,
        'quantity_min' => 1,
        'quantity_max' => 9999,
        'name' => 100,
        'landmark' => 150,
        'notes' => 1000,
        'files' => 10,
        'file_kilobytes' => 10 * 1024,
        'file_extensions' => ['pdf', 'png', 'jpg', 'jpeg'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Locations
    |--------------------------------------------------------------------------
    |
    | The cities and municipalities a project can be in. Metro Manila is the
    | National Capital Region's seventeen local government units: sixteen cities,
    | plus Pateros, which is the region's one municipality. The spellings are the
    | official ones, diacritics included, because these end up on a delivery
    | docket. Alphabetical, so the unfiltered list reads as a list.
    |
    | Validation and the Inquire page both read this one list.
    |
    */

    'locations' => [
        'Caloocan',
        'Las Piñas',
        'Makati',
        'Malabon',
        'Mandaluyong',
        'Manila',
        'Marikina',
        'Muntinlupa',
        'Navotas',
        'Parañaque',
        'Pasay',
        'Pasig',
        'Pateros',
        'Quezon City',
        'San Juan',
        'Taguig',
        'Valenzuela',
    ],

];
