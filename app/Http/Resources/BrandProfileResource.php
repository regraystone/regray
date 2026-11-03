<?php

namespace App\Http\Resources;

use App\Models\Brand;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Brand */
class BrandProfileResource extends JsonResource
{
    /**
     * @return array{slug: string, name: string, tagline: string|null, description: string|null, websiteUrl: string|null, logo: string, thumbnail: string|null, projects: array<int, array{slug: string, name: string, thumbnail: string}>}
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'tagline' => $this->tagline,
            'description' => $this->description,
            'websiteUrl' => $this->website_url,
            'logo' => ProjectCardResource::resolveThumbnailUrl($this->logo_path),
            'thumbnail' => $this->thumbnail_path === null ? null : ProjectCardResource::resolveThumbnailUrl($this->thumbnail_path),
            'projects' => $this->projects()
                ->where('is_published', true)
                ->get()
                ->map(fn (Project $project): array => [
                    'slug' => $project->slug,
                    'name' => $project->name,
                    'thumbnail' => ProjectCardResource::resolveThumbnailUrl($project->thumbnail_path),
                ])->all(),
        ];
    }
}
