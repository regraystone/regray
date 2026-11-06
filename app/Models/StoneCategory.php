<?php

namespace App\Models;

use App\Services\BestSellerService;
use Database\Factories\StoneCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string $thumbnail_path
 * @property string $description
 * @property bool $is_active
 * @property-read bool $is_best_seller
 * @property int $sort_order
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, Stone> $stones
 * @property-read Collection<int, Application> $applications
 * @property-read Collection<int, Finish> $finishes
 * @property-read Collection<int, CareTip> $careTips
 */
#[Fillable(['slug', 'name', 'thumbnail_path', 'description', 'is_active', 'sort_order'])]
class StoneCategory extends Model
{
    /** @use HasFactory<StoneCategoryFactory> */
    use HasFactory;

    use SoftDeletes;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * Whether the category is a best seller. Asked of the request's one
     * ranking, never stored.
     *
     * @return Attribute<bool, never>
     */
    protected function isBestSeller(): Attribute
    {
        return Attribute::get(fn (): bool => app(BestSellerService::class)->isBestSeller($this->id));
    }

    /** @param Builder<static> $query */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @return BelongsToMany<Stone, $this> */
    public function stones(): BelongsToMany
    {
        return $this->belongsToMany(Stone::class, 'stone_stone_category');
    }

    /** @return BelongsToMany<Application, $this> */
    public function applications(): BelongsToMany
    {
        return $this->belongsToMany(Application::class, 'application_stone_category')
            ->withPivot('sort_order')->orderByPivot('sort_order');
    }

    /** @return BelongsToMany<Finish, $this> */
    public function finishes(): BelongsToMany
    {
        return $this->belongsToMany(Finish::class, 'finish_stone_category')
            ->withPivot('sort_order')->orderByPivot('sort_order');
    }

    /** @return BelongsToMany<CareTip, $this> */
    public function careTips(): BelongsToMany
    {
        return $this->belongsToMany(CareTip::class, 'care_tip_stone_category')
            ->withPivot('sort_order')->orderByPivot('sort_order');
    }
}
