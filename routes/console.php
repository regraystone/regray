<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:backup-database')->dailyAt('00:00')->timezone('Asia/Manila')->onOneServer();
Schedule::command('queue:prune-failed --hours=168')->dailyAt('00:30')->timezone('Asia/Manila');
Schedule::command('messages:send-reminders')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
