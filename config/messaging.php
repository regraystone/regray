<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Message notifications
    |--------------------------------------------------------------------------
    |
    | How long after the first message a recipient has not seen their
    | notification and its email go (MessageNotificationService): staff after
    | `staff_delay_minutes`, clients after `client_delay_minutes`. Opening the
    | thread before then cancels both. A queued job sends them at the due
    | time, and `notifications:deliver-due` runs every minute for any it missed.
    |
    */

    'notifications' => [
        'staff_delay_minutes' => (int) env('MESSAGE_NOTIFICATION_STAFF_DELAY_MINUTES', 3),
        'client_delay_minutes' => (int) env('MESSAGE_NOTIFICATION_CLIENT_DELAY_MINUTES', 60),
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
