<?php

namespace App\Models;

use App\Enums\SocialPlatform;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One of a brand's social links, shown on its page with its network's icon
 * and set on the admin's Brands page. One per network, in `sort_order` from 0.
 *
 * @property int $id
 * @property int $brand_id
 * @property SocialPlatform $platform
 * @property string $url
 * @property int $sort_order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['platform', 'url', 'sort_order'])]
class BrandSocial extends Model
{
    protected function casts(): array
    {
        return [
            'platform' => SocialPlatform::class,
        ];
    }

    /** @return BelongsTo<Brand, $this> */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}
