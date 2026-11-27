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

    // On S3, how long the link a file preview or download redirects to lasts.
    'file_link_minutes' => 5,

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
        // Each project is written as its own inquiry, quoted on its own.
        'projects' => 5,
        'stone_rows' => 20,
        'quantity_min' => 1,
        'quantity_max' => 9999,
        'name' => 100,
        'notes' => 1000,
        'files' => 10,
        'file_kilobytes' => 10 * 1024,
        'file_extensions' => ['pdf', 'png', 'jpg', 'jpeg'],
    ],

];
