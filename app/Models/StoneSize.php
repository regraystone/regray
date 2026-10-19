<?php

namespace App\Models;

use Database\Factories\StoneSizeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $name
 * @property bool $is_active
 */
#[Fillable(['name', 'is_active'])]
class StoneSize extends Model
{
    /** @use HasFactory<StoneSizeFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return BelongsToMany<Stone, $this> */
    public function stones(): BelongsToMany
    {
        return $this->belongsToMany(Stone::class, 'stone_stone_size');
    }
}
