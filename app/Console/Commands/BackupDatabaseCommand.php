<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
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

            $path = 'backups/'.now()->format('Y-m-d_His').'.dump';

            if (! Storage::disk(config('filesystems.private_disk'))->writeStream($path, $stream)) {
                throw new RuntimeException('Unable to store the database dump on the private disk.');
            }

            $this->info('Database backup stored at '.$path);

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
}
