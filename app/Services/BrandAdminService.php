<?php

namespace App\Services;

use App\Data\WrittenFiles;
use App\Enums\AuditEvent;
use App\Models\Brand;
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
 * The admin's Brands page writes: adding a brand, changing one (its images
 * included) and archiving one. The public brand pages read brands through
 * BrandService; this is the admin's side.
 *
 * Images go on the public disk (config('filesystems.public_disk')) under
 * brands/, as every upload in Regray does:
 *
 * 1. stored under a random name (StoredFileName), never the uploader's own;
 * 2. with the uploader's name, the type read from the bytes and the size kept
 *    on the row, to show to people only;
 * 3. a replaced image's file is deleted first, then the new one is uploaded,
 *    and an image taken off has its file deleted, so nothing unused stays in
 *    storage. The form request has already validated the upload by then, so
 *    a bad one never deletes anything;
 * 4. whatever this write stored is deleted again if the write fails after it.
 *
 * Its facts, what it makes and its social links are rows of their own
 * (brand_facts, brand_makes, brand_socials). A save replaces them all with
 * the form's, in its order, in the same transaction as the brand.
 *
 * Archiving keeps the brand's files: its row still points at them.
 */
class BrandAdminService
{
    private const DIRECTORY = 'brands';

    public function __construct(
        private readonly AuditLogRepositoryInterface $auditLogs,
    ) {}

    /**
     * @param  array{name: string, slug: string, tagline: string|null, description: string|null, website_url: string|null, is_active: bool, sort_order: int, facts: list<array{label: string, value: string}>, makes: list<string>, socials: list<array{platform: string, url: string}>}  $data
     */
    public function create(array $data, UploadedFile $logo, ?UploadedFile $thumbnail, User $by, ?string $ipAddress, ?string $userAgent): Brand
    {
        return $this->writing(function (WrittenFiles $written) use ($data, $logo, $thumbnail, $by, $ipAddress, $userAgent): Brand {
            $brand = new Brand(self::columns($data));
            $brand->forceFill(self::imageColumns('logo', $this->store($logo, $written), $logo));

            if ($thumbnail !== null) {
                $brand->forceFill(self::imageColumns('thumbnail', $this->store($thumbnail, $written), $thumbnail));
            }

            return DB::transaction(function () use ($brand, $data, $by, $ipAddress, $userAgent): Brand {
                $brand->save();
                $brand->replaceDetails($data);
                $this->audit($by, AuditEvent::Created, $brand, null, self::snapshot($brand), $ipAddress, $userAgent);

                return $brand;
            });
        });
    }

    /**
     * Change a brand. An image sent replaces the current one; none sent keeps
     * it. `$removeThumbnail` takes the thumbnail off when no new one is sent.
     *
     * @param  array{name: string, slug: string, tagline: string|null, description: string|null, website_url: string|null, is_active: bool, sort_order: int, facts: list<array{label: string, value: string}>, makes: list<string>, socials: list<array{platform: string, url: string}>}  $data
     */
    public function update(Brand $brand, array $data, ?UploadedFile $logo, ?UploadedFile $thumbnail, bool $removeThumbnail, User $by, ?string $ipAddress, ?string $userAgent): Brand
    {
        $before = self::snapshot($brand);

        return $this->writing(function (WrittenFiles $written) use ($brand, $data, $logo, $thumbnail, $removeThumbnail, $before, $by, $ipAddress, $userAgent): Brand {
            $brand->fill(self::columns($data));

            if ($logo !== null) {
                $this->delete($brand->logo_path);
                $brand->forceFill(self::imageColumns('logo', $this->store($logo, $written), $logo));
            }

            if ($thumbnail !== null) {
                $this->delete($brand->thumbnail_path);
                $brand->forceFill(self::imageColumns('thumbnail', $this->store($thumbnail, $written), $thumbnail));
            } elseif ($removeThumbnail && $brand->thumbnail_path !== null) {
                $this->delete($brand->thumbnail_path);
                $brand->forceFill(self::imageColumns('thumbnail', null, null));
            }

            return DB::transaction(function () use ($brand, $data, $before, $by, $ipAddress, $userAgent): Brand {
                $brand->save();
                $brand->replaceDetails($data);
                $this->audit($by, AuditEvent::Updated, $brand, $before, self::snapshot($brand), $ipAddress, $userAgent);

                return $brand;
            });
        });
    }

    /**
     * Archive a brand: it leaves the Brands page and goes offline everywhere,
     * its stones and furniture with it (Brand::active(), Stone::active(),
     * Furniture::active()). Its row, files and slug stay.
     */
    public function archive(Brand $brand, User $by, ?string $ipAddress, ?string $userAgent): Brand
    {
        DB::transaction(function () use ($brand, $by, $ipAddress, $userAgent): void {
            $before = self::snapshot($brand);
            $brand->forceFill(['archived_at' => now()])->save();
            $this->audit($by, AuditEvent::Archived, $brand, $before, self::snapshot($brand), $ipAddress, $userAgent);
        });

        return $brand;
    }

    /**
     * The brand's own columns from the form's data, without its detail rows.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function columns(array $data): array
    {
        return array_diff_key($data, array_flip(Brand::DETAILS));
    }

    /**
     * Run a write that may store files, deleting what it stored if it fails.
     *
     * @param  \Closure(WrittenFiles): Brand  $write
     */
    private function writing(\Closure $write): Brand
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

    /** Put an image under brands/ with a random name, recorded as this write's. */
    private function store(UploadedFile $file, WrittenFiles $written): string
    {
        $path = $this->disk()->putFileAs(self::DIRECTORY, $file, StoredFileName::for($file));

        if ($path === false) {
            throw new RuntimeException('Could not store the brand image.');
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
     * An image's columns: its path, and the uploader's name, the type read
     * from its bytes and its size. All null when it is taken off.
     *
     * @param  'logo'|'thumbnail'  $image
     * @return array<string, string|int|null>
     */
    private static function imageColumns(string $image, ?string $path, ?UploadedFile $file): array
    {
        return [
            "{$image}_path" => $path,
            "{$image}_original_name" => $file === null ? null : Str::limit($file->getClientOriginalName(), 250, ''),
            "{$image}_mime_type" => $file?->getMimeType(),
            "{$image}_size_bytes" => $file?->getSize() ?: null,
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
    private function audit(User $by, AuditEvent $event, Brand $brand, ?array $before, ?array $after, ?string $ipAddress, ?string $userAgent): void
    {
        $this->auditLogs->record($by->id, $event, $brand, $before, $after, $ipAddress, $userAgent);
    }

    /**
     * What an audit row records of a brand.
     *
     * @return array<string, mixed>
     */
    private static function snapshot(Brand $brand): array
    {
        return [
            'name' => $brand->name,
            'slug' => $brand->slug,
            'tagline' => $brand->tagline,
            'description' => $brand->description,
            'website_url' => $brand->website_url,
            'logo_path' => $brand->logo_path,
            'thumbnail_path' => $brand->thumbnail_path,
            ...$brand->loadMissing(Brand::DETAILS)->details(),
            'is_active' => $brand->is_active,
            'sort_order' => $brand->sort_order,
            'archived_at' => $brand->archived_at?->toIso8601String(),
        ];
    }
}
