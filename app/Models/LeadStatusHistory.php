<?php

namespace App\Models;

use App\Enums\LeadStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $lead_id
 * @property LeadStatus|null $from_status
 * @property LeadStatus $to_status
 * @property int|null $changed_by
 * @property string|null $note Why it was marked won or lost.
 * @property Carbon $changed_at
 */
#[Fillable(['lead_id', 'from_status', 'to_status', 'changed_by', 'note', 'changed_at'])]
class LeadStatusHistory extends Model
{
    protected $table = 'lead_status_history';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'from_status' => LeadStatus::class,
            'to_status' => LeadStatus::class,
            'changed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new \Exception('LeadStatusHistory is immutable');
        });

        static::deleting(function () {
            throw new \Exception('LeadStatusHistory cannot be deleted');
        });
    }

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /** @return BelongsTo<User, $this> */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
