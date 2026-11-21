<?php

namespace App\Http\Resources;

use App\Enums\Permission;
use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * A brand as a card on the admin's Brands page, and what its View drawer and
 * Edit form read.
 *
 * `actions` is what this reader may do to this brand, from the permission
 * each route checks, so the page never decides by role. `logoFile` and
 * `thumbnailFile` are the uploaded images' own names and sizes, to show
 * people; null for an image that was committed rather than uploaded.
 * `publicUrl` is the brand's public page, or null while it is hidden.
 *
 * Expects the counts and Brand::DETAILS loaded
 * (BrandRepositoryInterface::paginateForAdmin()).
 *
 * @mixin Brand
 */
class AdminBrandResource extends JsonResource
{
    /**
     * @return array{id: int, slug: string, name: string, tagline: string|null, description: string|null, websiteUrl: string|null, logo: string, thumbnail: string|null, isActive: bool, sortOrder: int, stonesCount: int, furnitureCount: int, projectsCount: int, facts: list<array{label: string, value: string}>, makes: list<string>, socials: list<array{platform: string, url: string}>, logoFile: array{originalName: string, sizeBytes: int|null}|null, thumbnailFile: array{originalName: string, sizeBytes: int|null}|null, createdAt: string, updatedAt: string, publicUrl: string|null, actions: list<string>}
     */
    public function toArray(Request $request): array
    {
        $gate = Gate::forUser($request->user());

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'tagline' => $this->tagline,
            'description' => $this->description,
            'websiteUrl' => $this->website_url,
            'logo' => ProjectCardResource::resolveThumbnailUrl($this->logo_path),
            'thumbnail' => $this->thumbnail_path === null ? null : ProjectCardResource::resolveThumbnailUrl($this->thumbnail_path),
            'isActive' => $this->is_active,
            'sortOrder' => $this->sort_order,
            'stonesCount' => (int) ($this->stones_count ?? 0),
            'furnitureCount' => (int) ($this->furniture_count ?? 0),
            'projectsCount' => (int) ($this->projects_count ?? 0),
            ...$this->details(),
            'logoFile' => self::file($this->logo_original_name, $this->logo_size_bytes),
            'thumbnailFile' => $this->thumbnail_path === null ? null : self::file($this->thumbnail_original_name, $this->thumbnail_size_bytes),
            'createdAt' => $this->created_at->toIso8601String(),
            'updatedAt' => $this->updated_at->toIso8601String(),
            'publicUrl' => $this->is_active ? route('brands.show', $this->slug) : null,
            'actions' => array_values(array_filter([
                'view',
                $gate->allows(Permission::BrandsUpdate->value) ? 'edit' : null,
                $gate->allows(Permission::BrandsArchive->value) ? 'archive' : null,
            ])),
        ];
    }

    /** @return array{originalName: string, sizeBytes: int|null}|null */
    private static function file(?string $originalName, ?int $sizeBytes): ?array
    {
        return $originalName === null ? null : ['originalName' => $originalName, 'sizeBytes' => $sizeBytes];
    }
}
