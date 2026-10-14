<?php

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config([
        'database.default' => 'pgsql',
        'database.connections.pgsql.host' => 'backup-db.test',
        'database.connections.pgsql.port' => '5433',
        'database.connections.pgsql.database' => 'backup_database',
        'database.connections.pgsql.username' => 'backup_user',
        'database.connections.pgsql.password' => 'test-backup-password',
        'filesystems.private_disk' => 'local',
    ]);
});

test('it streams a successful database dump to the private disk and removes the temporary file', function () {
    $this->travelTo(now()->setDate(2026, 10, 14)->setTime(12, 34, 56));
    $disk = Storage::fake(config('filesystems.private_disk'));
    $temporaryPath = null;
    Process::preventStrayProcesses();
    Process::fake(function (PendingProcess $process) use (&$temporaryPath): ProcessResult {
        $temporaryPath = $process->command[array_search('--file', $process->command, true) + 1];
        expect(is_file($temporaryPath))->toBeFalse();
        file_put_contents($temporaryPath, 'non-empty database dump');

        return Process::result();
    });

    $this->artisan('app:backup-database')
        ->expectsOutput('Database backup stored at backups/2026-10-14_123456.dump')
        ->assertSuccessful();

    $disk->assertExists('backups/2026-10-14_123456.dump');
    expect($disk->get('backups/2026-10-14_123456.dump'))->toBe('non-empty database dump');
    expect(is_file($temporaryPath))->toBeFalse();
    Process::assertRan(function (PendingProcess $process) use ($temporaryPath): bool {
        return $process->command === [
            'pg_dump', '-Fc', '--no-owner', '--no-acl', '--no-password',
            '--host', 'backup-db.test',
            '--port', '5433',
            '--username', 'backup_user',
            '--dbname', 'backup_database',
            '--file', $temporaryPath,
        ] && $process->environment['PGPASSWORD'] === 'test-backup-password';
    });
});

test('it logs failed or invalid dumps without storing a backup and removes the temporary file', function (int $exitCode, ?string $contents, string $error) {
    $disk = Storage::fake(config('filesystems.private_disk'));
    Log::spy();
    $temporaryPath = null;
    Process::preventStrayProcesses();
    Process::fake(function (PendingProcess $process) use (&$temporaryPath, $exitCode, $contents): ProcessResult {
        $temporaryPath = $process->command[array_search('--file', $process->command, true) + 1];

        if ($contents !== null) {
            file_put_contents($temporaryPath, $contents);
        }

        return Process::result(exitCode: $exitCode);
    });

    $this->artisan('app:backup-database')
        ->expectsOutputToContain($error)
        ->assertFailed();

    $disk->assertDirectoryEmpty('backups');
    expect(is_file($temporaryPath))->toBeFalse();
    Log::shouldHaveReceived('error')->once()->with('Database backup failed: '.$error);
    Process::assertRan(fn (PendingProcess $process): bool => $process->command[0] === 'pg_dump');
})->with([
    'non-zero exit with a partial file' => [1, 'partial dump', 'pg_dump failed with exit code 1. Ensure pg_dump is installed and the database is accessible.'],
    'empty file' => [0, '', 'pg_dump did not produce a non-empty dump file.'],
    'missing file' => [0, null, 'pg_dump did not produce a non-empty dump file.'],
]);

test('it fails and cleans up when pg_dump cannot be started', function () {
    $disk = Storage::fake(config('filesystems.private_disk'));
    Log::spy();
    $temporaryPath = null;
    Process::preventStrayProcesses();
    Process::fake(function (PendingProcess $process) use (&$temporaryPath): ProcessResult {
        $temporaryPath = $process->command[array_search('--file', $process->command, true) + 1];

        throw new RuntimeException('pg_dump executable not found.');
    });

    $this->artisan('app:backup-database')
        ->expectsOutputToContain('pg_dump executable not found.')
        ->assertFailed();

    $disk->assertDirectoryEmpty('backups');
    expect(is_file($temporaryPath))->toBeFalse();
    Log::shouldHaveReceived('error')->once()->with('Database backup failed: pg_dump executable not found.');
});

test('it rejects a default connection other than pgsql without invoking pg_dump', function () {
    $disk = Storage::fake(config('filesystems.private_disk'));
    Log::spy();
    Process::fake();
    Process::preventStrayProcesses();
    config(['database.default' => 'sqlite']);

    try {
        $this->artisan('app:backup-database')
            ->expectsOutputToContain('Database backups support only the pgsql default connection.')
            ->assertFailed();
    } finally {
        config(['database.default' => 'pgsql']);
    }

    $disk->assertDirectoryEmpty('backups');
    Process::assertNothingRan();
    Log::shouldHaveReceived('error')->once()->with('Database backup failed: Database backups support only the pgsql default connection.');
});
