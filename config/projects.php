<?php

/*
 * The admin's Projects page: what a project's images may be. The thumbnail
 * and the gallery photos are stored on the public disk
 * (config('filesystems.public_disk')) under projects/, each under a random
 * name (App\Support\StoredFileName).
 *
 * Photos only: no SVG, which suits a logo but not a photograph, and is the
 * one image type that can carry a script.
 */
return [

    // The image types a thumbnail and a gallery photo may be, read from the file's bytes.
    'image_mimes' => ['png', 'jpg', 'jpeg', 'webp'],

    // The largest thumbnail, in kilobytes: the wide cover photo.
    'thumbnail_max_kb' => (int) env('PROJECTS_THUMBNAIL_MAX_KB', 5120),

    // The largest gallery photo, in kilobytes.
    'photo_max_kb' => (int) env('PROJECTS_PHOTO_MAX_KB', 5120),

    // The most photos a project's gallery can have.
    'gallery_max' => 20,

    // The longest overview, in characters.
    'overview_max' => 10000,

];
