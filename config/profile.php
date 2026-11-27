<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Profile photos
    |--------------------------------------------------------------------------
    |
    | A client's photo on their Profile page, shown wherever they appear on
    | the platform (the header, their messages, an inquiry's details). The
    | types are read from the file's bytes, not its name.
    |
    */

    'photo_mimes' => ['jpg', 'jpeg', 'png', 'webp'],

    // The largest photo, in kilobytes.
    'photo_max_kb' => (int) env('PROFILE_PHOTO_MAX_KB', 5120),

    // Where photos are kept on the public disk.
    'photo_directory' => 'avatars',

];
