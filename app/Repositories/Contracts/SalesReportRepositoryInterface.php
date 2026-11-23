<?php

namespace App\Repositories\Contracts;

use App\Models\Inquiry;
use App\Models\PaymentTransaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * The Sales page's figures, each over the inquiries the reader may see
 * (Inquiry::visibleTo()). Amounts come back as the database's decimal
 * strings ("1250.00"), never floats.
 */
interface SalesReportRepositoryInterface
{
    /**
     * Inquiries first marked won by sales in the span, and their contract
     * value.
     *
     * @return array{count: int, total: string}
     */
    public function contractsWon(User $user, ?CarbonImmutable $from, CarbonImmutable $to): array;

    /**
     * Inquiries sales rejected in the span, for the win rate.
     */
    public function rejectedCount(User $user, ?CarbonImmutable $from, CarbonImmutable $to): int;

    /**
     * Payments and refunds received in the span, and payments less refunds.
     *
     * @return array{payments: string, paymentsCount: int, refunds: string, refundsCount: int, net: string}
     */
    public function cash(User $user, ?CarbonImmutable $from, CarbonImmutable $to): array;

    /**
     * Payments and refunds by month in the studio's timezone, from `$from`.
     *
     * @return array<string, array{payments: string, refunds: string}> keyed "2026-10"
     */
    public function cashByMonth(User $user, CarbonImmutable $from, string $timezone): array;

    /**
     * Contract value won in the span, by service, largest first.
     *
     * @return list<array{service: string, count: int, total: string}>
     */
    public function contractsByService(User $user, ?CarbonImmutable $from, CarbonImmutable $to): array;

    /**
     * Inquiries with a payment status now, by that status, with their
     * contract value.
     *
     * @return array<string, array{count: int, total: string}> keyed by PaymentStatus value
     */
    public function byPaymentStatus(User $user): array;

    /**
     * Quoted inquiries sales has not decided on yet.
     *
     * @return array{count: int, total: string}
     */
    public function openQuotes(User $user): array;

    /**
     * What won inquiries still owe in all: their contract totals less what
     * has been paid and not refunded.
     *
     * @return array{count: int, total: string}
     */
    public function balanceToCollect(User $user): array;

    /**
     * The won inquiries that still owe the most, with `paid_net`, `balance`
     * and `last_payment_at` selected, and their client and service loaded.
     *
     * @return Collection<int, Inquiry>
     */
    public function largestBalances(User $user, int $limit): Collection;

    /**
     * The latest payments and refunds, with their inquiry, its client and who
     * recorded them loaded.
     *
     * @return Collection<int, PaymentTransaction>
     */
    public function recentTransactions(User $user, int $limit): Collection;
}
