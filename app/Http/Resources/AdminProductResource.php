<?php

namespace App\Http\Resources;

use App\Enums\Permission;
use App\Models\Furniture;
use App\Models\FurnitureImage;
use App\Models\Stone;
use App\Models\StoneImage;
use App\Models\StoneType;
use App\Models\StoneVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * A row on the admin's Products page, a stone or a piece of furniture, and
 * what its Edit form reads.
 *
 * `key` is unique across both kinds (`stone-12`, `furniture-3`), as their ids
 * are not. `type` is the stone's type or the piece's furniture type. A
 * stone's `sizes` are its variants in their order, a size and a thickness
 * each (a `sizeId` of null is "no set size"); its
 * `types` are the other types it is listed under. `gallery` is the photos
 * besides the thumbnail. `actions` is what this reader may do, from the
 * permission each route checks; `publicUrl` is its page while it is shown.
 *
 * Expects what ProductAdminRepositoryInterface::paginate() loads.
 *
 * @property-read Stone|Furniture $resource
 */
class AdminProductResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $product = $this->resource;
        $gate = Gate::forUser($request->user());
        $isStone = $product instanceof Stone;

        return [
            'key' => ($isStone ? 'stone-' : 'furniture-').$product->id,
            'kind' => $isStone ? 'stone' : 'furniture',
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'thumbnail' => $product->thumbnail_path === null ? null : ShowcaseCardResource::resolveThumbnailUrl($product->thumbnail_path),
            'brand' => ['id' => $product->brand->id, 'slug' => $product->brand->slug, 'name' => $product->brand->name],
            'type' => $isStone
                ? ['id' => $product->type->id, 'name' => $product->type->name]
                : ['id' => $product->furnitureType->id, 'name' => $product->furnitureType->name],
            'isActive' => $product->is_active,
            'gallery' => array_values($product->images->map(fn (StoneImage|FurnitureImage $image): array => [
                'id' => $image->id,
                'url' => ShowcaseCardResource::resolveThumbnailUrl($image->path),
            ])->all()),
            ...($isStone ? self::stone($product) : self::furniture($product)),
            'updatedAt' => $product->updated_at->toIso8601String(),
            'publicUrl' => $product->is_active
                ? ($isStone
                    ? route('brands.stones.show', ['brand' => $product->brand->slug, 'stone' => $product->slug])
                    : route('brands.furniture.show', ['brand' => $product->brand->slug, 'furniture' => $product->slug]))
                : null,
            'actions' => array_values(array_filter([
                $product->is_active ? 'view' : null,
                $gate->allows(Permission::ProductsUpdate->value) ? 'edit' : null,
                $gate->allows(Permission::ProductsArchive->value) ? 'archive' : null,
            ])),
            'canToggle' => $gate->allows(Permission::ProductsUpdate->value),
        ];
    }

    /** @return array{origin: string, sizeNote: string|null, types: list<array{id: int, name: string}>, sizes: list<array{sizeId: int|null, thicknessId: int}>, description: null} */
    private static function stone(Stone $stone): array
    {
        $sizes = $stone->variants->map(fn (StoneVariant $variant): array => ['sizeId' => $variant->size_id, 'thicknessId' => $variant->thickness_id]);

        return [
            'origin' => $stone->origin,
            'sizeNote' => $stone->size_note,
            'types' => array_values($stone->types
                ->reject(fn (StoneType $type): bool => $type->id === $stone->stone_type_id)
                ->map(fn (StoneType $type): array => ['id' => $type->id, 'name' => $type->name])->all()),
            'sizes' => array_values($sizes->all()),
            'description' => null,
        ];
    }

    /** @return array{origin: null, sizeNote: null, types: list<never>, sizes: list<never>, description: string|null} */
    private static function furniture(Furniture $piece): array
    {
        return [
            'origin' => null,
            'sizeNote' => null,
            'types' => [],
            'sizes' => [],
            'description' => $piece->description,
        ];
    }
}
