<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One slab a stone comes in: a size at a thickness. The size is null for a
 * stone sold without set sizes (its `size_note` says how big the pieces run).
 * Its `label` is how a page writes it: "3.15 x 1.98 m · 20 mm", or just "2 cm"
 * with no size.
 *
 * @property int $id
 * @property int $stone_id
 * @property int|null $size_id
 * @property int $thickness_id
 * @property int $sort_order
 * @property-read string $label
 * @property-read Stone $stone
 * @property-read Size|null $size
 * @property-read Thickness $thickness
 */
#[Fillable(['stone_id', 'size_id', 'thickness_id', 'sort_order'])]
class StoneVariant extends Model
{
    public $timestamps = false;

    /** @return BelongsTo<Stone, $this> */
    public function stone(): BelongsTo
    {
        return $this->belongsTo(Stone::class);
    }

    /** @return BelongsTo<Size, $this> */
    public function size(): BelongsTo
    {
        // An archived size still names the slabs of a stone archived with it.
        return $this->belongsTo(Size::class)->withTrashed();
    }

    /** @return BelongsTo<Thickness, $this> */
    public function thickness(): BelongsTo
    {
        return $this->belongsTo(Thickness::class)->withTrashed();
    }

    /** @return Attribute<string, never> */
    protected function label(): Attribute
    {
        return Attribute::get(fn (): string => $this->size === null
            ? $this->thickness->label
            : "{$this->size->label} · {$this->thickness->label}");
    }
}
