<?php

namespace App\Repositories\Eloquent;

use App\Data\OverviewRange;
use App\Enums\InquiryStatusTrack;
use App\Enums\LeadStatus;
use App\Enums\PaymentStatus;
use App\Enums\SalesStatus;
use App\Enums\ScheduleKind;
use App\Enums\ScheduleStatus;
use App\Models\Inquiry;
use App\Models\Lead;
use App\Models\Schedule;
use App\Models\User;
use App\Repositories\Contracts\OverviewInsightsRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * The Overview's deeper figures: the funnel, delivery on time, speed, results
 * by service, where inquiries come from, quotes, visit outcomes, the ageing of
 * what is owed, and the stones and brands asked for.
 *
 * Whose inquiries, as in OverviewRepository: a sales rep's are the ones
 * assigned to them (or the walk-ins they own), a marketing user's the ones on
 * the leads they hold, any other reader's the ones they may see
 * (Inquiry::visibleTo()), and null is the whole studio.
 */
class OverviewInsightsRepository implements OverviewInsightsRepositoryInterface
{
    private const SUM = 'coalesce(sum(%s), 0)::numeric(14,2)';

    // --- Whose inquiries -----------------------------------------------------

    /** @return Builder<Inquiry> */
    private function ofRep(?User $rep): Builder
    {
        return Inquiry::query()->when($rep !== null, fn (Builder $inquiries) => $inquiries->where(fn (Builder $credited) => $credited
            ->whereExists(fn (QueryBuilder $handler) => $handler->selectRaw('1')->from('inquiry_handlers')
                ->whereColumn('inquiry_handlers.inquiry_id', 'inquiries.id')
                ->where('inquiry_handlers.user_id', $rep?->id))
            ->orWhere('inquiries.owner_user_id', $rep?->id)));
    }

    /** @return Builder<Inquiry> */
    private function ofMarketer(User $marketer): Builder
    {
        return Inquiry::query()->whereIn('inquiries.lead_id', Lead::query()->where('marketing_user_id', $marketer->id)->select('id'));
    }

    /** @return Builder<Inquiry> */
    private function seenBy(?User $reader): Builder
    {
        return Inquiry::query()->when($reader !== null, fn (Builder $inquiries) => $inquiries->visibleTo($reader));
    }

    /**
     * @param  'rep'|'marketer'|'reader'  $as
     * @return Builder<Inquiry>
     */
    private function whose(string $as, ?User $user): Builder
    {
        return match ($as) {
            'rep' => $this->ofRep($user),
            'marketer' => $user === null ? Inquiry::query() : $this->ofMarketer($user),
            default => $this->seenBy($user),
        };
    }

    /** When each inquiry first reached a status on a track. */
    private function firstOn(InquiryStatusTrack $track, string $status): QueryBuilder
    {
        return DB::table('inquiry_status_history')
            ->where('track', $track->value)
            ->where('to_status', $status)
            ->groupBy('inquiry_id')
            ->select('inquiry_id')
            ->selectRaw('min(changed_at) as reached_at');
    }

    // --- 1. Funnel -------------------------------------------------------------

    public function funnel(string $as, ?User $user, OverviewRange $range): array
    {
        $row = $this->whose($as, $user)
            ->whereBetween('inquiries.created_at', [$range->from, $range->to])
            ->selectRaw('count(*) as received')
            ->selectRaw('count(*) filter (where inquiries.marketing_status = ? or inquiries.sales_status is not null) as passed', [LeadStatus::Won->value])
            ->selectRaw('count(*) filter (where inquiries.quote_revision is not null) as quoted')
            ->selectRaw('count(*) filter (where exists (select 1 from inquiry_status_history h where h.inquiry_id = inquiries.id and h.track = ? and h.to_status = ?)) as won', [InquiryStatusTrack::Sales->value, SalesStatus::Won->value])
            ->selectRaw("count(*) filter (where exists (select 1 from payment_transactions p where p.inquiry_id = inquiries.id and p.kind = 'payment')) as paid")
            ->selectRaw('count(*) filter (where inquiries.completed_at is not null) as completed')
            ->toBase()->first();

        return [
            'received' => (int) ($row->received ?? 0),
            'passed' => (int) ($row->passed ?? 0),
            'quoted' => (int) ($row->quoted ?? 0),
            'won' => (int) ($row->won ?? 0),
            'paid' => (int) ($row->paid ?? 0),
            'completed' => (int) ($row->completed ?? 0),
        ];
    }

    // --- 2. Delivered on time --------------------------------------------------

    public function onTime(?User $reader, OverviewRange $range): array
    {
        // Each day on the studio's clock: done by the end of the day it was needed is on time.
        $tz = $range->timezone;

        $row = $this->seenBy($reader)
            ->join('schedules as deadline', 'deadline.inquiry_id', '=', 'inquiries.id')
            ->whereBetween('inquiries.completed_at', [$range->from, $range->to])
            ->selectRaw('count(*) as completed')
            ->selectRaw('count(*) filter (where (inquiries.completed_at at time zone ?)::date <= (deadline.arrival_at at time zone ?)::date) as on_time', [$tz, $tz])
            ->selectRaw('avg((inquiries.completed_at at time zone ?)::date - (deadline.arrival_at at time zone ?)::date) filter (where (inquiries.completed_at at time zone ?)::date > (deadline.arrival_at at time zone ?)::date) as days_late', [$tz, $tz, $tz, $tz])
            ->toBase()->first();

        return [
            'completed' => (int) ($row->completed ?? 0),
            'onTime' => (int) ($row->on_time ?? 0),
            'averageDaysLate' => ($row->days_late ?? null) === null ? null : round((float) $row->days_late, 1),
        ];
    }

    // --- 3. Speed ----------------------------------------------------------------

    public function daysToFirstQuote(string $as, ?User $user, OverviewRange $range): ?float
    {
        return $this->averageDays($this->whose($as, $user)
            ->joinSub($this->firstOn(InquiryStatusTrack::Sales, SalesStatus::Quoted->value), 'quoted', 'quoted.inquiry_id', '=', 'inquiries.id')
            ->whereBetween('quoted.reached_at', [$range->from, $range->to]), 'quoted.reached_at - inquiries.created_at');
    }

    public function daysWonToCompleted(?User $reader, OverviewRange $range): ?float
    {
        return $this->averageDays($this->seenBy($reader)
            ->joinSub($this->firstOn(InquiryStatusTrack::Sales, SalesStatus::Won->value), 'won', 'won.inquiry_id', '=', 'inquiries.id')
            ->whereBetween('inquiries.completed_at', [$range->from, $range->to]), 'inquiries.completed_at - won.reached_at');
    }

    public function daysWonToPaid(?User $reader, OverviewRange $range): ?float
    {
        return $this->averageDays($this->seenBy($reader)
            ->joinSub($this->firstOn(InquiryStatusTrack::Sales, SalesStatus::Won->value), 'won', 'won.inquiry_id', '=', 'inquiries.id')
            ->joinSub($this->firstOn(InquiryStatusTrack::Payment, PaymentStatus::FullyPaid->value), 'paid', 'paid.inquiry_id', '=', 'inquiries.id')
            ->whereBetween('paid.reached_at', [$range->from, $range->to]), 'paid.reached_at - won.reached_at');
    }

    /**
     * @param  Builder<Inquiry>  $query
     * @param  literal-string  $interval
     */
    private function averageDays(Builder $query, string $interval): ?float
    {
        $days = $query->selectRaw("avg(extract(epoch from {$interval})) / 86400 as days")->toBase()->value('days');

        return $days === null ? null : round(max(0, (float) $days), 1);
    }

    // --- 4. By service -----------------------------------------------------------

    public function byService(?User $rep, OverviewRange $range): array
    {
        $grouped = fn (SalesStatus $status) => $this->ofRep($rep)
            ->joinSub($this->firstOn(InquiryStatusTrack::Sales, $status->value), 'reached', 'reached.inquiry_id', '=', 'inquiries.id')
            ->whereBetween('reached.reached_at', [$range->from, $range->to])
            ->join('services', 'services.id', '=', 'inquiries.service_id')
            ->leftJoin('services as applications', 'applications.id', '=', 'inquiries.application_id')
            // Each application on its own line: "Slab Supply and Installation · Flooring".
            ->groupByRaw("concat_ws(' · ', services.name, applications.name)")
            ->selectRaw("concat_ws(' · ', services.name, applications.name) as service, count(*) as count, coalesce(sum(inquiries.contract_total), 0)::numeric(14,2) as total")
            ->toBase()->get();

        $rows = [];

        foreach ($grouped(SalesStatus::Won) as $row) {
            $rows[(string) $row->service] = ['service' => (string) $row->service, 'won' => (int) $row->count, 'value' => (string) $row->total, 'lost' => 0];
        }

        foreach ($grouped(SalesStatus::Rejected) as $row) {
            $rows[(string) $row->service] ??= ['service' => (string) $row->service, 'won' => 0, 'value' => '0.00', 'lost' => 0];
            $rows[(string) $row->service]['lost'] = (int) $row->count;
        }

        $rows = array_values($rows);
        usort($rows, fn (array $a, array $b): int => [(float) $b['value'], $b['won'], $a['service']] <=> [(float) $a['value'], $a['won'], $b['service']]);

        return $rows;
    }

    // --- 5. Where inquiries come from -------------------------------------------

    public function channels(?User $marketer, OverviewRange $range): array
    {
        $walkIn = '(inquiries.owner_user_id is not null or inquiries.client_id is null)';

        $row = $this->whose('marketer', $marketer)
            ->whereBetween('inquiries.created_at', [$range->from, $range->to])
            ->selectRaw("count(*) filter (where not {$walkIn} and inquiries.created_by is null) as online")
            ->selectRaw("count(*) filter (where not {$walkIn} and inquiries.created_by is not null) as staff")
            ->selectRaw("count(*) filter (where {$walkIn}) as walk_in")
            ->toBase()->first();

        return ['online' => (int) ($row->online ?? 0), 'staff' => (int) ($row->staff ?? 0), 'walkIn' => (int) ($row->walk_in ?? 0)];
    }

    public function places(?User $marketer, OverviewRange $range, int $limit): array
    {
        return array_values($this->whose('marketer', $marketer)
            ->whereBetween('inquiries.created_at', [$range->from, $range->to])
            ->whereNotNull('inquiries.address_city_name')
            ->groupBy('inquiries.address_city_name', 'inquiries.address_province_name')
            ->selectRaw('inquiries.address_city_name as city, inquiries.address_province_name as province, count(*) as count')
            ->orderByDesc('count')
            ->orderBy('city')
            ->limit($limit)
            ->toBase()->get()
            ->map(fn ($row): array => [
                'label' => $row->province !== null && $row->province !== $row->city ? "{$row->city}, {$row->province}" : (string) $row->city,
                'count' => (int) $row->count,
            ])
            ->all());
    }

    // --- 6. Quotes ---------------------------------------------------------------

    public function quotes(?User $rep, OverviewRange $range): array
    {
        $row = $this->ofRep($rep)
            ->joinSub($this->firstOn(InquiryStatusTrack::Sales, SalesStatus::Won->value), 'won', 'won.inquiry_id', '=', 'inquiries.id')
            ->whereBetween('won.reached_at', [$range->from, $range->to])
            ->selectRaw('avg(inquiries.quote_revision) as quotes')
            ->selectRaw('count(*) filter (where inquiries.quote_revision > 1) as revised')
            ->selectRaw('count(*) as won')
            ->selectRaw('avg((inquiries.contract_total - inquiries.quoted_total) * 100 / nullif(inquiries.quoted_total, 0)) as difference')
            ->toBase()->first();

        return [
            'won' => (int) ($row->won ?? 0),
            'averageQuotes' => ($row->quotes ?? null) === null ? null : round((float) $row->quotes, 1),
            'revised' => (int) ($row->revised ?? 0),
            'contractVsQuote' => ($row->difference ?? null) === null ? null : round((float) $row->difference, 1),
        ];
    }

    // --- 7. Visits -----------------------------------------------------------------

    public function visitOutcomes(?User $reader, OverviewRange $range, CarbonImmutable $now): array
    {
        $wonAfter = '(select 1 from inquiries won_inquiry join inquiry_status_history won_h on won_h.inquiry_id = won_inquiry.id'
            ." and won_h.track = '".InquiryStatusTrack::Sales->value."' and won_h.to_status = '".SalesStatus::Won->value."'"
            .' where won_h.changed_at >= schedules.arrival_at and (won_inquiry.id = schedules.inquiry_id'
            .' or (schedules.client_id is not null and won_inquiry.client_id = schedules.client_id)'
            .' or (schedules.client_record_id is not null and won_inquiry.client_record_id = schedules.client_record_id)))';

        // Showroom visits only: not an installation, a delivery or an inquiry's target date.
        $row = Schedule::query()
            ->where('kind', ScheduleKind::ShowroomVisit->value)
            ->when($reader !== null, fn (Builder $visits) => $visits->visibleTo($reader))
            ->whereBetween('arrival_at', [$range->from, min($range->to, $now)])
            ->selectRaw('count(*) filter (where status = ?) as attended', [ScheduleStatus::Served->value])
            ->selectRaw('count(*) filter (where status = ?) as expired', [ScheduleStatus::Expired->value])
            ->selectRaw('count(*) filter (where status = ?) as cancelled', [ScheduleStatus::Cancelled->value])
            ->selectRaw('count(*) filter (where status = ?) as unmarked', [ScheduleStatus::Confirmed->value])
            ->selectRaw("count(*) filter (where status = ? and exists {$wonAfter}) as led_to_win", [ScheduleStatus::Served->value])
            ->toBase()->first();

        return array_map(fn ($count): int => (int) $count, [
            'attended' => $row->attended ?? 0,
            'expired' => $row->expired ?? 0,
            'cancelled' => $row->cancelled ?? 0,
            'unmarked' => $row->unmarked ?? 0,
            'ledToWin' => $row->led_to_win ?? 0,
        ]);
    }

    // --- 8. Ageing of what is owed ---------------------------------------------------

    public function ageing(User $reader, CarbonImmutable $now): array
    {
        $paid = DB::table('payment_transactions')
            ->groupBy('inquiry_id')
            ->select('inquiry_id')
            ->selectRaw("sum(case when kind = 'payment' then amount else -amount end) as paid_net");
        $age = 'extract(epoch from (?::timestamptz - won.reached_at)) / 86400';
        $balance = 'inquiries.contract_total - coalesce(paid.paid_net, 0)';

        $rows = $this->seenBy($reader)
            ->where('inquiries.sales_status', SalesStatus::Won->value)
            ->whereNotNull('inquiries.contract_total')
            ->leftJoinSub($paid, 'paid', 'paid.inquiry_id', '=', 'inquiries.id')
            ->joinSub($this->firstOn(InquiryStatusTrack::Sales, SalesStatus::Won->value), 'won', 'won.inquiry_id', '=', 'inquiries.id')
            ->whereRaw("{$balance} > 0")
            ->selectRaw("case when {$age} <= 30 then '0-30' when {$age} <= 60 then '31-60' when {$age} <= 90 then '61-90' else '90+' end as bucket", array_fill(0, 3, $now->utc()->format('Y-m-d H:i:sP')))
            ->selectRaw('count(*) as count, '.sprintf(self::SUM, $balance).' as total')
            ->groupBy('bucket')
            ->toBase()->get()
            ->keyBy('bucket');

        return array_map(fn (string $bucket): array => [
            'bucket' => $bucket,
            'count' => (int) ($rows[$bucket]->count ?? 0),
            'total' => (string) ($rows[$bucket]->total ?? '0.00'),
        ], ['0-30', '31-60', '61-90', '90+']);
    }

    // --- 9. Stones and brands asked for -----------------------------------------------

    public function topStones(?User $marketer, OverviewRange $range, int $limit): array
    {
        return array_values($this->whose('marketer', $marketer)
            ->whereBetween('inquiries.created_at', [$range->from, $range->to])
            ->join('inquiry_stones', 'inquiry_stones.inquiry_id', '=', 'inquiries.id')
            ->join('stones', 'stones.id', '=', 'inquiry_stones.stone_id')
            ->leftJoin('stone_types', 'stone_types.id', '=', 'stones.stone_type_id')
            ->groupBy('stones.id', 'stones.name', 'stone_types.name')
            ->selectRaw('stones.name as name, stone_types.name as type, count(distinct inquiries.id) as inquiries')
            ->selectRaw('count(distinct inquiries.id) filter (where exists (select 1 from inquiry_status_history h where h.inquiry_id = inquiries.id and h.track = ? and h.to_status = ?)) as won', [InquiryStatusTrack::Sales->value, SalesStatus::Won->value])
            ->orderByDesc('inquiries')
            ->orderBy('stones.name')
            ->limit($limit)
            ->toBase()->get()
            ->map(fn ($row): array => ['label' => (string) $row->name, 'detail' => $row->type === null ? null : (string) $row->type, 'inquiries' => (int) $row->inquiries, 'won' => (int) $row->won])
            ->all());
    }

    public function topBrands(?User $marketer, OverviewRange $range, int $limit): array
    {
        return array_values($this->whose('marketer', $marketer)
            ->whereBetween('inquiries.created_at', [$range->from, $range->to])
            ->join('inquiry_brands', 'inquiry_brands.inquiry_id', '=', 'inquiries.id')
            ->join('brands', 'brands.id', '=', 'inquiry_brands.brand_id')
            ->groupBy('brands.id', 'brands.name')
            ->selectRaw('brands.name as name, count(distinct inquiries.id) as inquiries')
            ->selectRaw('count(distinct inquiries.id) filter (where exists (select 1 from inquiry_status_history h where h.inquiry_id = inquiries.id and h.track = ? and h.to_status = ?)) as won', [InquiryStatusTrack::Sales->value, SalesStatus::Won->value])
            ->orderByDesc('inquiries')
            ->orderBy('brands.name')
            ->limit($limit)
            ->toBase()->get()
            ->map(fn ($row): array => ['label' => (string) $row->name, 'detail' => null, 'inquiries' => (int) $row->inquiries, 'won' => (int) $row->won])
            ->all());
    }
}
