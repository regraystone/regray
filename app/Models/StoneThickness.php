<?php

namespace App\Models;

use Database\Factories\StoneThicknessFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property int $min_mm
 * @property int|null $max_mm
 * @property bool $is_active
 * @property int $sort_order
 */
#[Fillable(['slug', 'name', 'min_mm', 'max_mm', 'is_active', 'sort_order'])]
class StoneThickness extends Model
{
    /** @use HasFactory<StoneThicknessFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'min_mm' => 'integer', 'max_mm' => 'integer'];
    }

    /** @return BelongsToMany<Stone, $this> */
    public function stones(): BelongsToMany
    {
        return $this->belongsToMany(Stone::class, 'stone_stone_thickness');
    }
}
