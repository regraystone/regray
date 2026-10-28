<?php

namespace App\Http\Controllers;

use App\Actions\Inquiry\ListInquiryOptionsAction;
use App\Actions\Schedule\ListSchedulesAction;
use App\Enums\LeadStatus;
use App\Enums\Permission;
use App\Enums\Role;
use App\Http\Requests\DataTableRequest;
use App\Http\Requests\Schedule\ScheduleIndexRequest;
use App\Http\Resources\InquiryCatalogResource;
use App\Http\Resources\InquiryRowResource;
use App\Http\Resources\LeadOptionResource;
use App\Http\Resources\LeadResource;
use App\Http\Resources\ScheduleResource;
use App\Models\Lead;
use App\Models\User;
use App\Repositories\Contracts\InquiryRepositoryInterface;
use App\Repositories\Contracts\LeadRepositoryInterface;
use App\Services\ScheduleService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Date;
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
        return Inertia::render('inquiry', [
            'inquiries' => self::page($inquiries->paginateForStaff($request->escapedSearch(), $request->perPage(), $request->page()), InquiryRowResource::class, $request),
            'filters' => $request->filters(),
        ]);
    }

    /**
     * The Inquire page and everything it offers: the services, the stones, the
     * applications, the edge profiles and finishes still in use, and the
     * locations. All of it is read here rather than imported by the page, so
     * the flow and the validation behind it start from the same rows.
     *
     * Staff open an inquiry for a lead's client, so they are also given the
     * leads they may pick (`leads`), and `lead` when the URL names one of
     * them: sales any won lead, marketing its own idle or won leads, the
     * super-admin every open lead. A client's `leads` is null: they send their
     * own.
     */
    public function inquire(Request $request, ListInquiryOptionsAction $listInquiryOptions, LeadRepositoryInterface $leads): Response
    {
        /** @var User $user */
        $user = $request->user();
        $pickable = $user->isClient() ? null : $this->pickableLeads($user, $leads);

        return Inertia::render('inquire', [
            ...InquiryCatalogResource::make($listInquiryOptions->execute())->resolve($request),
            'mode' => 'create',
            'leads' => $pickable === null ? null : LeadOptionResource::collection($pickable)->resolve($request),
            'lead' => $pickable?->firstWhere('reference', $request->query('lead'))?->reference,
        ]);
    }

    /**
     * The Leads page: the marketing user's own leads, newest first, a page at a
     * time (every lead for the super-admin).
     */
    public function leads(DataTableRequest $request, LeadRepositoryInterface $leads): Response
    {
        /** @var User $user */
        $user = $request->user();
        $holder = $user->hasRole(Role::superAdmin()->value) ? null : $user;

        return Inertia::render('leads', [
            'leads' => self::page($leads->paginateFor($holder, $request->escapedSearch(), $request->perPage(), $request->page()), LeadResource::class, $request),
            'filters' => $request->filters(),
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

    /** @return Collection<int, Lead> */
    private function pickableLeads(User $user, LeadRepositoryInterface $leads): Collection
    {
        if ($user->hasRole(Role::superAdmin()->value)) {
            return $leads->withStatuses([LeadStatus::Idle, LeadStatus::Won], null);
        }

        if ($user->hasRole(Role::Sales->value)) {
            return $leads->withStatuses([LeadStatus::Won], null);
        }

        return $leads->withStatuses([LeadStatus::Idle, LeadStatus::Won], $user);
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

    public function stones(Request $request): Response
    {
        return Inertia::render('stones');
    }

    public function services(Request $request): Response
    {
        return Inertia::render('services');
    }

    public function accounts(Request $request): Response
    {
        return Inertia::render('accounts');
    }

    public function projects(Request $request): Response
    {
        return Inertia::render('projects');
    }
}
