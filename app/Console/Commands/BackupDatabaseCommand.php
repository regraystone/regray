<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class BackupDatabaseCommand extends Command
{
    protected $signature = 'app:backup-database';

    protected $description = 'Dump the database and store it on the private disk';

    public function handle(): int
    {
        $temporaryPath = null;
        $stream = null;

        try {
            if (config('database.default') !== 'pgsql') {
                throw new RuntimeException('Database backups support only the pgsql default connection.');
            }

            $connection = config('database.connections.pgsql');
            $temporaryPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'regray-backup-'.uniqid().'.dump';

            $result = Process::forever()
                ->env(['PGPASSWORD' => (string) $connection['password']])
                ->run([
                    'pg_dump', '-Fc', '--no-owner', '--no-acl', '--no-password',
                    '--host', (string) $connection['host'],
                    '--port', (string) $connection['port'],
                    '--username', (string) $connection['username'],
                    '--dbname', (string) $connection['database'],
                    '--file', $temporaryPath,
                ]);

            if ($result->failed()) {
                throw new RuntimeException('pg_dump failed with exit code '.$result->exitCode().'. Ensure pg_dump is installed and the database is accessible.');
            }

            clearstatcache(true, $temporaryPath);

            if (! is_file($temporaryPath) || filesize($temporaryPath) <= 0) {
                throw new RuntimeException('pg_dump did not produce a non-empty dump file.');
            }

            $stream = fopen($temporaryPath, 'rb');

            if ($stream === false) {
                throw new RuntimeException('Unable to open the database dump file.');
            }

            $disk = Storage::disk(config('filesystems.private_disk'));
            $path = 'backups/'.now()->format('Y-m-d_His').'.dump';

            if (! $disk->writeStream($path, $stream)) {
                throw new RuntimeException('Unable to store the database dump on the private disk.');
            }

            $this->info('Database backup stored at '.$path);

            $this->pruneOldBackups($disk);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $message = 'Database backup failed: '.$exception->getMessage();
            Log::error($message);
            $this->error($message);

            return self::FAILURE;
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }

            if (is_string($temporaryPath) && is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }

    /**
     * Delete dumps older than the retention window, read from each file's own
     * timestamped name rather than the disk's mtime (S3 does not promise one).
     * A failure here is logged but never fails the backup that just succeeded.
     */
    private function pruneOldBackups(Filesystem $disk): void
    {
        $retentionDays = (int) config('backup.retention_days', 30);

        if ($retentionDays <= 0) {
            return;
        }

        try {
            $cutoff = now()->subDays($retentionDays)->getTimestamp();

            foreach ($disk->files('backups') as $path) {
                if (preg_match('#(\d{4}-\d{2}-\d{2}_\d{6})\.dump$#', $path, $matches) !== 1) {
                    continue;
                }

                $takenAt = \DateTimeImmutable::createFromFormat('Y-m-d_His', $matches[1]);

                if ($takenAt !== false && $takenAt->getTimestamp() < $cutoff) {
                    $disk->delete($path);
                    $this->info('Pruned old backup '.$path);
                }
            }
        } catch (Throwable $exception) {
            Log::warning('Could not prune old database backups: '.$exception->getMessage());
        }
    }
}
