<?php

/*
 * The admin's Brands page: what a brand's images may be. Both are stored on
 * the public disk (config('filesystems.public_disk')) under brands/, each
 * under a random name (App\Support\StoredFileName).
 */
return [

    // The image types a logo and a thumbnail may be, read from the file's bytes.
    'image_mimes' => ['png', 'jpg', 'jpeg', 'webp', 'svg'],

    // The largest logo, in kilobytes.
    'logo_max_kb' => (int) env('BRANDS_LOGO_MAX_KB', 2048),

    // The largest thumbnail, in kilobytes: a wide cover photo.
    'thumbnail_max_kb' => (int) env('BRANDS_THUMBNAIL_MAX_KB', 5120),

    // The limits on what a brand's page says about it.
    'facts_max' => 6,
    'fact_label_max' => 40,
    'fact_value_max' => 120,
    'makes_max' => 8,
    'make_max' => 40,

];
