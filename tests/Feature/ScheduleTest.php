<?php

use Illuminate\Console\Scheduling\Event;
use Illuminate\Support\Facades\Schedule;

test('database backups and failed job pruning run daily at the Manila times', function () {
    $this->artisan('schedule:list')->assertSuccessful();
    $events = collect(Schedule::events());
    $backup = $events->first(fn (Event $event): bool => str_contains($event->command ?? '', 'app:backup-database'));
    $prune = $events->first(fn (Event $event): bool => str_contains($event->command ?? '', 'queue:prune-failed --hours=168'));

    expect($backup)->toBeInstanceOf(Event::class);
    expect($backup->expression)->toBe('0 0 * * *');
    expect($backup->timezone)->toBe('Asia/Manila');
    expect($backup->onOneServer)->toBeTrue();
    expect($prune)->toBeInstanceOf(Event::class);
    expect($prune->expression)->toBe('30 0 * * *');
    expect($prune->timezone)->toBe('Asia/Manila');
});
