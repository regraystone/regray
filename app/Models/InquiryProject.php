<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $inquiry_id
 * @property string $name
 * @property int $sort_order
 * @property string $location
 * @property string|null $landmark
 * @property float|null $area_sqm
 * @property string|null $deadline_date
 * @property string|null $deadline_time
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['name', 'sort_order', 'location', 'landmark', 'area_sqm', 'deadline_date', 'deadline_time', 'notes'])]
class InquiryProject extends Model
{
    protected function casts(): array
    {
        return [
            'area_sqm' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Inquiry, $this> */
    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class);
    }

    /** @return BelongsToMany<Service, $this> */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'inquiry_project_services');
    }

    /** @return BelongsToMany<ProjectCategory, $this> */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(ProjectCategory::class, 'inquiry_project_categories')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }

    /** @return HasMany<InquiryProjectStone, $this> */
    public function stones(): HasMany
    {
        return $this->hasMany(InquiryProjectStone::class);
    }

    /** @return HasMany<InquiryFile, $this> */
    public function files(): HasMany
    {
        return $this->hasMany(InquiryFile::class);
    }
}
