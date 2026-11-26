<?php

namespace App\Services\Product;

use App\Data\WrittenFiles;
use App\Enums\AuditEvent;
use App\Models\Furniture;
use App\Models\User;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use App\Support\CatalogImages;
use App\Support\CatalogLocks;
use App\Support\PublicImageStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * The Products page's furniture writes: adding a piece, changing one (its
 * thumbnail and gallery included), turning it on or off and archiving it.
 * The public pages read furniture through FurnitureService.
 *
 * Images go on the public disk under furniture/, as the seeded ones are,
 * named by PublicImageStore; a replaced thumbnail's file and a photo taken
 * off the gallery go once the save has committed. A piece may have no
 * thumbnail: its page then shows the placeholder.
 *
 * Archiving is the soft delete: the piece leaves the Products page, its page
 * goes offline and saved lists drop it.
 */
class FurnitureAdminService
{
    private const DIRECTORY = 'furniture';

    public function __construct(
        private readonly AuditLogRepositoryInterface $auditLogs,
        private readonly PublicImageStore $images,
        private readonly ProductGallery $gallery,
    ) {}

    /**
     * @param  array{brand_id: int, furniture_type_id: int, name: string, slug: string, description: string|null, table_shape_option_id: int|null, table_base_design_option_id: int|null, is_active: bool}  $data
     * @param  list<array{id: int|null, file: UploadedFile|null}>  $gallery
     */
    public function create(array $data, ?UploadedFile $thumbnail, array $gallery, User $by, ?string $ipAddress, ?string $userAgent): Furniture
    {
        return $this->images->writing(function (WrittenFiles $written) use ($data, $thumbnail, $gallery, $by, $ipAddress, $userAgent): Furniture {
            $piece = new Furniture($data);
            $piece->forceFill([
                'thumbnail_path' => $thumbnail === null ? null : $this->images->store($thumbnail, self::DIRECTORY, $written),
                'sort_order' => self::nextPlace($data['brand_id']),
            ]);
            $stored = $this->gallery->store($gallery, self::DIRECTORY, $written);

            return DB::transaction(function () use ($piece, $gallery, $stored, $by, $ipAddress, $userAgent): Furniture {
                self::holdType($piece);
                $piece->save();
                $this->gallery->arrange($piece->images(), $gallery, $stored);
                $this->audit($by, AuditEvent::Created, $piece, null, self::snapshot($piece), $ipAddress, $userAgent);

                return $piece;
            });
        });
    }

    /**
     * Change a piece. A thumbnail sent replaces the current one; none sent
     * keeps it. The gallery becomes `$gallery`, in its order.
     *
     * @param  array{brand_id: int, furniture_type_id: int, name: string, slug: string, description: string|null, table_shape_option_id: int|null, table_base_design_option_id: int|null, is_active: bool}  $data
     * @param  list<array{id: int|null, file: UploadedFile|null}>  $gallery
     */
    public function update(Furniture $piece, array $data, ?UploadedFile $thumbnail, array $gallery, User $by, ?string $ipAddress, ?string $userAgent): Furniture
    {
        $before = self::snapshot($piece);
        $oldThumbnail = $piece->thumbnail_path;

        $removed = $this->images->writing(function (WrittenFiles $written) use ($piece, $data, $thumbnail, $gallery, $before, $by, $ipAddress, $userAgent): array {
            $piece->fill($data);

            if ($data['brand_id'] !== $piece->getOriginal('brand_id')) {
                $piece->forceFill(['sort_order' => self::nextPlace($data['brand_id'])]);
            }

            if ($thumbnail !== null) {
                $piece->forceFill(['thumbnail_path' => $this->images->store($thumbnail, self::DIRECTORY, $written)]);
            }

            $stored = $this->gallery->store($gallery, self::DIRECTORY, $written);

            return DB::transaction(function () use ($piece, $gallery, $stored, $before, $by, $ipAddress, $userAgent): array {
                self::holdType($piece);
                $piece->save();
                $removed = $this->gallery->arrange($piece->images(), $gallery, $stored);
                $this->audit($by, AuditEvent::Updated, $piece, $before, self::snapshot($piece), $ipAddress, $userAgent);

                return $removed;
            });
        });

        $gone = [...$removed, ...($thumbnail !== null ? [$oldThumbnail] : [])];
        $this->images->delete($gone, CatalogImages::inUse($gone));
        $piece->unsetRelation('images');

        return $piece;
    }

    /** Turn a piece on (shown on Regray) or off (hidden, kept here). */
    public function setActive(Furniture $piece, bool $active, User $by, ?string $ipAddress, ?string $userAgent): Furniture
    {
        if ($piece->is_active === $active) {
            return $piece;
        }

        DB::transaction(function () use ($piece, $active, $by, $ipAddress, $userAgent): void {
            $before = self::snapshot($piece);
            $piece->forceFill(['is_active' => $active])->save();
            $this->audit($by, AuditEvent::StatusChanged, $piece, $before, self::snapshot($piece), $ipAddress, $userAgent);
        });

        return $piece;
    }

    /** Archive a piece: it leaves the Products page and its page goes offline. Its rows and files stay. */
    public function archive(Furniture $piece, User $by, ?string $ipAddress, ?string $userAgent): Furniture
    {
        DB::transaction(function () use ($piece, $by, $ipAddress, $userAgent): void {
            $before = self::snapshot($piece);
            $piece->delete();
            $this->audit($by, AuditEvent::Archived, $piece, $before, self::snapshot($piece), $ipAddress, $userAgent);
        });

        return $piece;
    }

    /** Hold its furniture type, so it can't be archived under the save (CatalogLocks). */
    private static function holdType(Furniture $piece): void
    {
        CatalogLocks::hold('furniture_types', [$piece->furniture_type_id], 'furniture_type_id', __('That furniture type is no longer offered. Choose another.'));
    }

    /** After the brand's last piece. */
    private static function nextPlace(int $brandId): int
    {
        return (int) Furniture::query()->withTrashed()->where('brand_id', $brandId)->max('sort_order') + 1;
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    private function audit(User $by, AuditEvent $event, Furniture $piece, ?array $before, ?array $after, ?string $ipAddress, ?string $userAgent): void
    {
        $this->auditLogs->record($by->id, $event, $piece, $before, $after, $ipAddress, $userAgent);
    }

    /**
     * What an audit row records of a piece.
     *
     * @return array<string, mixed>
     */
    private static function snapshot(Furniture $piece): array
    {
        return [
            'brand_id' => $piece->brand_id,
            'furniture_type_id' => $piece->furniture_type_id,
            'name' => $piece->name,
            'slug' => $piece->slug,
            'description' => $piece->description,
            'table_shape_option_id' => $piece->table_shape_option_id,
            'table_base_design_option_id' => $piece->table_base_design_option_id,
            'thumbnail_path' => $piece->thumbnail_path,
            'gallery' => $piece->images()->pluck('path')->all(),
            'is_active' => $piece->is_active,
            'archived_at' => $piece->deleted_at?->toIso8601String(),
        ];
    }
}
