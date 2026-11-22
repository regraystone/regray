<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property bool $is_advisory
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, StoneType> $stoneTypes
 * @property Carbon|null $deleted_at Archived (the soft delete) from its Products page.
 */
#[Fillable(['name', 'is_advisory', 'is_active', 'sort_order'])]
class EdgeProfile extends Model
{
    use SoftDeletes;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'is_advisory' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** @return Builder<EdgeProfile> */
    public function active(): Builder
    {
        return $this->where('is_active', true);
    }

    /**
     * The stone types that list it.
     *
     * @return BelongsToMany<StoneType, $this>
     */
    public function stoneTypes(): BelongsToMany
    {
        return $this->belongsToMany(StoneType::class, 'edge_profile_stone_type');
    }
}
