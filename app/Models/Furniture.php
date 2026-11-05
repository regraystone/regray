<?php

namespace App\Models;

use Database\Factories\FurnitureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * A furniture piece in its brand's collection.
 *
 * @property int $id
 * @property int $brand_id
 * @property string $slug
 * @property string $name
 * @property string|null $description
 * @property string|null $thumbnail_path
 * @property string $furniture_type
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Brand $brand
 */
#[Fillable(['brand_id', 'slug', 'name', 'description', 'thumbnail_path', 'furniture_type', 'is_active', 'sort_order'])]
class Furniture extends Model
{
    /** @use HasFactory<FurnitureFactory> */
    use HasFactory;

    /** The furniture types a piece can have, in the order the filter lists them. */
    public const TYPES = [
        'Seating',
        'Tables',
        'Storage',
        'Cabinets',
        'Beds',
        'Desks',
        'Shelving',
        'Decorative Furniture',
    ];

    protected $table = 'furniture';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @return BelongsTo<Brand, $this> */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function savedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'saved_furniture')
            ->withPivot('created_at');
    }
}
