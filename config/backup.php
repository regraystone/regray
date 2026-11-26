<?php

/*
 * The nightly database backup (App\Console\Commands\BackupDatabaseCommand),
 * which runs at midnight Manila time and streams a pg_dump to the private disk
 * under backups/.
 */
return [

    /*
     * Dumps older than this many days are pruned after each successful backup,
     * so the private disk does not grow without bound. Zero keeps every dump.
     *
     * This prunes within the bucket; an S3 lifecycle rule and Object Lock on
     * the backups/ prefix are what protect the history from a leaked key.
     */
    'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 30),

];
