<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $inquiry_project_id
 * @property int $quotation_id
 * @property string $amount_due
 * @property string $amount_paid
 * @property PaymentStatus $status
 * @property Carbon $status_changed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * `amount_paid` is the sum of the `payment` rows in `transactions`.
 */
#[Fillable(['inquiry_project_id', 'quotation_id', 'amount_due', 'amount_paid', 'status', 'status_changed_at', 'created_at', 'updated_at'])]
class Payment extends Model
{
    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount_due' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'status_changed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<InquiryProject, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(InquiryProject::class, 'inquiry_project_id');
    }

    /** @return BelongsTo<Quotation, $this> */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /** @return HasMany<PaymentTransaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class)->orderBy('received_at');
    }
}
