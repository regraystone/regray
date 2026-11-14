<?php

namespace App\Models;

use App\Data\Measurement;
use App\Data\SizeMeasurement;
use App\Enums\SizeUnit;
use Database\Factories\SizeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One slab size a stone can be had in, width by length in one unit, shared
 * by every stone that comes in it (`stone_variants`). Its `label` is how every
 * page writes it ("160 x 320 cm"); its `key` is how a filter link names it
 * ("160x320-cm").
 *
 * @property int $id
 * @property string $width A decimal string, "160.00".
 * @property string $length
 * @property SizeUnit $unit
 * @property int $sort_order
 * @property-read string $label
 * @property-read string $key
 * @property-read Collection<int, Stone> $stones
 */
#[Fillable(['width', 'length', 'unit', 'sort_order'])]
class Size extends Model
{
    /** @use HasFactory<SizeFactory> */
    use HasFactory;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'width' => 'decimal:2',
            'length' => 'decimal:2',
            'unit' => SizeUnit::class,
        ];
    }

    /**
     * The stones some slab of which comes in it, each once however many of
     * its slabs do. Read the rows to count them (`->stones->count()`): a
     * query count, `has()` or `withCount()` counts the slabs, since
     * SELECT DISTINCT does not reach COUNT(*).
     *
     * @return BelongsToMany<Stone, $this>
     */
    public function stones(): BelongsToMany
    {
        return $this->belongsToMany(Stone::class, 'stone_variants')->distinct();
    }

    /** @return HasMany<StoneVariant, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(StoneVariant::class);
    }

    public function measurement(): SizeMeasurement
    {
        return new SizeMeasurement(Measurement::number($this->width), Measurement::number($this->length), $this->unit);
    }

    /** @return Attribute<string, never> */
    protected function label(): Attribute
    {
        return Attribute::get(fn (): string => $this->measurement()->label());
    }

    /** @return Attribute<string, never> */
    protected function key(): Attribute
    {
        return Attribute::get(fn (): string => $this->measurement()->key());
    }

    /**
     * The size a filter link names by its key; none when the key is not one.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function matchingKey(Builder $query, string $key): void
    {
        $measurement = SizeMeasurement::fromKey($key);

        $measurement === null
            ? $query->whereRaw('false')
            : $query->where('width', $measurement->width)
                ->where('length', $measurement->length)
                ->where('unit', $measurement->unit->value);
    }

    /**
     * Those at least one active, listed stone comes in: the options a filter
     * offers and accepts.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function offered(Builder $query): void
    {
        $query->whereHas('stones', fn (Builder $stones) => $stones->where('stones.is_active', true));
    }
}
