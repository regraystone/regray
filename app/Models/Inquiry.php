<?php

namespace App\Models;

use App\Enums\ConversationType;
use App\Enums\DocumentType;
use App\Enums\InquiryStatus;
use App\Enums\InquiryStatusGroup;
use App\Enums\LeadStatus;
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
use Illuminate\Support\Facades\DB;

/**
 * One inquiry, which is one project: a client who wants another project opens
 * another inquiry. It belongs to its client's lead, which holds marketing's
 * status. A role's status exists only once its stage has started.
 *
 * @property int $id
 * @property string $reference
 * @property int|null $client_id The client's account; null for a direct (walk-in) client.
 * @property int|null $client_record_id The client's record (`clients`), with or without an account.
 * @property int|null $lead_id Null until the client's Regray Team thread is claimed.
 * @property string $name
 * @property string $location
 * @property string|null $landmark
 * @property string|null $area_sqm
 * @property string|null $notes
 * @property SalesStatus|null $sales_status Null until the inquiry's lead is won.
 * @property Carbon|null $sales_status_changed_at
 * @property PaymentStatus|null $payment_status Null until the client approves the quotation.
 * @property Carbon|null $payment_status_changed_at
 * @property ProcessStatus|null $process_status Null until the client approves the quotation.
 * @property Carbon|null $process_status_changed_at
 * @property string|null $quoted_total
 * @property int|null $quote_revision
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read InquiryStatus $status The status shown for the inquiry, calculated from its lead and role statuses.
 *
 * When the project is wanted is not stored here: it is the `arrival_at` of
 * the inquiry's schedule.
 */
#[Fillable([
    'reference', 'client_id', 'client_record_id', 'lead_id', 'name', 'location', 'landmark', 'area_sqm', 'notes',
    'sales_status', 'sales_status_changed_at',
    'payment_status', 'payment_status_changed_at', 'process_status', 'process_status_changed_at',
    'quoted_total', 'quote_revision',
])]
class Inquiry extends Model
{
    protected function casts(): array
    {
        return [
            'area_sqm' => 'decimal:2',
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
            $this->leadStatus(),
            $this->sales_status,
            $this->process_status,
            $this->hasJobOrder(),
        ))->withoutObjectCaching();
    }

    /**
     * Its lead's status, or null while it has no lead. Read from a
     * `lead_status` column when the query selected one (withLeadStatus), from
     * the loaded lead, or else from the database.
     */
    public function leadStatus(): ?LeadStatus
    {
        if ($this->lead_id === null) {
            return null;
        }

        if (array_key_exists('lead_status', $this->attributes)) {
            return LeadStatus::tryFrom((string) $this->attributes['lead_status']);
        }

        if ($this->relationLoaded('lead')) {
            return $this->lead?->status;
        }

        return Lead::query()->whereKey($this->lead_id)->first(['id', 'status'])?->status;
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

    /**
     * Select whether each inquiry has a job order, as `has_job_order`, so a list reads its status without a query per row.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function withJobOrderFlag(Builder $query): void
    {
        $query->withExists(['files as has_job_order' => fn (Builder $files) => $files->where('document_type', DocumentType::JobOrder)]);
    }

    /**
     * Select each inquiry's lead status, as `lead_status`, so a list reads its status without a query per row.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function withLeadStatus(Builder $query): void
    {
        if ($query->getQuery()->columns === null) {
            $query->select($this->qualifyColumn('*'));
        }

        $query->addSelect(['lead_status' => Lead::query()->select('status')->whereColumn('leads.id', 'inquiries.lead_id')]);
    }

    /**
     * Select the display status as `display_status`, calculated in the database by the same rules.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function withDisplayStatus(Builder $query): void
    {
        if ($query->getQuery()->columns === null) {
            $query->select($this->qualifyColumn('*'));
        }

        $query->selectRaw('('.InquiryStatusCalculator::SQL.') AS display_status');
    }

    /**
     * Only inquiries shown at one of these statuses.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function whereDisplayStatus(Builder $query, InquiryStatus ...$statuses): void
    {
        $query->whereIn(
            DB::raw('('.InquiryStatusCalculator::SQL.')'),
            array_map(fn (InquiryStatus $status): string => $status->value, $statuses),
        );
    }

    /**
     * Only inquiries under this Quote list tab.
     *
     * @param  Builder<static>  $query
     */
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

    /** @return BelongsTo<Client, $this> */
    public function clientRecord(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_record_id');
    }

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
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

    /**
     * The inquiry's group chat, which its status cards post into. At most one.
     *
     * @return HasOne<Conversation, $this>
     */
    public function conversation(): HasOne
    {
        return $this->hasOne(Conversation::class)->where('type', ConversationType::Group);
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
