<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string $thumbnail_path
 * @property string $description
 * @property string $origin
 * @property int $thickness_mm
 * @property bool $recommended
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['slug', 'name', 'thumbnail_path', 'description', 'origin', 'thickness_mm', 'recommended', 'is_active', 'sort_order'])]
class Stone extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'recommended' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function active()
    {
        return $this->where('is_active', true);
    }

    public function images(): HasMany
    {
        return $this->hasMany(StoneImage::class);
    }

    public function applications(): BelongsToMany
    {
        return $this->belongsToMany(Application::class, 'application_stone')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }

    public function careTips(): BelongsToMany
    {
        return $this->belongsToMany(CareTip::class, 'care_tip_stone')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }

    public function finishes(): BelongsToMany
    {
        return $this->belongsToMany(Finish::class, 'finish_stone')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }

    public function savedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'saved_stones')
            ->withTimestamps();
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_stone');
    }
}
