<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $inquiry_id
 * @property int $user_id
 * @property int|null $assigned_by
 * @property Carbon $assigned_at
 */
#[Fillable([])]
class InquiryHandler extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'inquiry_id';

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
