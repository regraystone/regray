<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $tip
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, StoneType> $stoneTypes
 * @property Carbon|null $deleted_at Archived (the soft delete) from its Products page.
 */
#[Fillable(['tip'])]
class CareTip extends Model
{
    use SoftDeletes;

    public $timestamps = true;

    /**
     * The stone types that list it.
     *
     * @return BelongsToMany<StoneType, $this>
     */
    public function stoneTypes(): BelongsToMany
    {
        return $this->belongsToMany(StoneType::class, 'care_tip_stone_type');
    }
}
