<?php

namespace App\Http\Resources;

use App\Models\Application;
use App\Models\CareTip;
use App\Models\Finish;
use App\Models\Stone;
use App\Models\StoneImage;
use App\Models\StoneVariant;
use App\Models\Thickness;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Stone */
class StoneProfileResource extends JsonResource
{
    /**
     * Expose the profile rather than model or pivot metadata. Shared facts belong to its type.
     * `slabs` are the stone's variants grouped by size, in variant order, each size with the
     * thicknesses it is cut in, thinnest first, all as labels; a size of null is the stone's
     * pieces without set sizes, which `sizeNote` describes.
     *
     * @return array{slug: string, name: string, thumbnail: string, bestSeller: bool, brand: array{slug: string, name: string}, type: array{slug: string, name: string}, description: string, specifications: array{origin: string, slabs: list<array{size: string|null, thicknesses: list<string>}>, sizeNote: string|null}, finishes: array<int, string>, applications: array<int, string>, care: array<int, string>, gallery: array<int, array{src: string, name: string}>}
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'thumbnail' => ProjectCardResource::resolveThumbnailUrl($this->thumbnail_path),
            'bestSeller' => $this->is_best_seller,
            'brand' => ['slug' => $this->brand->slug, 'name' => $this->brand->name],
            'type' => ['slug' => $this->type->slug, 'name' => $this->type->name],
            'description' => $this->type->description,
            'specifications' => [
                'origin' => $this->origin,
                'slabs' => $this->slabs(),
                'sizeNote' => $this->size_note,
            ],
            'finishes' => $this->type->finishes->map(fn (Finish $finish): string => $finish->name)->all(),
            'applications' => $this->type->applications->map(fn (Application $application): string => $application->name)->all(),
            'care' => $this->type->careTips->map(fn (CareTip $careTip): string => $careTip->tip)->all(),
            'gallery' => $this->images->map(fn (StoneImage $image): array => [
                'src' => ProjectCardResource::resolveThumbnailUrl($image->path),
                'name' => $image->alt_text ?? $this->name,
            ])->all(),
        ];
    }

    /**
     * The variants grouped by size, in the order their first variant comes.
     *
     * @return list<array{size: string|null, thicknesses: list<string>}>
     */
    private function slabs(): array
    {
        $groups = [];

        /** @var StoneVariant $variant */
        foreach ($this->variants as $variant) {
            $key = $variant->size_id ?? 'none';
            $groups[$key] ??= ['size' => $variant->size?->label, 'thicknesses' => []];
            $groups[$key]['thicknesses'][] = $variant->thickness;
        }

        return array_values(array_map(function (array $group): array {
            usort($group['thicknesses'], fn (Thickness $left, Thickness $right): int => [$left->sort_order, (float) $left->value] <=> [$right->sort_order, (float) $right->value]);

            return [
                'size' => $group['size'],
                'thicknesses' => array_map(fn (Thickness $thickness): string => $thickness->label, $group['thicknesses']),
            ];
        }, $groups));
    }
}
