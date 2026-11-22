<?php

namespace App\Services\Product;

use App\Data\WrittenFiles;
use App\Enums\AuditEvent;
use App\Models\Stone;
use App\Models\User;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use App\Support\CatalogImages;
use App\Support\PublicImageStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * The Products page's stone writes: adding a stone, changing one (its
 * thumbnail, gallery and sizes included), turning it on or off and archiving
 * it. The public pages read stones through StoneService; this is the admin's
 * side.
 *
 * Images go on the public disk under stones/, as the seeded ones are, named
 * by PublicImageStore. A replaced thumbnail's file and a photo taken off the
 * gallery have their files deleted once the save has committed, unless some
 * row of the catalog still uses it (CatalogImages): a seeded stone's photo
 * can also be its type's.
 *
 * The stone is listed under its own type and any others chosen
 * (`stone_stone_type`, which the brand page's filters read). Its sizes are
 * its variants, a size and a thickness each, written in the form's order, so
 * the page lists them as the admin did.
 *
 * Archiving is the soft delete: the stone leaves the Products page and its
 * page goes offline; past inquiries keep it, as they read it with its trashed.
 */
class StoneAdminService
{
    private const DIRECTORY = 'stones';

    public function __construct(
        private readonly AuditLogRepositoryInterface $auditLogs,
        private readonly PublicImageStore $images,
        private readonly ProductGallery $gallery,
    ) {}

    /**
     * @param  array{brand_id: int, stone_type_id: int, name: string, slug: string, origin: string, size_note: string|null, is_active: bool, types: list<int>, variants: list<array{size_id: int|null, thickness_id: int}>}  $data
     * @param  list<array{id: int|null, file: UploadedFile|null}>  $gallery
     */
    public function create(array $data, UploadedFile $thumbnail, array $gallery, User $by, ?string $ipAddress, ?string $userAgent): Stone
    {
        return $this->images->writing(function (WrittenFiles $written) use ($data, $thumbnail, $gallery, $by, $ipAddress, $userAgent): Stone {
            $stone = new Stone(self::columns($data));
            $stone->forceFill([
                'thumbnail_path' => $this->images->store($thumbnail, self::DIRECTORY, $written),
                'sort_order' => self::nextPlace($data['brand_id']),
            ]);
            $stored = $this->gallery->store($gallery, self::DIRECTORY, $written);

            return DB::transaction(function () use ($stone, $data, $gallery, $stored, $by, $ipAddress, $userAgent): Stone {
                $stone->save();
                $this->writeRelations($stone, $data);
                $this->gallery->arrange($stone->images(), $gallery, $stored);
                $this->audit($by, AuditEvent::Created, $stone, null, self::snapshot($stone), $ipAddress, $userAgent);

                return $stone;
            });
        });
    }

    /**
     * Change a stone. A thumbnail sent replaces the current one; none sent
     * keeps it. The gallery becomes `$gallery`, in its order.
     *
     * @param  array{brand_id: int, stone_type_id: int, name: string, slug: string, origin: string, size_note: string|null, is_active: bool, types: list<int>, variants: list<array{size_id: int|null, thickness_id: int}>}  $data
     * @param  list<array{id: int|null, file: UploadedFile|null}>  $gallery
     */
    public function update(Stone $stone, array $data, ?UploadedFile $thumbnail, array $gallery, User $by, ?string $ipAddress, ?string $userAgent): Stone
    {
        $before = self::snapshot($stone);
        $oldThumbnail = $stone->thumbnail_path;

        $removed = $this->images->writing(function (WrittenFiles $written) use ($stone, $data, $thumbnail, $gallery, $before, $by, $ipAddress, $userAgent): array {
            $stone->fill(self::columns($data));

            if ($data['brand_id'] !== $stone->getOriginal('brand_id')) {
                $stone->forceFill(['sort_order' => self::nextPlace($data['brand_id'])]);
            }

            if ($thumbnail !== null) {
                $stone->forceFill(['thumbnail_path' => $this->images->store($thumbnail, self::DIRECTORY, $written)]);
            }

            $stored = $this->gallery->store($gallery, self::DIRECTORY, $written);

            return DB::transaction(function () use ($stone, $data, $gallery, $stored, $before, $by, $ipAddress, $userAgent): array {
                $stone->save();
                $this->writeRelations($stone, $data);
                $removed = $this->gallery->arrange($stone->images(), $gallery, $stored);
                $this->audit($by, AuditEvent::Updated, $stone, $before, self::snapshot($stone), $ipAddress, $userAgent);

                return $removed;
            });
        });

        // Now the save has committed: the replaced thumbnail and the photos taken off.
        $gone = [...$removed, ...($thumbnail !== null ? [$oldThumbnail] : [])];
        $this->images->delete($gone, CatalogImages::inUse($gone));
        $stone->unsetRelation('images');

        return $stone;
    }

    /** Turn a stone on (shown on Regray) or off (hidden, kept here). */
    public function setActive(Stone $stone, bool $active, User $by, ?string $ipAddress, ?string $userAgent): Stone
    {
        if ($stone->is_active === $active) {
            return $stone;
        }

        DB::transaction(function () use ($stone, $active, $by, $ipAddress, $userAgent): void {
            $before = self::snapshot($stone);
            $stone->forceFill(['is_active' => $active])->save();
            $this->audit($by, AuditEvent::StatusChanged, $stone, $before, self::snapshot($stone), $ipAddress, $userAgent);
        });

        return $stone;
    }

    /** Archive a stone: it leaves the Products page and its page goes offline. Its rows and files stay. */
    public function archive(Stone $stone, User $by, ?string $ipAddress, ?string $userAgent): Stone
    {
        DB::transaction(function () use ($stone, $by, $ipAddress, $userAgent): void {
            $before = self::snapshot($stone);
            $stone->delete();
            $this->audit($by, AuditEvent::Archived, $stone, $before, self::snapshot($stone), $ipAddress, $userAgent);
        });

        return $stone;
    }

    /**
     * Its types (its own first, then the others chosen) and its variants, in
     * the form's order.
     *
     * @param  array{stone_type_id: int, types: list<int>, variants: list<array{size_id: int|null, thickness_id: int}>}  $data
     */
    private function writeRelations(Stone $stone, array $data): void
    {
        $stone->types()->sync(array_values(array_unique([$data['stone_type_id'], ...$data['types']])));
        $stone->variants()->delete();
        $order = 0;

        foreach ($data['variants'] as $row) {
            $stone->variants()->create(['size_id' => $row['size_id'], 'thickness_id' => $row['thickness_id'], 'sort_order' => $order++]);
        }

        $stone->unsetRelation('types')->unsetRelation('variants');
    }

    /** After the brand's last stone. */
    private static function nextPlace(int $brandId): int
    {
        return (int) Stone::query()->withTrashed()->where('brand_id', $brandId)->max('sort_order') + 1;
    }

    /**
     * The stone's own columns from the form's data.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function columns(array $data): array
    {
        return array_diff_key($data, array_flip(['types', 'variants']));
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    private function audit(User $by, AuditEvent $event, Stone $stone, ?array $before, ?array $after, ?string $ipAddress, ?string $userAgent): void
    {
        $this->auditLogs->record($by->id, $event, $stone, $before, $after, $ipAddress, $userAgent);
    }

    /**
     * What an audit row records of a stone.
     *
     * @return array<string, mixed>
     */
    private static function snapshot(Stone $stone): array
    {
        return [
            'brand_id' => $stone->brand_id,
            'stone_type_id' => $stone->stone_type_id,
            'name' => $stone->name,
            'slug' => $stone->slug,
            'origin' => $stone->origin,
            'size_note' => $stone->size_note,
            'thumbnail_path' => $stone->thumbnail_path,
            'gallery' => $stone->images()->orderBy('sort_order')->pluck('path')->all(),
            'types' => $stone->types()->orderBy('stone_types.id')->pluck('stone_types.id')->all(),
            'variants' => $stone->variants()->get(['size_id', 'thickness_id'])
                ->map(fn ($variant): string => ($variant->size_id ?? 'none').'x'.$variant->thickness_id)->all(),
            'is_active' => $stone->is_active,
            'archived_at' => $stone->deleted_at?->toIso8601String(),
        ];
    }
}
