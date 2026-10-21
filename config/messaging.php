<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Unread message reminders
    |--------------------------------------------------------------------------
    |
    | How long a message may wait before an email is sent about it:
    |
    | - to a member (a client, the staff member handling a Regray Team thread,
    |   a group member) who has not read a message someone else sent them;
    | - to the support pool, when a client has written in a Regray Team thread
    |   that nobody has claimed yet.
    |
    | `messages:send-reminders` runs every five minutes and sends at most one
    | email per person per run, about every conversation that is due.
    |
    */

    'reminders' => [
        'after_minutes' => (int) env('MESSAGE_REMINDER_AFTER_MINUTES', 30),
    ],

];
