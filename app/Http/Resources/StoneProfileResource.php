<?php

namespace App\Http\Resources;

use App\Models\Application;
use App\Models\CareTip;
use App\Models\Finish;
use App\Models\Stone;
use App\Models\StoneImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Stone */
class StoneProfileResource extends JsonResource
{
    /**
     * Expose the profile rather than model or pivot metadata. Shared facts belong to its category;
     * thickness stays a number so the page, not the database, owns the unit wording.
     *
     * @return array{slug: string, name: string, thumbnail: string, bestSeller: bool, brand: array{slug: string, name: string}, category: array{slug: string, name: string}, description: string, specifications: array{stoneType: string, origin: string, size: string, thicknessMm: int}, finishes: array<int, string>, applications: array<int, string>, care: array<int, string>, gallery: array<int, array{src: string, name: string}>}
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'thumbnail' => ProjectCardResource::resolveThumbnailUrl($this->thumbnail_path),
            'bestSeller' => $this->is_best_seller,
            'brand' => ['slug' => $this->brand->slug, 'name' => $this->brand->name],
            'category' => ['slug' => $this->category->slug, 'name' => $this->category->name],
            'description' => $this->category->description,
            'specifications' => [
                'stoneType' => $this->category->name,
                'origin' => $this->origin,
                'size' => $this->size,
                'thicknessMm' => $this->thickness_mm,
            ],
            'finishes' => $this->category->finishes->map(fn (Finish $finish): string => $finish->name)->all(),
            'applications' => $this->category->applications->map(fn (Application $application): string => $application->name)->all(),
            'care' => $this->category->careTips->map(fn (CareTip $careTip): string => $careTip->tip)->all(),
            'gallery' => $this->images->map(fn (StoneImage $image): array => [
                'src' => ProjectCardResource::resolveThumbnailUrl($image->path),
                'name' => $image->alt_text ?? $this->name,
            ])->all(),
        ];
    }
}
