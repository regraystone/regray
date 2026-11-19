<?php

namespace App\Models\Ph;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A city or municipality, by its PSGC code. `province_code` is null where it
 * is in no province: every city of NCR, and the independent Isabela City and
 * Cotabato City. Reference data, read only.
 *
 * @property string $code
 * @property string $name
 * @property string $search_name
 * @property string $region_code
 * @property string|null $province_code
 * @property bool $is_city
 * @property-read PhRegion $region
 * @property-read PhProvince|null $province
 */
class PhCity extends Model
{
    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_city' => 'boolean'];
    }

    /** @return BelongsTo<PhRegion, $this> */
    public function region(): BelongsTo
    {
        return $this->belongsTo(PhRegion::class, 'region_code', 'code');
    }

    /** @return BelongsTo<PhProvince, $this> */
    public function province(): BelongsTo
    {
        return $this->belongsTo(PhProvince::class, 'province_code', 'code');
    }

    /** @return HasMany<PhPostalCode, $this> */
    public function postalCodes(): HasMany
    {
        return $this->hasMany(PhPostalCode::class, 'city_code', 'code');
    }
}
