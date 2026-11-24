<?php

namespace App\Models;

use Database\Factories\FurnitureTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A type a piece of furniture can be, such as "Tables".
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property int $sort_order
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at Archived (the soft delete) from its Products page.
 */
#[Fillable(['slug', 'name', 'sort_order', 'is_active'])]
class FurnitureType extends Model
{
    /** @use HasFactory<FurnitureTypeFactory> */
    use HasFactory;

    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @return HasMany<Furniture, $this> */
    public function furniture(): HasMany
    {
        return $this->hasMany(Furniture::class);
    }
}
