<?php

namespace App\Services;

use App\Data\StaffVisibility;
use App\Enums\PaymentStatus;
use App\Enums\SalesPeriod;
use App\Models\Inquiry;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Repositories\Contracts\SalesReportRepositoryInterface;
use Carbon\CarbonImmutable;

/**
 * The Sales page: the money side of the inquiries the reader may see. The
 * admin and the owner see every inquiry; accounting the ones assigned to it
 * (Inquiry::visibleTo()), so its figures are its own.
 *
 * Read only, from what the inquiries already hold: the contract total sales
 * sets when it wins one, the quoted total, the payments and refunds
 * accounting records, and the payment status accounting sets.
 *
 * - Contracts won, cash, refunds, the win rate and contract value by service
 *   cover the chosen span (SalesPeriod); a contract is dated by when sales
 *   first marked the inquiry won, cash by when it was received.
 * - What is still to collect and the open quotes are as of now.
 * - Cash by month is always the last 12 months, this one included.
 *
 * Months and spans run on the studio's clock (config('schedule.timezone')).
 */
class SalesReportService
{
    public const BALANCES_SHOWN = 10;

    public const TRANSACTIONS_SHOWN = 8;

    public const MONTHS_SHOWN = 12;

    public function __construct(
        private readonly SalesReportRepositoryInterface $reports,
    ) {}

    /** @return array<string, mixed> */
    public function report(User $user, SalesPeriod $period, CarbonImmutable $now): array
    {
        $timezone = ScheduleService::timezone();
        $now = $now->setTimezone($timezone);
        $from = $period->startsAt($now);

        $won = $this->reports->contractsWon($user, $from, $now);
        $rejected = $this->reports->rejectedCount($user, $from, $now);

        return [
            'period' => $period->value,
            'periods' => array_map(
                fn (SalesPeriod $option): array => ['value' => $option->value, 'label' => $option->label()],
                SalesPeriod::cases(),
            ),
            'scope' => StaffVisibility::for($user)->everything ? 'all' : 'assigned',
            'figures' => [
                'contractsWon' => $won,
                'cash' => $this->reports->cash($user, $from, $now),
                'balance' => $this->reports->balanceToCollect($user),
                'openQuotes' => $this->reports->openQuotes($user),
                'winRate' => [
                    'won' => $won['count'],
                    'rejected' => $rejected,
                    'rate' => $won['count'] + $rejected === 0 ? null : (int) round($won['count'] * 100 / ($won['count'] + $rejected)),
                ],
            ],
            'months' => $this->months($user, $now, $timezone),
            'byService' => $this->reports->contractsByService($user, $from, $now),
            'byPaymentStatus' => $this->paymentStatuses($user),
            'balances' => $this->reports->largestBalances($user, self::BALANCES_SHOWN)
                ->map(fn (Inquiry $inquiry): array => [
                    'reference' => $inquiry->reference,
                    'client' => self::clientName($inquiry),
                    'service' => $inquiry->service->name ?? '—',
                    'contractTotal' => (string) $inquiry->contract_total,
                    'paid' => (string) $inquiry->getAttribute('paid_net'),
                    'balance' => (string) $inquiry->getAttribute('balance'),
                    'paymentStatus' => $inquiry->payment_status?->label(),
                    'lastPaymentAt' => self::isoOrNull($inquiry->getAttribute('last_payment_at')),
                    'url' => route('inquiry.show', $inquiry->reference),
                ])
                ->values()
                ->all(),
            'transactions' => $this->reports->recentTransactions($user, self::TRANSACTIONS_SHOWN)
                ->map(fn (PaymentTransaction $transaction): array => [
                    'id' => $transaction->id,
                    'kind' => $transaction->kind,
                    'amount' => (string) $transaction->amount,
                    'receivedAt' => $transaction->received_at->toIso8601String(),
                    'reference' => $transaction->inquiry->reference,
                    'client' => self::clientName($transaction->inquiry),
                    'recordedBy' => $transaction->recordedBy?->name,
                    'url' => route('inquiry.show', $transaction->inquiry->reference),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * The last 12 months, oldest first, each with its payments and refunds,
     * a month with none at "0.00".
     *
     * @return list<array{month: string, payments: string, refunds: string}>
     */
    private function months(User $user, CarbonImmutable $now, string $timezone): array
    {
        $first = $now->startOfMonth()->subMonths(self::MONTHS_SHOWN - 1);
        $cash = $this->reports->cashByMonth($user, $first, $timezone);
        $months = [];

        for ($month = $first; $month <= $now; $month = $month->addMonth()) {
            $key = $month->format('Y-m');
            $months[] = ['month' => $key, ...($cash[$key] ?? ['payments' => '0.00', 'refunds' => '0.00'])];
        }

        return $months;
    }

    /**
     * Every payment status in its order, each with how many inquiries have it
     * and their contract value, none at 0.
     *
     * @return list<array{status: string, label: string, count: int, total: string}>
     */
    private function paymentStatuses(User $user): array
    {
        $counts = $this->reports->byPaymentStatus($user);

        return array_map(fn (PaymentStatus $status): array => [
            'status' => $status->value,
            'label' => $status->label(),
            ...($counts[$status->value] ?? ['count' => 0, 'total' => '0.00']),
        ], PaymentStatus::cases());
    }

    private static function clientName(Inquiry $inquiry): string
    {
        return $inquiry->clientRecord->full_name ?? $inquiry->client->name ?? '—';
    }

    private static function isoOrNull(mixed $value): ?string
    {
        return $value === null ? null : CarbonImmutable::parse((string) $value)->toIso8601String();
    }
}
