<?php

use App\Console\Commands\HealthCheckCommand;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake(config('filesystems.private_disk'));
    Cache::put(HealthCheckCommand::SCHEDULER_HEARTBEAT_KEY, now()->toIso8601String());
});

it('passes when the database, cache, queue, scheduler and public site all work', function (): void {
    Http::fake(['*/up' => Http::response('ok')]);

    $this->artisan('app:health-check', ['--wait' => 0])
        ->expectsOutputToContain('a worker ran the probe job')
        ->expectsOutputToContain('last ran')
        ->assertSuccessful();
});

it('fails when no worker runs the probe job', function (): void {
    Http::fake(['*/up' => Http::response('ok')]);
    Queue::fake();

    $this->artisan('app:health-check', ['--wait' => 0])
        ->expectsOutputToContain('no worker ran the probe job')
        ->assertFailed();
});

it('fails when the scheduler has stopped beating', function (): void {
    Http::fake(['*/up' => Http::response('ok')]);
    Cache::put(HealthCheckCommand::SCHEDULER_HEARTBEAT_KEY, now()->subMinutes(10)->toIso8601String());

    $this->artisan('app:health-check', ['--wait' => 0])
        ->expectsOutputToContain('is the scheduler container up?')
        ->assertFailed();
});

it('fails when the public site is down', function (): void {
    Http::fake(['*/up' => Http::response('down', 503)]);

    $this->artisan('app:health-check', ['--wait' => 0])
        ->expectsOutputToContain('answered 503')
        ->assertFailed();
});
