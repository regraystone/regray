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

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Inquiry, $this> */
    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
