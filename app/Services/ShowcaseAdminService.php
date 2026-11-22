<?php

namespace App\Services;

use App\Data\WrittenFiles;
use App\Enums\AuditEvent;
use App\Models\Showcase;
use App\Models\ShowcaseImage;
use App\Models\User;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use App\Support\StoredFileName;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * The admin's Showcase page writes: adding a showcase, changing one (its
 * thumbnail and gallery included) and archiving one. The public pages read
 * showcases through ShowcaseService; this is the admin's side.
 *
 * Images go on the public disk (config('filesystems.public_disk')) under
 * projects/ (the folder's name from before showcases were called that, kept
 * so every showcase image, old and new, is in one place), as every upload in
 * Regray does:
 *
 * 1. stored under a random name (StoredFileName), never the uploader's own;
 * 2. with the uploader's name, the type read from the bytes and the size kept
 *    on the row, to show to people only;
 * 3. a replaced thumbnail's file is deleted first, then the new one is
 *    uploaded, and a photo taken off the gallery has its file deleted, so
 *    nothing unused stays in storage. The form request has already validated
 *    the uploads by then, so a bad one never deletes anything. A removed
 *    photo's file goes once the save has committed, so a failed save keeps it;
 * 4. whatever this write stored is deleted again if the write fails after it.
 *
 * The stones, categories and gallery order change in the showcase's own
 * transaction. The gallery is renumbered through negative places first, as
 * `showcase_images` holds (showcase_id, sort_order) unique.
 *
 * A showcase is published once: `published_at` is set the first time it is,
 * and kept when it goes back to a draft.
 *
 * Archiving is the soft delete, and keeps the files: its rows still point at
 * them, and the saved stone's showcases still show it.
 */
class ShowcaseAdminService
{
    private const DIRECTORY = 'projects';

    public function __construct(
        private readonly AuditLogRepositoryInterface $auditLogs,
    ) {}

    /**
     * @param  array{name: string, slug: string, location: string, completed_year: int, overview: string, is_published: bool, stones: list<int>, categories: list<int>}  $data
     * @param  list<array{id: int|null, file: UploadedFile|null}>  $gallery
     */
    public function create(array $data, UploadedFile $thumbnail, array $gallery, User $by, ?string $ipAddress, ?string $userAgent): Showcase
    {
        return $this->writing(function (WrittenFiles $written) use ($data, $thumbnail, $gallery, $by, $ipAddress, $userAgent): Showcase {
            $showcase = new Showcase(self::columns($data));
            $showcase->forceFill([
                ...self::thumbnailColumns($this->store($thumbnail, $written), $thumbnail),
                'published_at' => $data['is_published'] ? now() : null,
            ]);
            $photos = $this->storePhotos($gallery, $written);

            return DB::transaction(function () use ($showcase, $data, $gallery, $photos, $by, $ipAddress, $userAgent): Showcase {
                $showcase->save();
                $showcase->stones()->sync($data['stones']);
                $showcase->categories()->sync($data['categories']);
                $this->arrangeGallery($showcase, $gallery, $photos);
                $this->audit($by, AuditEvent::Created, $showcase, null, self::snapshot($showcase), $ipAddress, $userAgent);

                return $showcase;
            });
        });
    }

    /**
     * Change a showcase. A thumbnail sent replaces the current one; none sent
     * keeps it. The gallery becomes `$gallery`, in its order: saved photos
     * kept by id, new ones added, the rest taken off.
     *
     * @param  array{name: string, slug: string, location: string, completed_year: int, overview: string, is_published: bool, stones: list<int>, categories: list<int>}  $data
     * @param  list<array{id: int|null, file: UploadedFile|null}>  $gallery
     */
    public function update(Showcase $showcase, array $data, ?UploadedFile $thumbnail, array $gallery, User $by, ?string $ipAddress, ?string $userAgent): Showcase
    {
        $before = self::snapshot($showcase);

        $removed = $this->writing(function (WrittenFiles $written) use ($showcase, $data, $thumbnail, $gallery, $before, $by, $ipAddress, $userAgent): array {
            $showcase->fill(self::columns($data));

            if ($data['is_published'] && $showcase->published_at === null) {
                $showcase->fill(['published_at' => now()]);
            }

            if ($thumbnail !== null) {
                $this->delete($showcase->thumbnail_path);
                $showcase->forceFill(self::thumbnailColumns($this->store($thumbnail, $written), $thumbnail));
            }

            $photos = $this->storePhotos($gallery, $written);

            return DB::transaction(function () use ($showcase, $data, $gallery, $photos, $before, $by, $ipAddress, $userAgent): array {
                $showcase->save();
                $showcase->stones()->sync($data['stones']);
                $showcase->categories()->sync($data['categories']);
                $removed = $this->arrangeGallery($showcase, $gallery, $photos);
                $this->audit($by, AuditEvent::Updated, $showcase, $before, self::snapshot($showcase), $ipAddress, $userAgent);

                return $removed;
            });
        });

        // The photos taken off, now the save has committed.
        foreach ($removed as $path) {
            $this->delete($path);
        }

        return $showcase;
    }

    /**
     * Archive a showcase: it leaves the Showcase page and its page goes
     * offline everywhere (the soft delete). Its rows, files and slug stay.
     */
    public function archive(Showcase $showcase, User $by, ?string $ipAddress, ?string $userAgent): Showcase
    {
        DB::transaction(function () use ($showcase, $by, $ipAddress, $userAgent): void {
            $before = self::snapshot($showcase);
            $showcase->delete();
            $this->audit($by, AuditEvent::Archived, $showcase, $before, self::snapshot($showcase), $ipAddress, $userAgent);
        });

        return $showcase;
    }

    /**
     * Make the gallery `$gallery`, in its order: take off the saved photos it
     * leaves out, renumber the kept ones, add the new ones (already stored, in
     * `$photos` by row). Returns the paths of the photos taken off, for their
     * files to go once this commits.
     *
     * @param  list<array{id: int|null, file: UploadedFile|null}>  $gallery
     * @param  array<int, array{path: string, file: UploadedFile}>  $photos
     * @return list<string>
     */
    private function arrangeGallery(Showcase $showcase, array $gallery, array $photos): array
    {
        $kept = array_values(array_filter(array_column($gallery, 'id')));
        $removed = $showcase->images()->whereNotIn('id', $kept)->get(['id', 'path']);
        $showcase->images()->whereKey($removed->modelKeys())->delete();

        // Out of the way first: (showcase_id, sort_order) is unique.
        $showcase->images()->update(['sort_order' => DB::raw('-1 - sort_order')]);

        foreach ($gallery as $order => $row) {
            if ($row['id'] !== null) {
                $showcase->images()->whereKey($row['id'])->update(['sort_order' => $order]);

                continue;
            }

            $photo = $photos[$order];
            $showcase->images()->create([
                'path' => $photo['path'],
                'sort_order' => $order,
                ...self::fileColumns($photo['file']),
            ]);
        }

        $showcase->unsetRelation('gallery');

        return array_values($removed->map(fn (ShowcaseImage $image): string => $image->path)->all());
    }

    /**
     * Store the gallery's new photos, by the row they are on.
     *
     * @param  list<array{id: int|null, file: UploadedFile|null}>  $gallery
     * @return array<int, array{path: string, file: UploadedFile}>
     */
    private function storePhotos(array $gallery, WrittenFiles $written): array
    {
        $photos = [];

        foreach ($gallery as $order => $row) {
            if ($row['file'] !== null) {
                $photos[$order] = ['path' => $this->store($row['file'], $written), 'file' => $row['file']];
            }
        }

        return $photos;
    }

    /**
     * The showcase's own columns from the form's data, without its stones and
     * categories.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function columns(array $data): array
    {
        return array_diff_key($data, array_flip(['stones', 'categories']));
    }

    /**
     * Run a write that may store files, deleting what it stored if it fails.
     *
     * @template T
     *
     * @param  \Closure(WrittenFiles): T  $write
     * @return T
     */
    private function writing(\Closure $write): mixed
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

    /** Put an image under projects/ with a random name, recorded as this write's. */
    private function store(UploadedFile $file, WrittenFiles $written): string
    {
        $path = $this->disk()->putFileAs(self::DIRECTORY, $file, StoredFileName::for($file));

        if ($path === false) {
            throw new RuntimeException('Could not store the showcase image.');
        }

        $written->record($path);

        return $path;
    }

    /** Delete an image's file, if it has one on the disk. */
    private function delete(?string $path): void
    {
        if ($path !== null && $path !== '' && ! str_starts_with($path, '/')) {
            $this->disk()->delete($path);
        }
    }

    /**
     * The thumbnail's columns: its path, and the uploader's name, the type
     * read from its bytes and its size.
     *
     * @return array<string, string|int|null>
     */
    private static function thumbnailColumns(string $path, UploadedFile $file): array
    {
        $columns = self::fileColumns($file);

        return [
            'thumbnail_path' => $path,
            'thumbnail_original_name' => $columns['original_name'],
            'thumbnail_mime_type' => $columns['mime_type'],
            'thumbnail_size_bytes' => $columns['size_bytes'],
        ];
    }

    /** @return array{original_name: string, mime_type: string|null, size_bytes: int|null} */
    private static function fileColumns(UploadedFile $file): array
    {
        return [
            'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize() ?: null,
        ];
    }

    private function disk(): Filesystem
    {
        return Storage::disk(config('filesystems.public_disk'));
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    private function audit(User $by, AuditEvent $event, Showcase $showcase, ?array $before, ?array $after, ?string $ipAddress, ?string $userAgent): void
    {
        $this->auditLogs->record($by->id, $event, $showcase, $before, $after, $ipAddress, $userAgent);
    }

    /**
     * What an audit row records of a showcase.
     *
     * @return array<string, mixed>
     */
    private static function snapshot(Showcase $showcase): array
    {
        return [
            'name' => $showcase->name,
            'slug' => $showcase->slug,
            'location' => $showcase->location,
            'completed_year' => $showcase->completed_year,
            'overview' => $showcase->overview,
            'thumbnail_path' => $showcase->thumbnail_path,
            'gallery' => $showcase->images()->orderBy('sort_order')->pluck('path')->all(),
            'stones' => $showcase->stones()->orderBy('stones.id')->pluck('stones.id')->all(),
            'categories' => $showcase->categories()->orderBy('showcase_categories.id')->pluck('showcase_categories.id')->all(),
            'is_published' => $showcase->is_published,
            'published_at' => $showcase->published_at?->toIso8601String(),
            'archived_at' => $showcase->deleted_at?->toIso8601String(),
        ];
    }
}
