<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, StoneType> $stoneTypes
 * @property Carbon|null $deleted_at Archived (the soft delete) from its Products page.
 */
#[Fillable(['name'])]
class Application extends Model
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
        return $this->belongsToMany(StoneType::class, 'application_stone_type');
    }
}
