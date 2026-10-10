<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $inquiry_project_id
 * @property int $stone_id
 * @property int $edge_profile_id
 * @property int $finish_id
 * @property int $quantity
 * @property int $sort_order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['stone_id', 'edge_profile_id', 'finish_id', 'quantity', 'sort_order'])]
class InquiryProjectStone extends Model
{
    /** @return BelongsTo<InquiryProject, $this> */
    public function inquiryProject(): BelongsTo
    {
        return $this->belongsTo(InquiryProject::class);
    }

    /** @return BelongsTo<Stone, $this> */
    public function stone(): BelongsTo
    {
        return $this->belongsTo(Stone::class);
    }

    /** @return BelongsTo<EdgeProfile, $this> */
    public function edgeProfile(): BelongsTo
    {
        return $this->belongsTo(EdgeProfile::class);
    }

    /** @return BelongsTo<Finish, $this> */
    public function finish(): BelongsTo
    {
        return $this->belongsTo(Finish::class);
    }
}
