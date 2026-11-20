<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One fact on a brand's page, such as "Made in" / "Treia, Italy", set on the
 * admin's Brands page. Up to 6 a brand, in `sort_order` from 0.
 *
 * @property int $id
 * @property int $brand_id
 * @property string $label
 * @property string $value
 * @property int $sort_order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['label', 'value', 'sort_order'])]
class BrandFact extends Model
{
    /** @return BelongsTo<Brand, $this> */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}
