<?php

namespace App\Http\Controllers;

use App\Actions\Inquiry\ListInquiryOptionsAction;
use App\Actions\Inquiry\ListInquiryStonesAction;
use App\Actions\Schedule\ListSchedulesAction;
use App\Enums\InquiryStatus;
use App\Enums\LeadStatus;
use App\Enums\PaymentStatus;
use App\Enums\Permission;
use App\Enums\ProcessStatus;
use App\Enums\Role;
use App\Enums\SocialPlatform;
use App\Enums\ScheduleAction;
use App\Http\Requests\DataTableRequest;
use App\Http\Requests\Schedule\ScheduleIndexRequest;
use App\Http\Resources\AccountResource;
use App\Http\Resources\AdminBrandResource;
use App\Http\Resources\ClientAddressResource;
use App\Http\Resources\InquiryCatalogResource;
use App\Http\Resources\InquiryRowResource;
use App\Http\Resources\LeadOptionResource;
use App\Http\Resources\LeadResource;
use App\Http\Resources\ScheduleResource;
use App\Models\Lead;
use App\Models\Schedule;
use App\Models\User;
use App\Repositories\Contracts\BrandRepositoryInterface;
use App\Repositories\Contracts\ClientAddressRepositoryInterface;
use App\Repositories\Contracts\InquiryRepositoryInterface;
use App\Repositories\Contracts\LeadRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\InquiryStatusRules;
use App\Services\ScheduleService;
use App\Services\WalkInInquiryService;
use App\Support\ReservedSlugs;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ViewController extends Controller
{
    public function overview(Request $request): Response
    {
        return Inertia::render('overview');
    }

    public function saved(Request $request): Response
    {
        return Inertia::render('saved');
    }

    public function sales(Request $request): Response
    {
        return Inertia::render('sales');
    }

    /**
     * The Inquiry list: every inquiry, a page at a time, the same columns for
     * every staff role, with the actions this reader has on each row
     * (InquiryRowResource).
     */
    public function inquiry(DataTableRequest $request, InquiryRepositoryInterface $inquiries): Response
    {
        /** @var User $user */
        $user = $request->user();
        $statusFilter = $request->query('status');

        return Inertia::render('inquiry', [
            'inquiries' => self::page($inquiries->paginateForStaff($request->escapedSearch(), $user, $statusFilter, $request->perPage(), $request->page()), InquiryRowResource::class, $request),
            'filters' => array_merge($request->filters(), ['status' => $statusFilter ?? 'all']),
            'statusOptions' => $this->statusOptionsForUser($user),
        ]);
    }

    /**
     * The Inquiry list's status filter: accounting by its payment status,
     * logistics by its process status, everyone else by the inquiry's status.
     *
     * @return list<array{value: string, label: string}>
     */
    private function statusOptionsForUser(User $user): array
    {
        $role = $user->primaryRole();
        $options = [['value' => 'all', 'label' => 'All statuses']];

        if ($role === Role::Accounting) {
            foreach (PaymentStatus::cases() as $status) {
                $options[] = [
                    'value' => $status->value,
                    'label' => match ($status) {
                        PaymentStatus::Awaiting => 'Awaiting',
                        PaymentStatus::Partial => 'Partially paid',
                        PaymentStatus::FullyPaid => 'Fully paid',
                        PaymentStatus::PartiallyRefunded => 'Partially refunded',
                        PaymentStatus::Refunded => 'Refunded',
                    },
                ];
            }
        } elseif ($role === Role::Logistics) {
            foreach (ProcessStatus::cases() as $status) {
                $options[] = [
                    'value' => $status->value,
                    'label' => match ($status) {
                        ProcessStatus::OrderReceived => 'Order received',
                        ProcessStatus::Measure => 'Measure',
                        ProcessStatus::Sourcing => 'Sourcing',
                        ProcessStatus::Production => 'Production',
                        ProcessStatus::QualityCheck => 'Quality check',
                        ProcessStatus::Ready => 'Ready',
                        ProcessStatus::Dispatched => 'Dispatched',
                        ProcessStatus::Installation => 'Installation',
                        ProcessStatus::Completed => 'Completed',
                        ProcessStatus::OnHold => 'On hold',
                        ProcessStatus::Delayed => 'Delayed',
                    },
                ];
            }
        } else {
            // Pending is only on the list for those who see pending inquiries.
            $seesPending = InquiryStatusRules::seesPending($user);

            foreach (InquiryStatus::cases() as $status) {
                if ($status === InquiryStatus::Pending && ! $seesPending) {
                    continue;
                }

                // Sales won shows as quoted, so no inquiry is ever at won.
                if ($status === InquiryStatus::Won) {
                    continue;
                }

                $options[] = [
                    'value' => $status->value,
                    'label' => match ($status) {
                        InquiryStatus::Pending => 'Pending',
                        InquiryStatus::Cancelled => 'Cancelled',
                        InquiryStatus::Closed => 'Closed',
                        InquiryStatus::Negotiation => 'Negotiation',
                        InquiryStatus::SiteVisited => 'Site visited',
                        InquiryStatus::OnHold => 'On hold',
                        InquiryStatus::Quoted => 'Quoted',
                        InquiryStatus::Revision => 'Revision',
                        InquiryStatus::OrderReceived => 'Order received',
                        InquiryStatus::Measure => 'Measure',
                        InquiryStatus::Sourcing => 'Sourcing',
                        InquiryStatus::Production => 'Production',
                        InquiryStatus::QualityCheck => 'Quality check',
                        InquiryStatus::Ready => 'Ready',
                        InquiryStatus::Dispatched => 'Dispatched',
                        InquiryStatus::Installation => 'Installation',
                        InquiryStatus::Delayed => 'Delayed',
                        InquiryStatus::ForRefund => 'For refund',
                    },
                ];
            }
        }

        return $options;
    }

    /**
     * The Inquire page and everything it offers: the brands, the service
     * types and their services, the stones a page at a time, and the edge
     * profiles and finishes still in use. All of it is read here rather than
     * imported by the page, so the flow and the validation behind it start
     * from the same rows.
     *
     * A client sending their own inquiry is also given their address book
     * (`addresses`, the default first), which a project that needs an address
     * picks from. Staff are not: they enter the address themselves.
     *
     * Staff open an inquiry for a lead's client, so they are also given the
     * leads they may pick (`leads`), and `lead` when the URL names one of
     * them: sales any won lead, marketing its own idle or won leads, the
     * super-admin every open lead. A client's `leads` is null: they send their
     * own.
     */
    public function inquire(Request $request, ListInquiryOptionsAction $listInquiryOptions, ListInquiryStonesAction $listInquiryStones, LeadRepositoryInterface $leads, ClientAddressRepositoryInterface $addresses, WalkInInquiryService $walkIns): Response
    {
        /** @var User $user */
        $user = $request->user();
        $pickable = $user->isClient() ? null : $this->pickableLeads($user, $leads);

        return Inertia::render('inquire', [
            ...InquiryCatalogResource::make($listInquiryOptions->execute())->resolve($request),
            ...$listInquiryStones->execute($request),
            'mode' => 'create',
            // Staff do not get the key at all: there is no book to pick from.
            ...($pickable === null ? ['addresses' => ClientAddressResource::collection($addresses->forUser($user))->resolve($request)] : []),
            'leads' => $pickable === null ? null : LeadOptionResource::collection($pickable)->resolve($request),
            'lead' => $pickable?->firstWhere('reference', $request->query('lead'))?->reference,
            // Who a walk-in's inquiry may be given to: null for sales, who owns what it opens, and for a client.
            'walkInOwners' => $pickable === null ? null : $walkIns->ownerOptions($user)?->map(fn (User $owner): array => ['id' => $owner->id, 'name' => $owner->name])->values()->all(),
        ]);
    }

    /**
     * The Leads page: the marketing user's own leads, newest first, a page at a
     * time (every lead for the super-admin). The status filter keeps the leads
     * with an inquiry at that decision.
     */
    public function leads(DataTableRequest $request, LeadRepositoryInterface $leads): Response
    {
        /** @var User $user */
        $user = $request->user();
        $holder = $user->hasRole(Role::superAdmin()->value) ? null : $user;

        // An unknown status is no filter, like any other bad value on a list.
        $status = LeadStatus::tryFrom((string) $request->query('status'));

        return Inertia::render('leads', [
            'leads' => self::page($leads->paginateFor($holder, $request->escapedSearch(), $request->perPage(), $request->page(), $status), LeadResource::class, $request),
            'filters' => array_merge($request->filters(), ['status' => $status->value ?? 'all']),
            'canCreateGroup' => $user->can(Permission::ConversationsCreate->value),
        ]);
    }

    /**
     * A page of a DataTable list: its rows through `$resource`, and the
     * pagination block the table reads ("Showing 11–20 of 42").
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  LengthAwarePaginator<int, TModel>  $paginator
     * @param  class-string<JsonResource>  $resource
     * @return array{data: array<int, mixed>, meta: array{currentPage: int, lastPage: int, perPage: int, total: int, from: int|null, to: int|null}}
     */
    private static function page(LengthAwarePaginator $paginator, string $resource, Request $request): array
    {
        return [
            'data' => $resource::collection($paginator->getCollection())->resolve($request),
            'meta' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];
    }

    /**
     * The leads this staff member may open an inquiry from (LeadPolicy): every
     * lead for the super-admin, marketing's own, and none for sales, which
     * opens inquiries only for direct (walk-in) clients.
     *
     * @return Collection<int, Lead>
     */
    private function pickableLeads(User $user, LeadRepositoryInterface $leads): Collection
    {
        if ($user->hasRole(Role::superAdmin()->value)) {
            return $leads->pickable(null);
        }

        // Sales opens inquiries only for direct (walk-in) clients: no lead to pick.
        if (! $user->can(Permission::InquiriesCreatePlatform->value)) {
            return new Collection;
        }

        return $leads->pickable($user);
    }

    /**
     * The Schedule page, at /schedule and /profile/schedule alike.
     *
     * Both props are closures, so a partial reload that asks only for
     * `schedules` (the page stepping through weeks) does not read `upcoming`.
     */
    public function schedule(ScheduleIndexRequest $request, ListSchedulesAction $listSchedules): Response
    {
        $viewer = $request->user();
        $range = $request->range();

        return Inertia::render('schedule', [
            'schedules' => fn (): array => ScheduleResource::collection(
                $listSchedules->inRange($viewer, $range['from'], $range['to']),
            )->resolve($request),
            'upcoming' => fn (): array => ScheduleResource::collection(
                $listSchedules->upcoming($viewer),
            )->resolve($request),
        ]);
    }

    /**
     * Update an appointment: the Book an appointment page, filled in with it,
     * asking for the reason for the change. One page at two URLs like the
     * schedule itself, the staff one and the client's Profile one.
     *
     * Who may update it is SchedulePolicy's (a client only their own, staff
     * only one they read: any other is a 404); an
     * appointment whose state no longer allows it (served, cancelled, expired,
     * its time passed, or an inquiry's visit) sends the reader back to the
     * schedule with the reason.
     */
    public function scheduleEdit(Request $request, Schedule $schedule, ScheduleService $scheduleService): Response|RedirectResponse
    {
        Gate::authorize('update', $schedule);

        /** @var User $viewer */
        $viewer = $request->user();
        $back = $viewer->isClient() ? route('profile.schedule') : route('schedule.index');
        $refusal = $scheduleService->refusalFor($schedule, ScheduleAction::Update);

        if ($refusal !== null) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $refusal]);

            return redirect($back);
        }

        $now = Date::now(ScheduleService::timezone());
        $arrival = $schedule->arrival_at->setTimezone(ScheduleService::timezone());

        return Inertia::render('schedule-book', [
            'studioNow' => ['date' => $now->toDateString(), 'time' => $now->format('H:i')],
            'earliestBookableDate' => ScheduleService::earliestBookableDate($viewer)->toDateString(),
            'schedule' => [
                'id' => $schedule->id,
                'title' => $schedule->title,
                'date' => $arrival->toDateString(),
                'arrivalTime' => $arrival->format('H:i'),
                'notes' => $schedule->notes,
                'status' => $schedule->status->for($viewer)->value,
                'clientName' => $viewer->isClient() ? null : $schedule->clientName(),
            ],
        ]);
    }

    /**
     * The studio's date and time now, so the page decides which days and slots
     * are already past by Manila's clock rather than the browser's.
     */
    public function scheduleBook(Request $request): Response
    {
        $now = Date::now(ScheduleService::timezone());

        return Inertia::render('schedule-book', [
            'studioNow' => ['date' => $now->toDateString(), 'time' => $now->format('H:i')],
            'earliestBookableDate' => ScheduleService::earliestBookableDate($request->user())->toDateString(),
        ]);
    }

    /**
     * The admin's Brands page: every brand but the archived, hidden ones too,
     * by display order then name, a page at a time, searched by the brand's
     * name. There is no status filter.
     */
    public function brands(DataTableRequest $request, BrandRepositoryInterface $brands): Response
    {
        return Inertia::render('brands', [
            'brands' => self::page($brands->paginateForAdmin(
                $request->escapedSearch(),
                $request->perPage(),
                $request->page(),
            ), AdminBrandResource::class, $request),
            'filters' => $request->filters(),
            'canCreate' => $request->user()?->can(Permission::BrandsCreate->value) ?? false,
            'limits' => [
                'logoMaxKb' => config()->integer('brands.logo_max_kb'),
                'thumbnailMaxKb' => config()->integer('brands.thumbnail_max_kb'),
                'factsMax' => config()->integer('brands.facts_max'),
                'factLabelMax' => config()->integer('brands.fact_label_max'),
                'factValueMax' => config()->integer('brands.fact_value_max'),
                'makesMax' => config()->integer('brands.makes_max'),
                'makeMax' => config()->integer('brands.make_max'),
            ],
            // The networks a brand may link to, each with its own icon on its page.
            'socialPlatforms' => array_map(
                fn (SocialPlatform $platform): array => ['value' => $platform->value, 'label' => $platform->label()],
                SocialPlatform::cases(),
            ),
            'reservedSlugs' => ReservedSlugs::ALL,
            'publicBrandUrl' => route('brands.show', '__slug__'),
        ]);
    }

    public function products(Request $request): Response
    {
        return Inertia::render('products');
    }

    public function services(Request $request): Response
    {
        return Inertia::render('services');
    }

    /**
     * The admin's Accounts page: the staff accounts the admin manages (never an
     * admin or a client), but the archived, newest first, a page at a time, searched by name or email and filtered by status
     * (`all`, `active`, `inactive`; anything else is `all`).
     *
     * Also what the Add and Edit drawers offer: the staff roles an account may
     * be given (`assignableRoles`), and the temporary password a new account
     * starts with (`defaultPassword`), shown read-only and never sent back.
     */
    public function accounts(DataTableRequest $request, UserRepositoryInterface $users): Response
    {
        $status = in_array($request->query('status'), ['active', 'inactive'], true) ? (string) $request->query('status') : 'all';

        return Inertia::render('accounts', [
            'accounts' => self::page($users->paginateAccounts(
                $request->escapedSearch(),
                $status === 'all' ? null : $status === 'active',
                $request->perPage(),
                $request->page(),
            ), AccountResource::class, $request),
            'filters' => array_merge($request->filters(), ['status' => $status]),
            'assignableRoles' => array_map(fn (Role $role): array => [
                'value' => $role->value,
                'label' => AccountResource::roleLabel($role),
            ], Role::staffRoles()),
            'defaultPassword' => config()->string('accounts.default_password'),
            'canCreate' => $request->user()?->can(Permission::AccountsCreate->value) ?? false,
        ]);
    }

    public function projects(Request $request): Response
    {
        return Inertia::render('projects');
    }
}
