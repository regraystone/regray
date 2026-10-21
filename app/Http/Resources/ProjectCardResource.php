<?php

namespace App\Http\Resources;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin Project */
class ProjectCardResource extends JsonResource
{
    /**
     * @return array{slug: string, name: string, thumbnail: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'thumbnail' => self::resolveThumbnailUrl($this->thumbnail_path),
        ];
    }

    public static function resolveThumbnailUrl(string $path): string
    {
        // Strip legacy /storage/ prefix if present, leaving only the relative path
        $relativePath = $path;
        if (str_starts_with($path, '/storage/')) {
            $relativePath = substr($path, 9);
        } elseif (str_starts_with($path, '/')) {
            // Other /paths (e.g., /images/...) should be returned unchanged
            return $path;
        }

        $disk = config('filesystems.public_disk');

        if ($disk === 'public') {
            return '/storage/'.$relativePath;
        }

        return Storage::disk($disk)->url($relativePath);
    }
}
