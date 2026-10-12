<?php

namespace App\Http\Resources;

use App\Models\Application;
use App\Models\CareTip;
use App\Models\Finish;
use App\Models\Project;
use App\Models\Stone;
use App\Models\StoneImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Stone */
class StoneProfileResource extends JsonResource
{
    /**
     * Expose the profile rather than model or pivot metadata. The name is also its stone type;
     * thickness stays a number so the page, not the database, owns the unit wording.
     *
     * @return array{slug: string, name: string, thumbnail: string, recommended: bool, description: string, specifications: array{stoneType: string, origin: string, thicknessMm: int}, finishes: array<int, string>, applications: array<int, string>, care: array<int, string>, gallery: array<int, array{src: string, name: string}>, projects: array<int, array{slug: string, name: string, thumbnail: string}>}
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'thumbnail' => ProjectCardResource::resolveThumbnailUrl($this->thumbnail_path),
            'recommended' => $this->recommended,
            'description' => $this->description,
            'specifications' => [
                'stoneType' => $this->name,
                'origin' => $this->origin,
                'thicknessMm' => $this->thickness_mm,
            ],
            'finishes' => $this->finishes->map(fn (Finish $finish): string => $finish->name)->all(),
            'applications' => $this->applications->map(fn (Application $application): string => $application->name)->all(),
            'care' => $this->careTips->map(fn (CareTip $careTip): string => $careTip->tip)->all(),
            'gallery' => $this->images->map(fn (StoneImage $image): array => [
                'src' => ProjectCardResource::resolveThumbnailUrl($image->path),
                'name' => $image->name,
            ])->all(),
            'projects' => $this->projects->map(fn (Project $project): array => [
                'slug' => $project->slug,
                'name' => $project->name,
                'thumbnail' => ProjectCardResource::resolveThumbnailUrl($project->thumbnail_path),
            ])->all(),
        ];
    }
}
