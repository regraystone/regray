<?php

namespace App\Models;

use App\Enums\JobOrderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $reference
 * @property int $inquiry_project_id
 * @property int $quotation_id
 * @property JobOrderStatus $status
 * @property Carbon $status_changed_at
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['reference', 'inquiry_project_id', 'quotation_id', 'status', 'status_changed_at', 'notes', 'created_at', 'updated_at'])]
class JobOrder extends Model
{
    protected function casts(): array
    {
        return [
            'status' => JobOrderStatus::class,
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
}
