<?php

namespace App\Models;

use App\Enums\InquiryProjectStatus;
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
 * @property int|null $schedule_id
 * @property string|null $notes
 * @property InquiryProjectStatus $status
 * @property Carbon $status_changed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * When the project is wanted is not stored here: it is the `arrival_at` of the
 * schedule the inquiry created for it (`schedule_id`).
 */
#[Fillable(['name', 'sort_order', 'location', 'landmark', 'area_sqm', 'schedule_id', 'notes', 'status', 'status_changed_at'])]
class InquiryProject extends Model
{
    protected function casts(): array
    {
        return [
            'area_sqm' => 'decimal:2',
            'status' => InquiryProjectStatus::class,
            'status_changed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Inquiry, $this> */
    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class);
    }

    /** @return BelongsTo<Schedule, $this> */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
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

    /** @return HasMany<Quotation, $this> */
    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class)->orderBy('revision_no');
    }

    /** @return HasMany<JobOrder, $this> */
    public function jobOrders(): HasMany
    {
        return $this->hasMany(JobOrder::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
