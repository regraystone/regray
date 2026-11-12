<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * One question a service asks: where it comes in the service's order, and
 * whether it has to be answered.
 *
 * @property int $service_id
 * @property int $detail_field_id
 * @property int $sort_order
 * @property bool $is_required
 */
class ServiceDetailField extends Pivot
{
    protected $table = 'service_detail_fields';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_required' => 'boolean',
        ];
    }
}
