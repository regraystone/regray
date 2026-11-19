<?php

namespace App\Models\Ph;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A province, by its PSGC code, under its region. Reference data, read only.
 *
 * @property string $code
 * @property string $name
 * @property string $search_name
 * @property string $region_code
 * @property-read PhRegion $region
 */
class PhProvince extends Model
{
    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    /** @return BelongsTo<PhRegion, $this> */
    public function region(): BelongsTo
    {
        return $this->belongsTo(PhRegion::class, 'region_code', 'code');
    }
}
