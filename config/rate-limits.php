<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Requests per minute
    |--------------------------------------------------------------------------
    |
    | How many requests a minute each named limiter in
    | AppServiceProvider::configureRateLimits() lets through, counted per
    | signed-in user and per IP for a guest. Past it the request is answered
    | 429 with a Retry-After header, which the pages turn into "please wait".
    |
    | These are starting points. Tune them from Pulse's usage cards and the
    | 429s in the logs, through the environment, without a code change.
    | Fortify's own `login` and `two-factor` limits (5 a minute) are set in
    | FortifyServiceProvider.
    |
    */

    'per_minute' => [

        // POST quote/inquire and inquiry/inquire: sending an inquiry.
        'inquiry-submit' => (int) env('RATE_LIMIT_INQUIRY_SUBMIT', 5),

        // PATCH quote/{reference} and quote/{reference}/cancel.
        'inquiry-edit' => (int) env('RATE_LIMIT_INQUIRY_EDIT', 10),

        // POST, PUT and DELETE dashboard/profile/addresses: changing the client's address book.
        'address-book-save' => (int) env('RATE_LIMIT_ADDRESS_BOOK_SAVE', 20),

        // POST, PUT, PATCH dashboard/accounts/*: the admin adding, changing,
        // turning off and archiving accounts.
        'account-save' => (int) env('RATE_LIMIT_ACCOUNT_SAVE', 20),

        // POST, PUT dashboard/brands/*: the admin adding, changing and
        // archiving brands, images included.
        'brand-save' => (int) env('RATE_LIMIT_BRAND_SAVE', 20),
        'showcase-save' => (int) env('RATE_LIMIT_SHOWCASE_SAVE', 20),

        // GET api/ph/*: the address picker's searches, debounced as the reader types.
        'ph-address-search' => (int) env('RATE_LIMIT_PH_ADDRESS_SEARCH', 120),

        // GET inquiry/{reference}/files/{storedName}: a preview checks the file
        // with HEAD and then opens it, so each one is two requests.
        'inquiry-files' => (int) env('RATE_LIMIT_INQUIRY_FILES', 120),

        // POST schedule/book.
        'booking' => (int) env('RATE_LIMIT_BOOKING', 10),

        // PUT and DELETE saved/{stone}: the heart on a stone card.
        'saved-stone' => (int) env('RATE_LIMIT_SAVED_STONE', 60),

        // POST message/groups.
        'group-create' => (int) env('RATE_LIMIT_GROUP_CREATE', 10),

        // GET home and saved: the stone and showcase feeds with their filters,
        // search and infinite scroll, each page of which is one request.
        'heavy-reads' => (int) env('RATE_LIMIT_HEAVY_READS', 120),

        // Fortify's POST register, per IP.
        'register' => (int) env('RATE_LIMIT_REGISTER', 5),

        // Sign in and sign up with Google: the trip out, the callback and
        // Finish signing up, per IP (per user when connecting from settings).
        'social-login' => (int) env('RATE_LIMIT_SOCIAL_LOGIN', 10),

        // Fortify's POST forgot-password and reset-password, per IP, together.
        'password-reset' => (int) env('RATE_LIMIT_PASSWORD_RESET', 5),

        // The Message page. See AppServiceProvider::configureRateLimits().
        'messages' => (int) env('RATE_LIMIT_MESSAGES', 60),
        'message-receipts' => (int) env('RATE_LIMIT_MESSAGE_RECEIPTS', 120),
        'message-typing' => (int) env('RATE_LIMIT_MESSAGE_TYPING', 60),
        'message-assignment' => (int) env('RATE_LIMIT_MESSAGE_ASSIGNMENT', 10),
        'message-people' => (int) env('RATE_LIMIT_MESSAGE_PEOPLE', 60),
        'message-attachments' => (int) env('RATE_LIMIT_MESSAGE_ATTACHMENTS', 300),

        // The bell: its pages, opening a notification, and marking all read.
        'notifications' => (int) env('RATE_LIMIT_NOTIFICATIONS', 120),

    ],

];
