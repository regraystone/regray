<?php

return [

    // The image types a product's thumbnail and gallery photos may be, read from the file's bytes.
    'image_mimes' => ['png', 'jpg', 'jpeg', 'webp'],

    // The largest thumbnail, in kilobytes.
    'thumbnail_max_kb' => (int) env('PRODUCTS_THUMBNAIL_MAX_KB', 5120),

    // The largest gallery photo, in kilobytes.
    'photo_max_kb' => (int) env('PRODUCTS_PHOTO_MAX_KB', 5120),

    // The most photos a product's gallery can have, besides its thumbnail.
    'gallery_max' => 12,

    // The most sizes a stone can list, each with its thicknesses.
    'sizes_max' => 30,

    // The longest description a piece of furniture or a stone type can have, in characters.
    'description_max' => 2000,

];
