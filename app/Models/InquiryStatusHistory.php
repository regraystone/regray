<?php

namespace App\Models;

use App\Enums\InquiryStatusTrack;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $inquiry_id
 * @property InquiryStatusTrack $track
 * @property string|null $from_status A value of the track's own status enum.
 * @property string $to_status A value of the track's own status enum.
 * @property int|null $changed_by
 * @property string|null $note
 * @property Carbon $changed_at
 */
#[Fillable(['inquiry_id', 'track', 'from_status', 'to_status', 'changed_by', 'note', 'changed_at'])]
class InquiryStatusHistory extends Model
{
    protected $table = 'inquiry_status_history';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'track' => InquiryStatusTrack::class,
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
