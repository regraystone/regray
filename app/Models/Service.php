<?php

namespace App\Models;

use App\Enums\Fulfillment;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
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
 * @property bool $requires_stones Whether it asks for stones; Installation Only does not.
 * @property Fulfillment $fulfillment Whether it asks how the client would like to get it, or only where it is installed.
 * @property string|null $delivery_note A line under the delivery method on the Inquire page.
 * @property string|null $schedule_note A line under the date on the Inquire page.
 * @property int|null $lead_days The fewest days from today a client may ask for it; null is today.
 * @property int $sort_order
 * @property bool $is_active
 * @property Carbon|null $archived_at Set by the admin's Services page, with is_active false.
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read ServiceType $serviceType
 * @property-read Collection<int, StoneType> $stoneTypes
 * @property-read Collection<int, ServiceFeature> $features
 * @property-read Collection<int, DetailField> $detailFields
 */
#[Fillable(['service_type_id', 'slug', 'name', 'description', 'requires_stones', 'fulfillment', 'delivery_note', 'schedule_note', 'lead_days', 'sort_order', 'is_active'])]
class Service extends Model
{
    protected function casts(): array
    {
        return [
            'requires_stones' => 'boolean',
            'fulfillment' => Fulfillment::class,
            'is_active' => 'boolean',
            'lead_days' => 'integer',
            'archived_at' => 'datetime',
        ];
    }

    /**
     * The services still on the admin's Services page: hidden or not, but not
     * archived.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function notArchived(Builder $query): void
    {
        $query->whereNull($query->qualifyColumn('archived_at'));
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
     * What it includes, as the lines shown under it.
     *
     * @return HasMany<ServiceFeature, $this>
     */
    public function features(): HasMany
    {
        return $this->hasMany(ServiceFeature::class)->orderBy('sort_order');
    }

    /**
     * The questions it asks besides the stones, in the order they are asked,
     * each with whether it has to be answered.
     *
     * @return BelongsToMany<DetailField, $this, ServiceDetailField, 'pivot'>
     */
    public function detailFields(): BelongsToMany
    {
        return $this->belongsToMany(DetailField::class, 'service_detail_fields')
            ->using(ServiceDetailField::class)
            ->withPivot(['sort_order', 'is_required'])
            ->orderByPivot('sort_order');
    }

    /**
     * Whether new inquiries may ask for it: it and its type are both still
     * offered, and, unless it asks for no stones, it is made from at least one
     * stone type.
     */
    public function isOffered(): bool
    {
        return $this->is_active
            && $this->serviceType->is_active
            && (! $this->requires_stones || ($this->relationLoaded('stoneTypes') ? $this->stoneTypes->isNotEmpty() : $this->stoneTypes()->exists()));
    }
}
