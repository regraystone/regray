<?php

namespace App\Support;

use App\Data\WrittenFiles;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * The admin's images on the public disk (config('filesystems.public_disk')),
 * stored the way every upload in Regray is: under a random name
 * (StoredFileName), never the uploader's own, and deleted again if the write
 * that stored them fails after it (WrittenFiles).
 *
 * Used by the Products pages' writes; ShowcaseAdminService and
 * BrandAdminService keep their own copies of the same steps.
 */
final class PublicImageStore
{
    /**
     * Run a write that may store files, deleting what it stored if it fails.
     *
     * @template T
     *
     * @param  \Closure(WrittenFiles): T  $write
     * @return T
     */
    public function writing(\Closure $write): mixed
    {
        $written = new WrittenFiles;

        try {
            return $write($written);
        } catch (Throwable $exception) {
            if ($written->paths() !== []) {
                $this->disk()->delete($written->paths());
            }

            throw $exception;
        }
    }

    /** Put an image under `$directory` with a random name, recorded as this write's. */
    public function store(UploadedFile $file, string $directory, WrittenFiles $written): string
    {
        $path = $this->disk()->putFileAs($directory, $file, StoredFileName::for($file));

        if ($path === false) {
            throw new RuntimeException('Could not store the image.');
        }

        $written->record($path);

        return $path;
    }

    /**
     * Delete images' files: each one on the disk (not a path into the app's
     * own public folder), and not among `$stillUsed`.
     *
     * @param  iterable<string|null>  $paths
     * @param  list<string>  $stillUsed
     */
    public function delete(iterable $paths, array $stillUsed = []): void
    {
        foreach ($paths as $path) {
            if ($path !== null && $path !== '' && ! str_starts_with($path, '/') && ! in_array($path, $stillUsed, true)) {
                $this->disk()->delete($path);
            }
        }
    }

    private function disk(): Filesystem
    {
        return Storage::disk(config('filesystems.public_disk'));
    }
}
