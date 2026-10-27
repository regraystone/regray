<?php

namespace App\Models;

use App\Enums\QuotationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $inquiry_project_id
 * @property int $revision_no
 * @property QuotationStatus $status
 * @property string $total_amount
 * @property Carbon $issued_at
 * @property Carbon|null $responded_at
 * @property int|null $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['inquiry_project_id', 'revision_no', 'status', 'total_amount', 'issued_at', 'responded_at', 'created_by', 'created_at', 'updated_at'])]
class Quotation extends Model
{
    protected function casts(): array
    {
        return [
            'status' => QuotationStatus::class,
            'total_amount' => 'decimal:2',
            'issued_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<InquiryProject, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(InquiryProject::class, 'inquiry_project_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<JobOrder, $this> */
    public function jobOrders(): HasMany
    {
        return $this->hasMany(JobOrder::class);
    }

    /** @return HasOne<Payment, $this> */
    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }
}
