<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * What a client actually chose when they sent one inquiry, copied at submission
 * so later Profile edits cannot rewrite the request. One row per inquiry.
 *
 * @property int $inquiry_id
 * @property string $delivery_method
 * @property string|null $location
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['delivery_method', 'location'])]
class ClientInformationSnapshot extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'inquiry_id';

    /** @return BelongsTo<Inquiry, $this> */
    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class);
    }
}
