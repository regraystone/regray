<?php

namespace App\Models\Ph;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A region of the Philippines, by its PSGC code. Reference data, read only.
 *
 * @property string $code
 * @property string $name
 * @property string $designation
 * @property string $search_name
 */
class PhRegion extends Model
{
    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    /** @return HasMany<PhProvince, $this> */
    public function provinces(): HasMany
    {
        return $this->hasMany(PhProvince::class, 'region_code', 'code');
    }

    /** @return HasMany<PhCity, $this> */
    public function cities(): HasMany
    {
        return $this->hasMany(PhCity::class, 'region_code', 'code');
    }
}
