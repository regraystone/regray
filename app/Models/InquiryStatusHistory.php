<?php

namespace App\Models;

use App\Enums\InquiryStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $inquiry_id
 * @property InquiryStatus|null $from_status
 * @property InquiryStatus $to_status
 * @property int|null $changed_by
 * @property string|null $note
 * @property Carbon $changed_at
 */
#[Fillable(['inquiry_id', 'from_status', 'to_status', 'changed_by', 'note', 'changed_at'])]
class InquiryStatusHistory extends Model
{
    protected $table = 'inquiry_status_history';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'from_status' => InquiryStatus::class,
            'to_status' => InquiryStatus::class,
            'changed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new \Exception('InquiryStatusHistory is immutable');
        });

        static::deleting(function () {
            throw new \Exception('InquiryStatusHistory cannot be deleted');
        });
    }

    /** @return BelongsTo<Inquiry, $this> */
    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class);
    }

    /** @return BelongsTo<User, $this> */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
