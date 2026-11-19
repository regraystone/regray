<?php

namespace App\Actions\Saved;

use App\Models\Brand;
use App\Models\Furniture;
use App\Models\Stone;
use Illuminate\Database\Eloquent\Collection;

/**
 * What the Saved page's "Explore by category" cards say about the catalog:
 * how many stones and pieces of furniture Regray Stone carries, and the
 * brands behind Kitchens and Wardrobes (their names and photos). Counted
 * from the active catalog, so the cards never promise more than is there.
 */
class ListSavedCategoriesAction
{
    /** The brand whose stones and furniture Natural Stone and Furniture open. */
    public const HOME_BRAND = 'regray-stone';

    /** The brands Kitchens and Wardrobes open, by slug. */
    public const CATEGORY_BRANDS = ['cucine', 'faer'];

    /**
     * @return array{stones: int, furniture: int, brands: Collection<int, Brand>}
     */
    public function execute(): array
    {
        return [
            'stones' => Stone::query()->active()
                ->whereHas('brand', fn ($brand) => $brand->active()->where('slug', self::HOME_BRAND))
                ->count(),
            'furniture' => Furniture::query()->active()
                ->whereHas('brand', fn ($brand) => $brand->active()->where('slug', self::HOME_BRAND))
                ->count(),
            'brands' => Brand::query()->active()
                ->whereIn('slug', self::CATEGORY_BRANDS)
                ->get(['id', 'slug', 'name', 'tagline', 'logo_path', 'thumbnail_path']),
        ];
    }
}
