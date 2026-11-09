<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * What a client can ask for: an entry of the second dropdown of "What do you
 * need?" on the Inquire page, under its service type.
 *
 * @property int $id
 * @property int $service_type_id
 * @property string $slug
 * @property string $name
 * @property string $description
 * @property list<string> $features
 * @property int $sort_order
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read ServiceType $serviceType
 * @property-read Collection<int, StoneType> $stoneTypes
 */
#[Fillable(['service_type_id', 'slug', 'name', 'description', 'features', 'sort_order', 'is_active'])]
class Service extends Model
{
    protected function casts(): array
    {
        return [
            'features' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return BelongsTo<ServiceType, $this> */
    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(ServiceType::class);
    }

    /** @return HasMany<Inquiry, $this> */
    public function inquiries(): HasMany
    {
        return $this->hasMany(Inquiry::class);
    }

    /**
     * The stone types it is made from: the Inquire page lists the stones whose
     * primary type is one of them.
     *
     * @return BelongsToMany<StoneType, $this>
     */
    public function stoneTypes(): BelongsToMany
    {
        return $this->belongsToMany(StoneType::class, 'service_stone_type');
    }

    /**
     * Whether new inquiries may ask for it: it and its type are both still
     * offered, and it is made from at least one stone type.
     */
    public function isOffered(): bool
    {
        return $this->is_active
            && $this->serviceType->is_active
            && ($this->relationLoaded('stoneTypes') ? $this->stoneTypes->isNotEmpty() : $this->stoneTypes()->exists());
    }
}
