<?php

namespace App\Models;

use App\Data\Measurement;
use App\Data\ThicknessMeasurement;
use App\Enums\ThicknessUnit;
use Database\Factories\ThicknessFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One thickness a stone can be had in, shared by every stone that comes in it
 * (`stone_variants`). Its `label` is how every page writes it ("20 mm",
 * "0.75 in"); its `key` is how a filter link names it ("20-mm").
 *
 * @property int $id
 * @property string $value A decimal string, "20.00".
 * @property ThicknessUnit $unit
 * @property int $sort_order
 * @property-read string $label
 * @property-read string $key
 * @property-read Collection<int, Stone> $stones
 */
#[Fillable(['value', 'unit', 'sort_order'])]
class Thickness extends Model
{
    /** @use HasFactory<ThicknessFactory> */
    use HasFactory;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'unit' => ThicknessUnit::class,
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

    public function measurement(): ThicknessMeasurement
    {
        return new ThicknessMeasurement(Measurement::number($this->value), $this->unit);
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
     * The thickness a filter link names by its key; none when the key is not
     * one.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function matchingKey(Builder $query, string $key): void
    {
        $measurement = ThicknessMeasurement::fromKey($key);

        $measurement === null
            ? $query->whereRaw('false')
            : $query->where('value', $measurement->value)->where('unit', $measurement->unit->value);
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
