<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $inquiry_id
 * @property string $kind `payment` or `refund`
 * @property string $amount
 * @property string|null $reference
 * @property int|null $recorded_by
 * @property Carbon $received_at
 * @property Carbon $created_at
 *
 * Accounting records each one and uploads its receipt. The inquiry's
 * `payment_status` is set by hand, not derived from these rows.
 */
#[Fillable(['inquiry_id', 'kind', 'amount', 'reference', 'recorded_by', 'received_at', 'created_at'])]
class PaymentTransaction extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'received_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Inquiry, $this> */
    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
