<?php

namespace App\Models;

use App\Data\PhAddress;
use App\Data\StaffVisibility;
use App\Enums\ConversationType;
use App\Enums\DeliveryMethod;
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
use Illuminate\Database\Eloquent\Collection;
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
 * @property int|null $created_by The staff member who opened it for the client; null when the client sent it.
 * @property LeadStatus|null $marketing_status Marketing's decision, won or lost; null while it waits for one.
 * @property Carbon|null $marketing_status_changed_at
 * @property string $name
 * @property int|null $service_id What the client asked for.
 * @property bool $brand_no_preference The client chose No preference for brands.
 * @property DeliveryMethod|null $delivery_method Null for an installed service, which asks for none.
 * @property string|null $address_region_code
 * @property string|null $address_region_name
 * @property string|null $address_province_code
 * @property string|null $address_province_name
 * @property string|null $address_city_code
 * @property string|null $address_city_name
 * @property string|null $address_barangay_code
 * @property string|null $address_barangay_name
 * @property string|null $address_postal_code
 * @property string|null $address_lat
 * @property string|null $address_lng
 * @property-read Collection<int, Brand> $brands
 * @property string|null $notes
 * @property bool $stone_advice_requested Whether the client asked Regray to recommend a stone instead of choosing one.
 * @property SalesStatus|null $sales_status Null until marketing wins the inquiry.
 * @property Carbon|null $sales_status_changed_at
 * @property PaymentStatus|null $payment_status Null until the client approves the quotation.
 * @property Carbon|null $payment_status_changed_at
 * @property ProcessStatus|null $process_status Null until the client approves the quotation.
 * @property Carbon|null $process_status_changed_at
 * @property Carbon|null $completed_at When logistics moved to completed; null until then.
 * @property string|null $quoted_total
 * @property int|null $quote_revision
 * @property string|null $contract_total The total contract amount billed at awaiting; null until then.
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read InquiryStatus $status The status shown for the inquiry, calculated from marketing's decision and the role statuses.
 *
 * When the project is wanted is not stored here: it is the `arrival_at` of
 * the inquiry's schedule.
 */
#[Fillable([
    'reference', 'client_id', 'client_record_id', 'lead_id', 'created_by', 'marketing_status', 'marketing_status_changed_at',
    'name', 'service_id', 'brand_no_preference', 'delivery_method', 'notes', 'stone_advice_requested',
    'address_region_code', 'address_region_name', 'address_province_code', 'address_province_name', 'address_city_code', 'address_city_name',
    'address_barangay_code', 'address_barangay_name', 'address_postal_code', 'address_lat', 'address_lng',
    'sales_status', 'sales_status_changed_at',
    'payment_status', 'payment_status_changed_at', 'process_status', 'process_status_changed_at', 'completed_at',
    'quoted_total', 'quote_revision', 'contract_total',
])]
class Inquiry extends Model
{
    /** The prefix of the address columns (PhAddress). */
    public const ADDRESS_PREFIX = 'address_';

    protected function casts(): array
    {
        return [
            'brand_no_preference' => 'boolean',
            'address_lat' => 'decimal:6',
            'address_lng' => 'decimal:6',
            'delivery_method' => DeliveryMethod::class,
            'stone_advice_requested' => 'boolean',
            'sales_status' => SalesStatus::class,
            'sales_status_changed_at' => 'datetime',
            'payment_status' => PaymentStatus::class,
            'payment_status_changed_at' => 'datetime',
            'process_status' => ProcessStatus::class,
            'marketing_status' => LeadStatus::class,
            'marketing_status_changed_at' => 'datetime',
            'process_status_changed_at' => 'datetime',
            'completed_at' => 'datetime',
            'quoted_total' => 'decimal:2',
            'contract_total' => 'decimal:2',
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
            $this->payment_status,
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

    /**
     * Only the inquiries this staff member reads (StaffVisibility): every one
     * for the admin and the owner; otherwise those of a lead marketing holds,
     * those marketing or sales opened, and those sales, technical, accounting
     * or logistics are assigned to, whichever of these the user's roles give.
     * A user with none of them reads none. Not for clients, who read their own.
     *
     * isVisibleTo() is the same rule for one loaded inquiry.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        $visibility = StaffVisibility::for($user);

        if ($visibility->everything) {
            return;
        }

        if ($visibility->seesNoInquiry()) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function (Builder $query) use ($visibility, $user): void {
            if ($visibility->leadInquiries) {
                $query->orWhereHas('lead', fn (Builder $lead) => $lead->where('marketing_user_id', $user->id));
            }

            if ($visibility->assignedInquiries) {
                $query->orWhereHas('handlers', fn (Builder $handler) => $handler->where('user_id', $user->id));
            }

            if ($visibility->createdInquiries) {
                $query->orWhere($this->qualifyColumn('created_by'), $user->id);
            }
        });
    }

    /**
     * Whether this staff member reads this inquiry: scopeVisibleTo() for one
     * row, read from its lead and handlers (loaded when they are not), so a
     * list already holding them asks the database nothing more.
     */
    public function isVisibleTo(User $user): bool
    {
        $visibility = StaffVisibility::for($user);

        if ($visibility->everything) {
            return true;
        }

        // Loaded without the columns the rule reads (`inquiry:id,reference`): ask the database.
        if (! array_key_exists('created_by', $this->attributes) || ! array_key_exists('lead_id', $this->attributes)) {
            return static::query()->visibleTo($user)->whereKey($this->getKey())->exists();
        }

        if ($visibility->createdInquiries && $this->created_by === $user->id) {
            return true;
        }

        if ($visibility->leadInquiries && $this->lead_id !== null && $this->leadMarketingUserId() === $user->id) {
            return true;
        }

        return $visibility->assignedInquiries
            && $this->loadMissing('handlers')->handlers->contains(fn (InquiryHandler $handler): bool => $handler->user_id === $user->id);
    }

    /**
     * Whether marketing has won it, which a group chat waits for
     * (InquiryPolicy::createGroup()).
     */
    public function isWonByMarketing(): bool
    {
        return $this->marketing_status === LeadStatus::Won;
    }

    /** The marketing user of the inquiry's lead, from the loaded lead when it holds the column. */
    private function leadMarketingUserId(): ?int
    {
        $lead = $this->relationLoaded('lead') ? $this->lead : null;

        if ($lead !== null && array_key_exists('marketing_user_id', $lead->getAttributes())) {
            return $lead->marketing_user_id;
        }

        $marketingUserId = Lead::query()->whereKey($this->lead_id)->value('marketing_user_id');

        return $marketingUserId === null ? null : (int) $marketingUserId;
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

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** @return HasMany<InquiryStone, $this> */
    public function stones(): HasMany
    {
        return $this->hasMany(InquiryStone::class);
    }

    /**
     * The project-scope answers its service asked for, one per question.
     *
     * @return HasMany<InquiryDetailAnswer, $this>
     */
    public function detailAnswers(): HasMany
    {
        return $this->hasMany(InquiryDetailAnswer::class);
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
     * The brands the client would like, when they named any; none when they
     * chose No preference (`brand_no_preference`).
     *
     * @return BelongsToMany<Brand, $this>
     */
    public function brands(): BelongsToMany
    {
        return $this->belongsToMany(Brand::class, 'inquiry_brands')->orderBy('brands.sort_order');
    }

    /**
     * Where the work goes, as it was copied when the inquiry was sent or last
     * edited; null for a pickup.
     */
    public function address(): ?PhAddress
    {
        return PhAddress::fromColumns($this->getAttributes(), self::ADDRESS_PREFIX);
    }

    /**
     * How the work reaches the client, as every page and email words it:
     * "Pickup", "Delivery to {address}", or "Installation at {address}" for a
     * service with no delivery method to choose.
     * resources/js/lib/ph-address.ts fulfillmentSummary() words it the same.
     */
    public function fulfillmentSummary(): string
    {
        if ($this->delivery_method === DeliveryMethod::Pickup) {
            return 'Pickup';
        }

        $line = $this->address()?->line() ?? 'an address not given';

        return $this->delivery_method === DeliveryMethod::Delivery ? "Delivery to {$line}" : "Installation at {$line}";
    }

    /**
     * The address in one short phrase for a card or a calendar entry: the city,
     * or null for a pickup.
     */
    public function placeLabel(): ?string
    {
        return $this->getAttribute('address_city_name');
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
