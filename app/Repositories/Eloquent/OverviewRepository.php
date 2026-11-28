<?php

namespace App\Repositories\Eloquent;

use App\Data\OverviewRange;
use App\Enums\ConversationType;
use App\Enums\DocumentType;
use App\Enums\DueStatus;
use App\Enums\InquiryStatus;
use App\Enums\InquiryStatusTrack;
use App\Enums\LeadStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProcessStatus;
use App\Enums\Role;
use App\Enums\SalesStatus;
use App\Enums\ScheduleKind;
use App\Enums\ScheduleStatus;
use App\Models\Conversation;
use App\Models\Inquiry;
use App\Models\Lead;
use App\Models\Schedule;
use App\Models\User;
use App\Repositories\Contracts\OverviewRepositoryInterface;
use App\Services\InquiryStatusCalculator;
use BackedEnum;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * The Overview's figures, read from what the inquiries, leads and visits
 * already hold. Nothing here is stored.
 *
 * Who a figure is credited to (decided with the studio):
 * - a sales rep: the inquiries they are assigned to (`inquiry_handlers`) and
 *   the walk-ins they own (`owner_user_id`), whoever clicked the status, so two
 *   reps on one inquiry both count it;
 * - a marketing user: the inquiries of the leads they hold.
 *
 * A deal's value is its contract total, as on the Sales page; a won deal not
 * yet billed is counted apart, at its quoted total (`unbilled`). An inquiry is won (or lost) when sales first marked it so,
 * so one sent back and won again counts once.
 */
class OverviewRepository implements OverviewRepositoryInterface
{
    /** A sum of money, "0.00" when there is nothing to add. */
    private const SUM = 'coalesce(sum(%s), 0)::numeric(14,2)';

    /** A deal's value: its billed contract total. */
    private const VALUE = 'inquiries.contract_total';

    /** What a won deal not yet billed was quoted at. */
    private const UNBILLED = 'case when inquiries.contract_total is null then inquiries.quoted_total end';

    /** The sales statuses of an inquiry sales has not decided on yet. */
    public const UNDECIDED = [SalesStatus::Negotiation, SalesStatus::SiteVisited, SalesStatus::OnHold, SalesStatus::Quoted, SalesStatus::Revision];

    /** The statuses of a visit still to happen. */
    private const UPCOMING_VISIT = [ScheduleStatus::Pending, ScheduleStatus::AwaitingSales, ScheduleStatus::Confirmed];

    /** How many days without a reply or a move before something counts as stale. */
    public const STALE_DAYS = 7;

    private const INQUIRY_CARD = ['clientRecord:id,full_name', 'client:id,name', 'service:id,name', 'application:id,name', 'schedule:id,inquiry_id,arrival_at'];

    // --- Sales -------------------------------------------------------------

    public function salesResults(?User $rep, OverviewRange $range): array
    {
        $won = $this->firstReached($this->sales($rep), SalesStatus::Won, $range)
            ->selectRaw('count(*) as count, '.sprintf(self::SUM, self::VALUE).' as total, '.sprintf(self::SUM, self::UNBILLED).' as unbilled')
            ->toBase()->first();
        $lost = $this->firstReached($this->sales($rep), SalesStatus::Rejected, $range)->count();
        $quotations = DB::table('inquiry_status_history')
            ->where('track', InquiryStatusTrack::Sales->value)
            ->whereIn('to_status', [SalesStatus::Quoted->value, SalesStatus::Revision->value])
            ->whereBetween('changed_at', [$range->from, $range->to])
            ->whereIn('inquiry_id', $this->sales($rep)->select('inquiries.id'))
            ->count();

        return [
            'won' => (int) ($won->count ?? 0),
            'value' => (string) ($won->total ?? '0.00'),
            'unbilled' => (string) ($won->unbilled ?? '0.00'),
            'lost' => $lost,
            'quotations' => $quotations,
            'averageDaysToClose' => $rep === null ? null : $this->averageDaysToClose($rep, $range),
        ];
    }

    public function salesPipeline(?User $rep): array
    {
        $rows = $this->sales($rep)
            ->whereIn('inquiries.sales_status', self::values(self::UNDECIDED))
            ->groupBy('inquiries.sales_status')
            ->selectRaw('inquiries.sales_status as status, count(*) as count, '.sprintf(self::SUM, 'inquiries.quoted_total').' as total')
            ->toBase()->get();

        $pipeline = [];

        foreach ($rows as $row) {
            $pipeline[(string) $row->status] = ['count' => (int) $row->count, 'total' => (string) $row->total];
        }

        return $pipeline;
    }

    public function salesTrend(?User $rep, OverviewRange $range): array
    {
        $trend = [];

        foreach ([SalesStatus::Won, SalesStatus::Rejected] as $status) {
            $rows = $this->firstReached($this->sales($rep), $status, $range)
                ->selectRaw('date_trunc(?, reached.reached_at at time zone ?)::date as bucket, count(*) as count, '.sprintf(self::SUM, self::VALUE).' as total', [$range->bucket(), $range->timezone])
                ->groupBy('bucket')
                ->toBase()->get();

            foreach ($rows as $row) {
                $trend[(string) $row->bucket][$status->value] = ['count' => (int) $row->count, 'total' => (string) $row->total];
            }
        }

        return $trend;
    }

    public function visitsAwaitingConfirmation(User $rep, int $limit): array
    {
        $query = Schedule::query()
            ->where('assigned_to', $rep->id)
            ->where('status', ScheduleStatus::AwaitingSales->value);

        return [(clone $query)->count(), $query->with(['clientRecord:id,full_name', 'client:id,name'])->orderBy('arrival_at')->limit($limit)->get()];
    }

    public function visitsToMarkAttended(User $rep, CarbonImmutable $now, int $limit): array
    {
        $query = Schedule::query()
            ->visibleTo($rep)
            ->where('status', ScheduleStatus::Confirmed->value)
            ->where('arrival_at', '<', $now);

        return [(clone $query)->count(), $query->with(['clientRecord:id,full_name', 'client:id,name'])->orderBy('arrival_at')->limit($limit)->get()];
    }

    public function staleQuotes(User $rep, CarbonImmutable $now, int $limit): array
    {
        $query = $this->sales($rep)
            ->whereIn('inquiries.sales_status', [SalesStatus::Quoted->value, SalesStatus::Revision->value])
            ->where('inquiries.sales_status_changed_at', '<', $now->subDays(self::STALE_DAYS));

        return [(clone $query)->count(), $query->with(self::INQUIRY_CARD)->orderBy('inquiries.sales_status_changed_at')->limit($limit)->get()];
    }

    public function wonWithoutJobOrder(User $rep, int $limit): array
    {
        $query = $this->sales($rep)
            ->where('inquiries.sales_status', SalesStatus::Won->value)
            ->whereDoesntHave('files', fn (Builder $files) => $files->where('document_type', DocumentType::JobOrder->value));

        return [(clone $query)->count(), $query->with(self::INQUIRY_CARD)->orderBy('inquiries.sales_status_changed_at')->limit($limit)->get()];
    }

    public function visitsAttended(User $rep, OverviewRange $range): int
    {
        // Showroom visits only: not an installation, a delivery or an inquiry's target date.
        return Schedule::query()
            ->visibleTo($rep)
            ->where('kind', ScheduleKind::ShowroomVisit->value)
            ->where('status', ScheduleStatus::Served->value)
            ->whereBetween('arrival_at', [$range->from, $range->to])
            ->count();
    }

    // --- Marketing ---------------------------------------------------------

    public function marketingResults(?User $marketer, OverviewRange $range): array
    {
        $decided = $this->held($marketer)
            ->whereBetween('inquiries.marketing_status_changed_at', [$range->from, $range->to])
            ->selectRaw('count(*) filter (where inquiries.marketing_status = ?) as won', [LeadStatus::Won->value])
            ->selectRaw('count(*) filter (where inquiries.marketing_status = ?) as lost', [LeadStatus::Lost->value])
            ->selectRaw('avg(extract(epoch from inquiries.marketing_status_changed_at - inquiries.created_at)) / 3600 as hours')
            ->toBase()->first();

        $sold = $this->held($marketer)
            ->where('inquiries.marketing_status', LeadStatus::Won->value)
            ->whereBetween('inquiries.marketing_status_changed_at', [$range->from, $range->to])
            ->whereExists(fn (QueryBuilder $won) => $won->selectRaw('1')->from('inquiry_status_history as sold')
                ->whereColumn('sold.inquiry_id', 'inquiries.id')
                ->where('sold.track', InquiryStatusTrack::Sales->value)
                ->where('sold.to_status', SalesStatus::Won->value))
            ->selectRaw('count(*) as count, '.sprintf(self::SUM, self::VALUE).' as total')
            ->toBase()->first();

        return [
            'newLeads' => Lead::query()
                ->when($marketer !== null, fn (Builder $leads) => $leads->where('marketing_user_id', $marketer?->id))
                ->whereBetween('created_at', [$range->from, $range->to])
                ->count(),
            'received' => $this->held($marketer)->whereBetween('inquiries.created_at', [$range->from, $range->to])->count(),
            'opened' => $marketer === null ? null : Inquiry::query()
                ->where('created_by', $marketer->id)
                ->whereBetween('created_at', [$range->from, $range->to])
                ->count(),
            'won' => (int) ($decided->won ?? 0),
            'lost' => (int) ($decided->lost ?? 0),
            'averageHoursToDecide' => $decided?->hours === null ? null : round((float) $decided->hours, 1),
            'sold' => (int) ($sold->count ?? 0),
            'soldValue' => (string) ($sold->total ?? '0.00'),
        ];
    }

    public function marketingTrend(User $marketer, OverviewRange $range): array
    {
        $trend = [];
        $bucket = 'date_trunc(?, %s at time zone ?)::date';

        $leads = Lead::query()
            ->where('marketing_user_id', $marketer->id)
            ->whereBetween('created_at', [$range->from, $range->to])
            ->selectRaw(sprintf($bucket, 'created_at').' as bucket, count(*) as count', [$range->bucket(), $range->timezone])
            ->groupBy('bucket')
            ->toBase()->get();

        foreach ($leads as $row) {
            $trend[(string) $row->bucket]['newLeads'] = (int) $row->count;
        }

        $decisions = $this->held($marketer)
            ->whereBetween('inquiries.marketing_status_changed_at', [$range->from, $range->to])
            ->selectRaw(sprintf($bucket, 'inquiries.marketing_status_changed_at').' as bucket, inquiries.marketing_status as status, count(*) as count', [$range->bucket(), $range->timezone])
            ->groupBy('bucket', 'inquiries.marketing_status')
            ->toBase()->get();

        foreach ($decisions as $row) {
            $trend[(string) $row->bucket][(string) $row->status] = (int) $row->count;
        }

        return $trend;
    }

    public function idleInquiries(User $marketer, int $limit): array
    {
        $query = $this->held($marketer)->whereNull('inquiries.marketing_status');

        return [(clone $query)->count(), $query->with(self::INQUIRY_CARD)->orderBy('inquiries.created_at')->limit($limit)->get()];
    }

    public function bookingsToAssign(?User $marketer, int $limit): array
    {
        $query = Schedule::query()
            ->whereNull('inquiry_id')
            ->where('status', ScheduleStatus::Pending->value)
            ->when($marketer !== null, fn (Builder $visits) => $visits->inMarketingQueueOf($marketer));

        return [
            (clone $query)->count(),
            (clone $query)->whereNotNull('declined_at')->count(),
            $query->with(['clientRecord:id,full_name', 'client:id,name'])->orderBy('arrival_at')->limit($limit)->get(),
        ];
    }

    public function unclaimedSupportChats(): int
    {
        return Conversation::query()
            ->where('type', ConversationType::Support->value)
            ->whereNull('assigned_to')
            ->whereHas('messages')
            ->count();
    }

    // --- Accounting --------------------------------------------------------

    public function awaitingPayment(User $user, int $limit): array
    {
        $query = Inquiry::query()
            ->visibleTo($user)
            ->where('inquiries.sales_status', SalesStatus::Won->value)
            ->where('inquiries.payment_status', PaymentStatus::Awaiting->value);

        return [(clone $query)->count(), $query->with(self::INQUIRY_CARD)->orderBy('inquiries.payment_status_changed_at')->limit($limit)->get()];
    }

    public function forRefund(?User $user, int $limit): array
    {
        $query = Inquiry::query()
            ->when($user !== null, fn (Builder $inquiries) => $inquiries->visibleTo($user))
            ->where('inquiries.sales_status', SalesStatus::ForRefund->value);

        return [(clone $query)->count(), $query->with(self::INQUIRY_CARD)->orderBy('inquiries.sales_status_changed_at')->limit($limit)->get()];
    }

    // --- Logistics and technical ---------------------------------------------

    public function processCounts(User $user): array
    {
        return $this->openJobs($user)
            ->groupBy('inquiries.process_status')
            ->selectRaw('inquiries.process_status as status, count(*) as count')
            ->pluck('count', 'status')
            ->map(fn ($count): int => (int) $count)
            ->all();
    }

    public function dueCounts(?User $user, CarbonImmutable $now, string $timezone): array
    {
        [$today, $soon] = $this->dueBounds($now, $timezone);

        $row = $this->openJobs($user)
            ->join('schedules as deadline', 'deadline.inquiry_id', '=', 'inquiries.id')
            ->selectRaw('count(*) filter (where deadline.arrival_at < ?) as overdue', [$today])
            ->selectRaw('count(*) filter (where deadline.arrival_at >= ? and deadline.arrival_at < ?) as due_soon', [$today, $soon])
            ->toBase()->first();

        return ['overdue' => (int) ($row->overdue ?? 0), 'dueSoon' => (int) ($row->due_soon ?? 0)];
    }

    public function jobsAt(User $user, array $statuses, int $limit): array
    {
        $query = $this->openJobs($user)->whereIn('inquiries.process_status', self::values($statuses));

        return [(clone $query)->count(), $query->with(self::INQUIRY_CARD)->orderBy('inquiries.process_status_changed_at')->limit($limit)->get()];
    }

    public function overdueJobs(User $user, CarbonImmutable $now, string $timezone, int $limit): array
    {
        [$today] = $this->dueBounds($now, $timezone);
        $query = $this->openJobs($user)->whereHas('schedule', fn (Builder $deadline) => $deadline->where('arrival_at', '<', $today));

        return [(clone $query)->count(), $this->byDeadline($query->with(self::INQUIRY_CARD))->limit($limit)->get()];
    }

    public function deadlinesAhead(User $user, CarbonImmutable $now, string $timezone, int $limit): Collection
    {
        [$today, $soon] = $this->dueBounds($now, $timezone);

        return $this->byDeadline($this->openJobs($user)
            ->whereHas('schedule', fn (Builder $deadline) => $deadline->where('arrival_at', '>=', $today)->where('arrival_at', '<', $soon))
            ->with(self::INQUIRY_CARD))
            ->limit($limit)
            ->get();
    }

    public function completedJobs(User $user, OverviewRange $range): int
    {
        return Inquiry::query()->visibleTo($user)->whereBetween('completed_at', [$range->from, $range->to])->count();
    }

    public function openAssigned(User $user, CarbonImmutable $now, string $timezone, int $limit): array
    {
        [, $soon] = $this->dueBounds($now, $timezone);
        $query = Inquiry::query()
            ->visibleTo($user)
            ->whereDisplayStatus(...array_filter(InquiryStatus::cases(), fn (InquiryStatus $status): bool => ! in_array($status, [InquiryStatus::Closed, InquiryStatus::Cancelled], true)));

        return [
            (clone $query)->count(),
            (clone $query)->whereHas('schedule', fn (Builder $deadline) => $deadline->where('arrival_at', '<', $soon))->count(),
            $this->byDeadline($query->withDisplayStatus()->with(self::INQUIRY_CARD))->limit($limit)->get(),
        ];
    }

    // --- Shared ------------------------------------------------------------

    public function upcomingVisits(User $user, CarbonImmutable $now, int $days, int $limit): Collection
    {
        return Schedule::query()
            ->visibleTo($user)
            ->whereIn('status', self::values(self::UPCOMING_VISIT))
            ->whereBetween('arrival_at', [$now, $now->addDays($days)])
            ->with(['clientRecord:id,full_name', 'client:id,name', 'inquiry:id,reference'])
            ->orderBy('arrival_at')
            ->limit($limit)
            ->get();
    }

    public function pipelineByStatus(): array
    {
        return Inquiry::query()
            ->selectRaw('('.InquiryStatusCalculator::SQL.') as status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->map(fn ($count): int => (int) $count)
            ->all();
    }

    public function staleInquiries(CarbonImmutable $now): int
    {
        return Inquiry::query()
            ->whereDisplayStatus(...array_filter(InquiryStatus::cases(), fn (InquiryStatus $status): bool => ! in_array($status, [InquiryStatus::Closed, InquiryStatus::Cancelled], true)))
            ->whereRaw('greatest(inquiries.created_at, inquiries.marketing_status_changed_at, inquiries.sales_status_changed_at, inquiries.payment_status_changed_at, inquiries.process_status_changed_at) < ?', [$now->subDays(self::STALE_DAYS)])
            ->count();
    }

    public function newClients(OverviewRange $range): int
    {
        return User::role(Role::Client->value)->whereBetween('created_at', [$range->from, $range->to])->count();
    }

    public function staff(Role $role): Collection
    {
        return User::role($role->value)->whereNull('archived_at')->orderBy('name')->get(['id', 'name']);
    }

    // --- Building blocks -----------------------------------------------------

    /**
     * The inquiries credited to a sales rep, or every inquiry for null.
     *
     * @return Builder<Inquiry>
     */
    private function sales(?User $rep): Builder
    {
        return Inquiry::query()->when($rep !== null, fn (Builder $inquiries) => $inquiries->where(fn (Builder $credited) => $credited
            ->whereExists(fn (QueryBuilder $handler) => $handler->selectRaw('1')->from('inquiry_handlers')
                ->whereColumn('inquiry_handlers.inquiry_id', 'inquiries.id')
                ->where('inquiry_handlers.user_id', $rep?->id))
            ->orWhere('inquiries.owner_user_id', $rep?->id)));
    }

    /**
     * The inquiries of the leads a marketing user holds, or of every lead for null.
     *
     * @return Builder<Inquiry>
     */
    private function held(?User $marketer): Builder
    {
        return Inquiry::query()->whereIn('inquiries.lead_id', Lead::query()
            ->when($marketer !== null, fn (Builder $leads) => $leads->where('marketing_user_id', $marketer?->id))
            ->select('id'));
    }

    /**
     * These inquiries, those sales first moved to `$status` within the range,
     * with when as `reached.reached_at`.
     *
     * @param  Builder<Inquiry>  $inquiries
     * @return Builder<Inquiry>
     */
    private function firstReached(Builder $inquiries, SalesStatus $status, OverviewRange $range): Builder
    {
        $first = DB::table('inquiry_status_history')
            ->where('track', InquiryStatusTrack::Sales->value)
            ->where('to_status', $status->value)
            ->groupBy('inquiry_id')
            ->select('inquiry_id')
            ->selectRaw('min(changed_at) as reached_at');

        return $inquiries
            ->joinSub($first, 'reached', 'reached.inquiry_id', '=', 'inquiries.id')
            ->whereBetween('reached.reached_at', [$range->from, $range->to]);
    }

    /** From the rep's assignment (or the walk-in's opening) to the first win, in days. */
    private function averageDaysToClose(User $rep, OverviewRange $range): ?float
    {
        $days = $this->firstReached($this->sales($rep), SalesStatus::Won, $range)
            ->leftJoin('inquiry_handlers as mine', fn ($join) => $join->on('mine.inquiry_id', '=', 'inquiries.id')->where('mine.user_id', $rep->id))
            ->selectRaw('avg(extract(epoch from reached.reached_at - coalesce(mine.assigned_at, inquiries.created_at))) / 86400 as days')
            ->toBase()->value('days');

        return $days === null ? null : round(max(0, (float) $days), 1);
    }

    /**
     * Jobs logistics has started and not completed, among those the reader
     * sees, or every one for null.
     *
     * @return Builder<Inquiry>
     */
    private function openJobs(?User $user): Builder
    {
        return Inquiry::query()
            ->when($user !== null, fn (Builder $inquiries) => $inquiries->visibleTo($user))
            ->whereNotNull('inquiries.process_status')
            ->where('inquiries.process_status', '!=', ProcessStatus::Completed->value)
            ->whereNull('inquiries.completed_at');
    }

    /**
     * The start of today and of the day after the due-soon window, in the
     * studio's zone, as DueStatus reads a deadline.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function dueBounds(CarbonImmutable $now, string $timezone): array
    {
        $today = $now->setTimezone($timezone)->startOfDay();

        return [$today, $today->addDays(DueStatus::DUE_SOON_DAYS + 1)];
    }

    /**
     * @param  Builder<Inquiry>  $query
     * @return Builder<Inquiry>
     */
    private function byDeadline(Builder $query): Builder
    {
        return $query->orderBy(Schedule::query()->select('arrival_at')->whereColumn('schedules.inquiry_id', 'inquiries.id')->limit(1))
            ->orderBy('inquiries.id');
    }

    /**
     * @param  list<BackedEnum>  $cases
     * @return list<string>
     */
    private static function values(array $cases): array
    {
        return array_map(fn (BackedEnum $case): string => (string) $case->value, $cases);
    }
}
