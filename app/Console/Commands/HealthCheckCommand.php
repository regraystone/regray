<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;
use Illuminate\Console\Command;
use Illuminate\Mail\Message;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use stdClass;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Throwable;

/**
 * Checks from inside the app container that production works end to end:
 * config, database, cache, queue worker, scheduler, mail, Reverb and the
 * public site. Each check answers OK, WARN, FAIL or SKIP, and any FAIL makes
 * the command exit 1. scripts/prod-check.sh runs it after checking the
 * containers themselves.
 */
class HealthCheckCommand extends Command
{
    /** The key the scheduler refreshes every minute (routes/console.php). */
    public const SCHEDULER_HEARTBEAT_KEY = 'health-check:scheduler-heartbeat';

    protected $signature = 'app:health-check
        {--mail= : Also send a test email to this address through the queue}
        {--wait=20 : Seconds to wait for the queue worker to run the probe job}';

    protected $description = 'Check the database, cache, queue, scheduler, mail, Reverb and public site';

    public function handle(): int
    {
        $checks = [
            'Config' => $this->checkConfig(...),
            'Database' => $this->checkDatabase(...),
            'Cache' => $this->checkCache(...),
            'Valkey' => $this->checkValkey(...),
            'Queue worker' => $this->checkQueueWorker(...),
            'Horizon' => $this->checkHorizon(...),
            'Failed jobs' => $this->checkFailedJobs(...),
            'Scheduler' => $this->checkScheduler(...),
            'Mail' => $this->checkMail(...),
            'Reverb (server)' => $this->checkReverbServer(...),
            'Public site' => $this->checkPublicSite(...),
            'Reverb (browser)' => $this->checkReverbBrowser(...),
            'Storage' => $this->checkStorage(...),
            'Backups' => $this->checkBackups(...),
        ];

        $counts = ['OK' => 0, 'WARN' => 0, 'FAIL' => 0, 'SKIP' => 0];

        foreach ($checks as $name => $check) {
            try {
                [$status, $detail] = $check();
            } catch (Throwable $exception) {
                [$status, $detail] = ['FAIL', Str::limit($exception->getMessage(), 200)];
            }

            $counts[$status]++;

            $this->components->twoColumnDetail($name, "<fg=gray>{$detail}</> ".$this->styleStatus($status));
        }

        $this->newLine();

        if ($counts['FAIL'] > 0) {
            $this->components->error("{$counts['FAIL']} check(s) failed, {$counts['WARN']} warning(s).");

            return self::FAILURE;
        }

        $this->components->info("All checks passed, {$counts['WARN']} warning(s).");

        return self::SUCCESS;
    }

    protected function styleStatus(string $status): string
    {
        return match ($status) {
            'OK' => '<fg=green;options=bold>OK</>',
            'WARN' => '<fg=yellow;options=bold>WARN</>',
            'FAIL' => '<fg=red;options=bold>FAIL</>',
            default => '<fg=gray;options=bold>SKIP</>',
        };
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function checkConfig(): array
    {
        $problems = array_filter([
            app()->isProduction() ? null : 'APP_ENV is '.app()->environment(),
            config('app.debug') ? 'APP_DEBUG is on' : null,
            blank(config('app.key')) ? 'APP_KEY is missing' : null,
            str_starts_with((string) config('app.url'), 'https://') ? null : 'APP_URL is not https',
            blank(config('sentry.dsn')) ? 'Sentry has no DSN' : null,
        ]);

        if ($problems === []) {
            return ['OK', 'production, debug off, https'];
        }

        return [app()->isProduction() ? 'FAIL' : 'WARN', implode('; ', $problems)];
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function checkDatabase(): array
    {
        DB::select('select 1');

        $migrator = app('migrator');
        $files = $migrator->getMigrationFiles([database_path('migrations'), ...$migrator->paths()]);
        $pending = array_diff(array_keys($files), $migrator->getRepository()->getRan());

        if ($pending !== []) {
            return ['FAIL', count($pending).' pending migration(s)'];
        }

        return ['OK', DB::connection()->getDriverName().', no pending migrations'];
    }

    /**
     * Round-trips the shape Pulse caches (a collection of plain objects with
     * dates), which cache.serializable_classes once broke.
     *
     * @return array{0: string, 1: string}
     */
    protected function checkCache(): array
    {
        $key = 'health-check:cache:'.Str::random(8);

        Cache::put($key, collect([(object) ['at' => CarbonImmutable::now()]]), 60);
        $cached = Cache::pull($key);

        $row = $cached instanceof Collection ? $cached->first() : null;

        if (! $row instanceof stdClass || ! ($row->at ?? null) instanceof CarbonImmutable) {
            return ['FAIL', 'a cached collection came back broken (see cache.serializable_classes)'];
        }

        return ['OK', 'store '.config('cache.default')];
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function checkValkey(): array
    {
        if (! in_array('redis', [config('cache.default'), config('queue.default')], true)) {
            return ['SKIP', 'neither the cache nor the queue uses redis'];
        }

        $connection = Redis::connection();
        $connection->ping();

        $info = $connection->command('info', ['memory']);
        $usedBytes = (int) ($info['used_memory'] ?? $info['Memory']['used_memory'] ?? 0);
        $usedMegabytes = round($usedBytes / 1024 / 1024, 1);

        if ($usedMegabytes > 200) {
            return ['WARN', "{$usedMegabytes} MB used of the 256 MB limit"];
        }

        return ['OK', "{$usedMegabytes} MB used"];
    }

    /**
     * Dispatches a job that marks a cache key, then waits for a worker to run
     * it. This proves the worker is up and shares the app's queue and cache.
     *
     * @return array{0: string, 1: string}
     */
    protected function checkQueueWorker(): array
    {
        $key = 'health-check:queue:'.Str::random(8);

        dispatch(static function () use ($key): void {
            Cache::put($key, true, 300);
        });

        $deadline = microtime(true) + (int) $this->option('wait');

        do {
            if (Cache::pull($key)) {
                return ['OK', 'a worker ran the probe job on '.config('queue.default')];
            }

            usleep(250_000);
        } while (microtime(true) < $deadline);

        return ['FAIL', "no worker ran the probe job within {$this->option('wait')}s: is the worker container up?"];
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function checkHorizon(): array
    {
        if (config('queue.default') !== 'redis') {
            return ['SKIP', 'the queue is '.config('queue.default')];
        }

        $masters = collect(app(MasterSupervisorRepository::class)->all());

        if ($masters->isEmpty()) {
            return ['FAIL', 'Horizon is not running'];
        }

        if ($masters->contains(fn (stdClass $master): bool => $master->status === 'paused')) {
            return ['WARN', 'Horizon is paused'];
        }

        return ['OK', 'running'];
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function checkFailedJobs(): array
    {
        $since = CarbonImmutable::now()->subDay();

        $recent = collect(app('queue.failer')->all())
            ->filter(fn (stdClass $job): bool => CarbonImmutable::parse($job->failed_at)->isAfter($since))
            ->count();

        if ($recent > 0) {
            return ['WARN', "{$recent} in the last 24 hours (see /horizon or queue:failed)"];
        }

        return ['OK', 'none in the last 24 hours'];
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function checkScheduler(): array
    {
        $heartbeat = Cache::get(self::SCHEDULER_HEARTBEAT_KEY);

        if ($heartbeat === null) {
            return ['FAIL', 'no heartbeat: the scheduler has not run (it beats every minute)'];
        }

        $secondsAgo = (int) abs(CarbonImmutable::parse($heartbeat)->diffInSeconds(CarbonImmutable::now()));

        if ($secondsAgo > 180) {
            return ['FAIL', 'last ran '.CarbonImmutable::parse($heartbeat)->diffForHumans().': is the scheduler container up?'];
        }

        return ['OK', "last ran {$secondsAgo}s ago"];
    }

    /**
     * Signs in to the SMTP server without sending anything, and with --mail
     * also queues a real email so the worker sends it.
     *
     * @return array{0: string, 1: string}
     */
    protected function checkMail(): array
    {
        $mailer = (string) config('mail.default');
        $transport = Mail::mailer()->getSymfonyTransport();

        if (! $transport instanceof SmtpTransport) {
            return [app()->isProduction() ? 'FAIL' : 'SKIP', "the mailer is {$mailer}, which sends no email"];
        }

        $transport->start();
        $transport->stop();

        $detail = 'signed in to '.config("mail.mailers.{$mailer}.host");
        $address = $this->option('mail');

        if (blank($address)) {
            return ['OK', $detail];
        }

        if (! filter_var($address, FILTER_VALIDATE_EMAIL)) {
            return ['FAIL', "{$address} is not an email address"];
        }

        dispatch(static function () use ($address): void {
            Mail::raw(
                'This is a test email from app:health-check. If it arrived, queued email works.',
                fn (Message $message) => $message->to($address)->subject(config('app.name').' health check'),
            );
        });

        return ['OK', "{$detail}; test email queued to {$address}"];
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function checkReverbServer(): array
    {
        if (config('broadcasting.default') !== 'reverb') {
            return [app()->isProduction() ? 'FAIL' : 'SKIP', 'broadcasting is '.config('broadcasting.default')];
        }

        /** @var PusherBroadcaster $broadcaster */
        $broadcaster = Broadcast::connection('reverb');
        $broadcaster->getPusher()->trigger('health-check', 'ping', ['at' => CarbonImmutable::now()->toIso8601String()]);

        return ['OK', 'published an event to '.config('broadcasting.connections.reverb.options.host')];
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function checkPublicSite(): array
    {
        $url = rtrim((string) config('app.url'), '/').'/up';
        $response = Http::timeout(10)->get($url);

        return [$response->successful() ? 'OK' : 'FAIL', "{$url} answered {$response->status()}"];
    }

    /**
     * Opens a WebSocket the way the browser does, through the public domain,
     * so it covers DNS, TLS, Caddy's /app proxy and Reverb together.
     *
     * @return array{0: string, 1: string}
     */
    protected function checkReverbBrowser(): array
    {
        if (config('broadcasting.default') !== 'reverb') {
            return [app()->isProduction() ? 'FAIL' : 'SKIP', 'broadcasting is '.config('broadcasting.default')];
        }

        $appUrl = parse_url((string) config('app.url'));
        $scheme = config('broadcasting.client.scheme') ?: ($appUrl['scheme'] ?? 'https');
        $host = config('broadcasting.client.host') ?: ($appUrl['host'] ?? 'localhost');
        $port = (int) (config('broadcasting.client.port') ?: ($scheme === 'https' ? 443 : 80));
        $url = ($scheme === 'https' ? 'wss' : 'ws')."://{$host}:{$port}/app";

        $socket = @stream_socket_client(($scheme === 'https' ? 'tls' : 'tcp')."://{$host}:{$port}", $errorCode, $errorMessage, 10);

        if ($socket === false) {
            return ['FAIL', "could not connect to {$url}: {$errorMessage}"];
        }

        stream_set_timeout($socket, 10);

        fwrite($socket, implode("\r\n", [
            'GET /app/'.config('broadcasting.client.key').'?protocol=7&client=js&version=8.4.0&flash=false HTTP/1.1',
            "Host: {$host}",
            "Origin: {$scheme}://{$host}",
            'Upgrade: websocket',
            'Connection: Upgrade',
            'Sec-WebSocket-Key: '.base64_encode(random_bytes(16)),
            'Sec-WebSocket-Version: 13',
            '',
            '',
        ]));

        $statusLine = trim((string) fgets($socket));
        fclose($socket);

        if (! str_contains($statusLine, ' 101 ')) {
            return ['FAIL', "{$url} answered \"{$statusLine}\" instead of 101"];
        }

        return ['OK', "{$url} accepted a WebSocket"];
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function checkStorage(): array
    {
        $unwritable = array_filter(
            [storage_path('app'), storage_path('logs'), storage_path('framework/views')],
            fn (string $path): bool => ! is_writable($path),
        );

        if ($unwritable !== []) {
            return ['FAIL', 'not writable: '.implode(', ', $unwritable)];
        }

        return ['OK', 'writable'];
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function checkBackups(): array
    {
        $disk = Storage::disk(config('filesystems.private_disk'));
        $latest = collect($disk->files('backups'))->map(fn (string $path): int => $disk->lastModified($path))->max();

        if ($latest === null) {
            return ['WARN', 'no backup yet (app:backup-database runs at midnight Manila time)'];
        }

        $takenAt = CarbonImmutable::createFromTimestamp($latest);

        if ($takenAt->isBefore(CarbonImmutable::now()->subHours(26))) {
            return ['WARN', 'the latest backup is from '.$takenAt->diffForHumans()];
        }

        return ['OK', 'the latest backup is from '.$takenAt->diffForHumans()];
    }
}
