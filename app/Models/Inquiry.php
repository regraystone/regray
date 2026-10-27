<?php

namespace App\Models;

use App\Enums\DocumentType;
use App\Enums\InquiryStatus;
use App\Enums\InquiryStatusGroup;
use App\Enums\MarketingStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProcessStatus;
use App\Enums\SalesStatus;
use App\Services\InquiryStatusCalculator;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * One inquiry, which is one project: a client who wants another project opens
 * another inquiry. A role's status exists only once its stage has started.
 *
 * @property int $id
 * @property string $reference
 * @property int $client_id
 * @property string $name
 * @property string $location
 * @property string|null $landmark
 * @property string|null $area_sqm
 * @property string|null $notes
 * @property MarketingStatus $marketing_status
 * @property Carbon $marketing_status_changed_at
 * @property SalesStatus|null $sales_status Null until marketing has won the client.
 * @property Carbon|null $sales_status_changed_at
 * @property PaymentStatus|null $payment_status Null until the client approves the quotation.
 * @property Carbon|null $payment_status_changed_at
 * @property ProcessStatus|null $process_status Null until the client approves the quotation.
 * @property Carbon|null $process_status_changed_at
 * @property string|null $quoted_total
 * @property int|null $quote_revision
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read InquiryStatus $status The status shown for the inquiry, calculated from the role statuses.
 *
 * When the project is wanted is not stored here: it is the `arrival_at` of
 * the inquiry's schedule.
 */
#[Fillable([
    'reference', 'client_id', 'name', 'location', 'landmark', 'area_sqm', 'notes',
    'marketing_status', 'marketing_status_changed_at', 'sales_status', 'sales_status_changed_at',
    'payment_status', 'payment_status_changed_at', 'process_status', 'process_status_changed_at',
    'quoted_total', 'quote_revision',
])]
class Inquiry extends Model
{
    protected function casts(): array
    {
        return [
            'area_sqm' => 'decimal:2',
            'marketing_status' => MarketingStatus::class,
            'marketing_status_changed_at' => 'datetime',
            'sales_status' => SalesStatus::class,
            'sales_status_changed_at' => 'datetime',
            'payment_status' => PaymentStatus::class,
            'payment_status_changed_at' => 'datetime',
            'process_status' => ProcessStatus::class,
            'process_status_changed_at' => 'datetime',
            'quoted_total' => 'decimal:2',
        ];
    }

    /**
     * The status shown for the inquiry, everywhere. Never cached on the model,
     * so a role status changed in memory shows at once.
     *
     * @return Attribute<InquiryStatus, never>
     */
    protected function status(): Attribute
    {
        return Attribute::get(fn (): InquiryStatus => InquiryStatusCalculator::calculate(
            $this->marketing_status,
            $this->sales_status,
            $this->process_status,
            $this->hasJobOrder(),
        ))->withoutObjectCaching();
    }

    /**
     * Whether sales has uploaded the job order. Read from a `has_job_order`
     * column when the query selected one (withJobOrderFlag), from the loaded
     * files, or else from the database.
     */
    public function hasJobOrder(): bool
    {
        if (array_key_exists('has_job_order', $this->attributes)) {
            return (bool) $this->attributes['has_job_order'];
        }

        if ($this->relationLoaded('files')) {
            return $this->files->contains(fn (InquiryFile $file): bool => $file->document_type === DocumentType::JobOrder);
        }

        return $this->files()->where('document_type', DocumentType::JobOrder)->exists();
    }

    /** Select whether each inquiry has a job order, as `has_job_order`, so a list reads its status without a query per row. */
    #[Scope]
    protected function withJobOrderFlag(Builder $query): void
    {
        $query->withExists(['files as has_job_order' => fn (Builder $files) => $files->where('document_type', DocumentType::JobOrder)]);
    }

    /** Select the display status as `display_status`, calculated in the database by the same rules. */
    #[Scope]
    protected function withDisplayStatus(Builder $query): void
    {
        if ($query->getQuery()->columns === null) {
            $query->select($this->qualifyColumn('*'));
        }

        $query->selectRaw('('.InquiryStatusCalculator::sql($this->getTable()).') AS display_status');
    }

    /** Only inquiries shown at one of these statuses. */
    #[Scope]
    protected function whereDisplayStatus(Builder $query, InquiryStatus ...$statuses): void
    {
        $values = array_map(fn (InquiryStatus $status): string => $status->value, $statuses);

        $query->whereRaw(
            '('.InquiryStatusCalculator::sql($this->getTable()).') IN ('.implode(',', array_fill(0, count($values), '?')).')',
            $values,
        );
    }

    /** Only inquiries under this Quote list tab. */
    #[Scope]
    protected function inStatusGroup(Builder $query, InquiryStatusGroup $group): void
    {
        $query->whereDisplayStatus(...$group->statuses());
    }

    /** @return BelongsTo<User, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /** @return HasMany<InquiryHandler, $this> */
    public function handlers(): HasMany
    {
        return $this->hasMany(InquiryHandler::class);
    }

    /** @return HasMany<InquiryStatusHistory, $this> */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(InquiryStatusHistory::class)->orderBy('changed_at')->orderBy('id');
    }

    /** @return BelongsToMany<Service, $this> */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'inquiry_services');
    }

    /** @return BelongsToMany<ProjectCategory, $this> */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(ProjectCategory::class, 'inquiry_categories')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }

    /** @return HasMany<InquiryStone, $this> */
    public function stones(): HasMany
    {
        return $this->hasMany(InquiryStone::class);
    }

    /** @return HasMany<InquiryFile, $this> */
    public function files(): HasMany
    {
        return $this->hasMany(InquiryFile::class);
    }

    /** @return HasMany<PaymentTransaction, $this> */
    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class)->orderBy('received_at');
    }

    /**
     * The visit that holds the project's target deadline. One per inquiry.
     *
     * @return HasOne<Schedule, $this>
     */
    public function schedule(): HasOne
    {
        return $this->hasOne(Schedule::class);
    }

    /** @return HasOne<Conversation, $this> */
    public function conversation(): HasOne
    {
        return $this->hasOne(Conversation::class);
    }

    /** @return HasMany<Message, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /** @return HasMany<Schedule, $this> */
    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }
}
