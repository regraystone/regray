<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One thing a brand makes, such as "Kitchens", shown as a tag on its page and
 * set on the admin's Brands page. Up to 8 a brand, no two alike whatever their
 * case, in `sort_order` from 0.
 *
 * @property int $id
 * @property int $brand_id
 * @property string $name
 * @property int $sort_order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['name', 'sort_order'])]
class BrandMake extends Model
{
    /** @return BelongsTo<Brand, $this> */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}
