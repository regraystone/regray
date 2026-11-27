<?php

namespace App\Repositories\Eloquent;

use App\Enums\InquiryStatusTrack;
use App\Enums\SalesStatus;
use App\Models\Inquiry;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Repositories\Contracts\SalesReportRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Sums are cast to numeric(14,2) so an empty one reads "0.00", like every
 * other amount the page shows.
 */
class SalesReportRepository implements SalesReportRepositoryInterface
{
    /** A sum of money, "0.00" when there is nothing to add. */
    private const SUM = 'coalesce(sum(%s), 0)::numeric(14,2)';

    /** The sales statuses of an inquiry sales has not decided on yet. */
    private const UNDECIDED = [SalesStatus::Negotiation, SalesStatus::SiteVisited, SalesStatus::OnHold, SalesStatus::Quoted, SalesStatus::Revision];

    /** @return array{count: int, total: string} */
    public function contractsWon(User $user, ?CarbonImmutable $from, CarbonImmutable $to): array
    {
        $row = $this->wonIn($user, $from, $to)
            ->selectRaw('count(*) as count, '.sprintf(self::SUM, 'inquiries.contract_total').' as total')
            ->toBase()
            ->first();

        return ['count' => (int) ($row->count ?? 0), 'total' => (string) ($row->total ?? '0.00')];
    }

    public function rejectedCount(User $user, ?CarbonImmutable $from, CarbonImmutable $to): int
    {
        return DB::table('inquiry_status_history')
            ->where('track', InquiryStatusTrack::Sales->value)
            ->where('to_status', SalesStatus::Rejected->value)
            ->whereIn('inquiry_id', $this->visible($user))
            ->when($from !== null, fn (QueryBuilder $query) => $query->where('changed_at', '>=', $from))
            ->where('changed_at', '<=', $to)
            ->distinct()
            ->count('inquiry_id');
    }

    /** @return array{payments: string, paymentsCount: int, refunds: string, refundsCount: int, net: string} */
    public function cash(User $user, ?CarbonImmutable $from, CarbonImmutable $to): array
    {
        $row = $this->transactions($user)
            ->when($from !== null, fn (Builder $query) => $query->where('received_at', '>=', $from))
            ->where('received_at', '<=', $to)
            ->selectRaw(sprintf(self::SUM, "case when kind = 'payment' then amount end").' as payments')
            ->selectRaw("count(*) filter (where kind = 'payment') as payments_count")
            ->selectRaw(sprintf(self::SUM, "case when kind = 'refund' then amount end").' as refunds')
            ->selectRaw("count(*) filter (where kind = 'refund') as refunds_count")
            ->selectRaw(sprintf(self::SUM, "case when kind = 'payment' then amount else -amount end").' as net')
            ->toBase()
            ->first();

        return [
            'payments' => (string) ($row->payments ?? '0.00'),
            'paymentsCount' => (int) ($row->payments_count ?? 0),
            'refunds' => (string) ($row->refunds ?? '0.00'),
            'refundsCount' => (int) ($row->refunds_count ?? 0),
            'net' => (string) ($row->net ?? '0.00'),
        ];
    }

    /** @return array<string, array{payments: string, refunds: string}> */
    public function cashByMonth(User $user, CarbonImmutable $from, string $timezone): array
    {
        $rows = $this->transactions($user)
            ->where('received_at', '>=', $from)
            ->selectRaw("to_char(received_at at time zone ?, 'YYYY-MM') as month", [$timezone])
            ->selectRaw(sprintf(self::SUM, "case when kind = 'payment' then amount end").' as payments')
            ->selectRaw(sprintf(self::SUM, "case when kind = 'refund' then amount end").' as refunds')
            ->groupBy('month')
            ->toBase()
            ->get();

        $months = [];

        foreach ($rows as $row) {
            $months[(string) $row->month] = ['payments' => (string) $row->payments, 'refunds' => (string) $row->refunds];
        }

        return $months;
    }

    /** @return list<array{service: string, count: int, total: string}> */
    public function contractsByService(User $user, ?CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = $this->wonIn($user, $from, $to)
            ->join('services', 'services.id', '=', 'inquiries.service_id')
            // Each application on its own line: "Slab Supply and Installation · Flooring".
            ->leftJoin('services as applications', 'applications.id', '=', 'inquiries.application_id')
            ->groupByRaw("concat_ws(' · ', services.name, applications.name)")
            ->selectRaw("concat_ws(' · ', services.name, applications.name) as service, count(*) as count, ".sprintf(self::SUM, 'inquiries.contract_total').' as total')
            ->orderByDesc('total')
            ->orderBy('service')
            ->toBase()
            ->get();

        $services = [];

        foreach ($rows as $row) {
            $services[] = ['service' => (string) $row->service, 'count' => (int) $row->count, 'total' => (string) $row->total];
        }

        return $services;
    }

    /** @return array<string, array{count: int, total: string}> */
    public function byPaymentStatus(User $user): array
    {
        $rows = Inquiry::query()
            ->visibleTo($user)
            ->whereNotNull('payment_status')
            ->groupBy('payment_status')
            ->selectRaw('payment_status, count(*) as count, '.sprintf(self::SUM, 'contract_total').' as total')
            ->toBase()
            ->get();

        $statuses = [];

        foreach ($rows as $row) {
            $statuses[(string) $row->payment_status] = ['count' => (int) $row->count, 'total' => (string) $row->total];
        }

        return $statuses;
    }

    /** @return array{count: int, total: string} */
    public function openQuotes(User $user): array
    {
        $row = Inquiry::query()
            ->visibleTo($user)
            ->whereNotNull('quoted_total')
            ->whereIn('sales_status', array_map(fn (SalesStatus $status): string => $status->value, self::UNDECIDED))
            ->selectRaw('count(*) as count, '.sprintf(self::SUM, 'quoted_total').' as total')
            ->toBase()
            ->first();

        return ['count' => (int) ($row->count ?? 0), 'total' => (string) ($row->total ?? '0.00')];
    }

    /** @return array{count: int, total: string} */
    public function balanceToCollect(User $user): array
    {
        $row = $this->owing($user)
            ->selectRaw('count(*) as count, '.sprintf(self::SUM, 'inquiries.contract_total - coalesce(paid.paid_net, 0)').' as total')
            ->toBase()
            ->first();

        return ['count' => (int) ($row->count ?? 0), 'total' => (string) ($row->total ?? '0.00')];
    }

    /** @return Collection<int, Inquiry> */
    public function largestBalances(User $user, int $limit): Collection
    {
        return $this->owing($user)
            ->select('inquiries.*')
            ->selectRaw('coalesce(paid.paid_net, 0)::numeric(14,2) as paid_net')
            ->selectRaw('(inquiries.contract_total - coalesce(paid.paid_net, 0))::numeric(14,2) as balance')
            ->selectRaw('paid.last_payment_at')
            ->with(['clientRecord:id,full_name', 'client:id,name', 'service:id,name', 'application:id,name'])
            ->orderByDesc('balance')
            ->orderBy('inquiries.id')
            ->limit($limit)
            ->get();
    }

    /** @return Collection<int, PaymentTransaction> */
    public function recentTransactions(User $user, int $limit): Collection
    {
        return $this->transactions($user)
            ->with([
                'inquiry:id,reference,client_id,client_record_id,service_id',
                'inquiry.clientRecord:id,full_name',
                'inquiry.client:id,name',
                'recordedBy:id,name',
            ])
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * The ids of the inquiries the reader may see.
     *
     * @return Builder<Inquiry>
     */
    private function visible(User $user): Builder
    {
        return Inquiry::query()->visibleTo($user)->select('inquiries.id');
    }

    /** @return Builder<PaymentTransaction> */
    private function transactions(User $user): Builder
    {
        return PaymentTransaction::query()->whereIn('inquiry_id', $this->visible($user));
    }

    /**
     * The visible inquiries sales first marked won in the span: the first
     * `won` of their sales history, so one won, sent back and won again is
     * counted once, when it was first won.
     *
     * @return Builder<Inquiry>
     */
    private function wonIn(User $user, ?CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        $firstWon = DB::table('inquiry_status_history')
            ->where('track', InquiryStatusTrack::Sales->value)
            ->where('to_status', SalesStatus::Won->value)
            ->groupBy('inquiry_id')
            ->select('inquiry_id')
            ->selectRaw('min(changed_at) as won_at');

        return Inquiry::query()
            ->visibleTo($user)
            ->joinSub($firstWon, 'won', 'won.inquiry_id', '=', 'inquiries.id')
            ->when($from !== null, fn (Builder $query) => $query->where('won.won_at', '>=', $from))
            ->where('won.won_at', '<=', $to);
    }

    /**
     * The visible inquiries sales has won that still owe something: their
     * contract total is more than what has been paid and not refunded
     * (`paid.paid_net`, with `paid.last_payment_at`).
     *
     * @return Builder<Inquiry>
     */
    private function owing(User $user): Builder
    {
        $paid = PaymentTransaction::query()
            ->groupBy('inquiry_id')
            ->select('inquiry_id')
            ->selectRaw("sum(case when kind = 'payment' then amount else -amount end) as paid_net")
            ->selectRaw("max(case when kind = 'payment' then received_at end) as last_payment_at");

        return Inquiry::query()
            ->visibleTo($user)
            ->where('inquiries.sales_status', SalesStatus::Won->value)
            ->whereNotNull('inquiries.contract_total')
            ->leftJoinSub($paid, 'paid', 'paid.inquiry_id', '=', 'inquiries.id')
            ->whereRaw('inquiries.contract_total - coalesce(paid.paid_net, 0) > 0');
    }
}
