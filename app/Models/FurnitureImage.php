<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One photo in a piece of furniture's gallery.
 *
 * @property int $id
 * @property int $furniture_id
 * @property string $path
 * @property string|null $alt_text
 * @property int $sort_order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['path', 'alt_text', 'sort_order'])]
class FurnitureImage extends Model
{
    /** @return BelongsTo<Furniture, $this> */
    public function furniture(): BelongsTo
    {
        return $this->belongsTo(Furniture::class);
    }
}
