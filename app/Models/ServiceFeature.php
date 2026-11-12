<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of what a service includes, shown under it on the Inquire page, in
 * `sort_order`. Keyed by its service and position: ServiceSeeder replaces a
 * service's lines whole.
 *
 * @property int $service_id
 * @property int $sort_order
 * @property string $text
 */
#[Fillable(['service_id', 'sort_order', 'text'])]
class ServiceFeature extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
