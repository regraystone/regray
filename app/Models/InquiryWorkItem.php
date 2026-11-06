<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * What an inquiry wants of one stone: the service and, where that service asks for
 * one, the furniture type.
 *
 * @property int $id
 * @property int $inquiry_id
 * @property int|null $stone_id Null when Regray is to recommend the stone.
 * @property int $service_id
 * @property int|null $furniture_type_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['stone_id', 'service_id', 'furniture_type_id'])]
class InquiryWorkItem extends Model
{
    /** @return BelongsTo<Inquiry, $this> */
    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class);
    }

    /** @return BelongsTo<Stone, $this> */
    public function stone(): BelongsTo
    {
        return $this->belongsTo(Stone::class);
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** @return BelongsTo<FurnitureType, $this> */
    public function furnitureType(): BelongsTo
    {
        return $this->belongsTo(FurnitureType::class);
    }
}
