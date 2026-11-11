<?php

use App\Console\Commands\HealthCheckCommand;
use App\Models\PendingNotification;
use App\Models\UserNotification;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:backup-database')->dailyAt('00:00')->timezone('Asia/Manila')->onOneServer();
Schedule::command('queue:prune-failed --hours=168')->dailyAt('00:30')->timezone('Asia/Manila');
// Message notifications whose delay has passed, for any the queue missed.
Schedule::command('notifications:deliver-due')->everyMinute()->withoutOverlapping()->onOneServer();
// Notifications, and message timers, older than 30 days.
Schedule::command('model:prune', ['--model' => [UserNotification::class, PendingNotification::class]])->dailyAt('01:00')->timezone('Asia/Manila')->onOneServer();
// An appointment nobody approved before its time becomes expired, and its client is told.
Schedule::command('schedules:expire')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
// Horizon's throughput and wait-time graphs. Only Horizon's redis queues have any.
Schedule::command('horizon:snapshot')->everyFiveMinutes()->when(fn (): bool => config('queue.default') === 'redis');
// The heartbeat app:health-check reads to tell that the scheduler is running.
Schedule::call(fn () => Cache::put(HealthCheckCommand::SCHEDULER_HEARTBEAT_KEY, now()->toIso8601String(), now()->addDay()))
    ->everyMinute()
    ->name('health-check-heartbeat');
