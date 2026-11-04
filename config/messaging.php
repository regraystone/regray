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

    /*
    |--------------------------------------------------------------------------
    | Attachments
    |--------------------------------------------------------------------------
    |
    | Files sent in a thread, always on a private disk: s3-private (the
    | private S3 bucket) in production, and Laravel's own storage
    | (storage/app/private) everywhere else. It does not follow
    | FILESYSTEM_PRIVATE_DISK, so a production server that forgot that line
    | still cannot keep files inside a container that the next deploy
    | replaces. MESSAGE_ATTACHMENT_DISK overrides it.
    |
    | A file is only ever served through the download route, which checks that
    | the viewer may read the conversation, and on S3 hands out a link that
    | expires after `link_minutes`.
    |
    | The total stays under PHP's post_max_size (64M in docker/php.ini). The
    | front end mirrors these in resources/js/lib/message-attachments.ts, so a
    | change here has to be made there too.
    |
    */

    'attachments' => [
        'disk' => env('MESSAGE_ATTACHMENT_DISK', env('APP_ENV') === 'production' ? 's3-private' : 'local'),
        'link_minutes' => 5,
        'files' => 5,
        'file_kilobytes' => 50 * 1024,
        'total_kilobytes' => 50 * 1024,
        'file_extensions' => ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'docx'],
    ],

];
