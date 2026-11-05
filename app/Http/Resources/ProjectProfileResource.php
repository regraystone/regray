<?php

namespace App\Http\Resources;

use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\ProjectImage;
use App\Models\Stone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Project */
class ProjectProfileResource extends JsonResource
{
    /**
     * Only the profile's public fields leave the server, never model or pivot metadata.
     *
     * @return array{slug: string, name: string, completedYear: int, location: string, overview: string, thumbnail: string, categories: array<int, array{slug: string, name: string}>, stones: array<int, array{slug: string, name: string, thumbnail: string, category: array{slug: string, name: string}}>, gallery: array<int, array{src: string}>}
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'completedYear' => $this->completed_year,
            'location' => $this->location,
            'overview' => $this->overview,
            'thumbnail' => ProjectCardResource::resolveThumbnailUrl($this->thumbnail_path),
            'categories' => $this->categories->map(fn (ProjectCategory $category): array => [
                'slug' => $category->slug,
                'name' => $category->name,
            ])->all(),
            'stones' => $this->stones->map(fn (Stone $stone): array => [
                'slug' => $stone->slug,
                'name' => $stone->name,
                'thumbnail' => ProjectCardResource::resolveThumbnailUrl($stone->thumbnail_path),
                'category' => ['slug' => $stone->category->slug, 'name' => $stone->category->name],
            ])->all(),
            'gallery' => $this->images->map(fn (ProjectImage $image): array => [
                'src' => ProjectCardResource::resolveThumbnailUrl($image->path),
            ])->all(),
        ];
    }
}
