<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Timezone
    |--------------------------------------------------------------------------
    |
    | The studio's own zone. A date and time a client types on the inquiry form
    | ("the 14th at 10:00") is read in it, and the Schedule page's default range
    | is worked out in it. The scheduler in routes/console.php runs in the same
    | zone.
    |
    */

    'timezone' => 'Asia/Manila',

    /*
    |--------------------------------------------------------------------------
    | Ongoing window
    |--------------------------------------------------------------------------
    |
    | How many minutes after its arrival a pending or confirmed visit still
    | counts as happening "now" on the Upcoming panel, rather than as past.
    |
    */

    'ongoing_minutes' => (int) env('SCHEDULE_ONGOING_MINUTES', 60),

    /*
    |--------------------------------------------------------------------------
    | Longest range
    |--------------------------------------------------------------------------
    |
    | The most days one request for the Schedule page may cover. The page asks
    | for its week and the mini calendar's six-week month together, which fits
    | well inside this. resources/js/lib/schedule.ts mirrors it.
    |
    */

    'max_range_days' => 62,

];
