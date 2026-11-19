<?php

namespace App\Models\Ph;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A barangay, by its PSGC code, under its city or municipality. Reference
 * data, read only, and only ever read for one city at a time.
 *
 * @property string $code
 * @property string $name
 * @property string $search_name
 * @property string $city_code
 * @property-read PhCity $city
 */
class PhBarangay extends Model
{
    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    /** @return BelongsTo<PhCity, $this> */
    public function city(): BelongsTo
    {
        return $this->belongsTo(PhCity::class, 'city_code', 'code');
    }
}
