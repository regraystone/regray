<?php

use App\Enums\InquiryStatusTrack;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Enums\SalesStatus;
use App\Models\Inquiry;
use App\Models\InquiryStatusHistory;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The Sales page reads money from what the inquiries hold: contract and quoted
 * totals, and the payments and refunds accounting records. Known inquiries in
 * known months, and the figures each span adds up to.
 */

beforeEach(function (): void {
    seedInquiryCatalog();
    Date::setTestNow(Date::parse('2026-11-23 10:00', 'Asia/Manila'));
    $this->admin = User::factory()->role(Role::Admin)->create();
    $client = inquiryClient();

    // Won this month; 30,000 of 100,000 paid.
    $this->flooring = wonInquiry($client, 'flooring', '100000', '2026-11-05 09:00', PaymentStatus::Partial);
    recordCash($this->flooring, '30000', '2026-11-06 14:00');

    // Won in February, paid in full.
    $this->cladding = wonInquiry($client, 'wall-cladding', '200000', '2026-02-10 09:00', PaymentStatus::FullyPaid);
    recordCash($this->cladding, '200000', '2026-02-12 10:00');

    // Won last December: 20,000 paid then, 5,000 of it refunded this month.
    $this->staircase = wonInquiry($client, 'staircase', '50000', '2025-12-01 09:00', PaymentStatus::PartiallyRefunded);
    recordCash($this->staircase, '20000', '2025-12-02 09:00');
    recordCash($this->staircase, '5000', '2026-11-10 09:00', 'refund');

    // Rejected this month, and one quote still open.
    $rejected = makeInquiry($client, ['service' => 'flooring'], ['sales_status' => SalesStatus::Rejected]);
    $rejected->forceFill(['quoted_total' => '40000'])->save();
    salesHistory($rejected, SalesStatus::Rejected, '2026-11-15 09:00');
    makeInquiry($client, ['service' => 'flooring'], ['sales_status' => SalesStatus::Quoted])->forceFill(['quoted_total' => '80000'])->save();
});

function wonInquiry(User $client, string $service, string $contract, string $wonAt, PaymentStatus $payment): Inquiry
{
    $inquiry = makeInquiry($client, ['service' => $service], ['sales_status' => SalesStatus::Won, 'payment_status' => $payment]);
    $inquiry->forceFill(['quoted_total' => $contract, 'contract_total' => $contract])->save();
    salesHistory($inquiry, SalesStatus::Won, $wonAt);

    return $inquiry;
}

function salesHistory(Inquiry $inquiry, SalesStatus $to, string $at): void
{
    InquiryStatusHistory::query()->create([
        'inquiry_id' => $inquiry->id,
        'track' => InquiryStatusTrack::Sales,
        'to_status' => $to->value,
        'changed_at' => Date::parse($at, 'Asia/Manila'),
    ]);
}

function recordCash(Inquiry $inquiry, string $amount, string $at, string $kind = 'payment'): void
{
    PaymentTransaction::query()->create([
        'inquiry_id' => $inquiry->id,
        'kind' => $kind,
        'amount' => $amount,
        'received_at' => Date::parse($at, 'Asia/Manila'),
        'created_at' => now(),
    ]);
}

it('adds up each span from the inquiries\' own amounts', function (string $period, array $won, array $cash, array $winRate): void {
    $this->actingAs($this->admin)
        ->get(route('sales.index', ['period' => $period]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('sales')
            ->where('period', $period)
            ->where('scope', 'all')
            ->where('figures.contractsWon', $won)
            ->where('figures.cash', $cash)
            ->where('figures.winRate', $winRate)
            // As of today, whatever the span.
            ->where('figures.balance', ['count' => 2, 'total' => '105000.00'])
            ->where('figures.openQuotes', ['count' => 1, 'total' => '80000.00']));
})->with([
    'this month' => ['this_month', ['count' => 1, 'total' => '100000.00'], ['payments' => '30000.00', 'paymentsCount' => 1, 'refunds' => '5000.00', 'refundsCount' => 1, 'net' => '25000.00'], ['won' => 1, 'rejected' => 1, 'rate' => 50]],
    'this year' => ['this_year', ['count' => 2, 'total' => '300000.00'], ['payments' => '230000.00', 'paymentsCount' => 2, 'refunds' => '5000.00', 'refundsCount' => 1, 'net' => '225000.00'], ['won' => 2, 'rejected' => 1, 'rate' => 67]],
    'all time' => ['all', ['count' => 3, 'total' => '350000.00'], ['payments' => '250000.00', 'paymentsCount' => 3, 'refunds' => '5000.00', 'refundsCount' => 1, 'net' => '245000.00'], ['won' => 3, 'rejected' => 1, 'rate' => 75]],
]);

it('shows the last 12 months, the owing inquiries largest first, and contract value by service', function (): void {
    $page = $this->actingAs($this->admin)->get(route('sales.index'))->viewData('page')['props'];
    $months = collect($page['months'])->keyBy('month');

    expect($page['period'])->toBe('this_year')
        ->and($months->keys()->first())->toBe('2025-12')
        ->and($months->keys()->last())->toBe('2026-11')
        ->and($months)->toHaveCount(12)
        ->and($months['2025-12'])->toBe(['month' => '2025-12', 'payments' => '20000.00', 'refunds' => '0.00'])
        ->and($months['2026-02']['payments'])->toBe('200000.00')
        ->and($months['2026-11'])->toBe(['month' => '2026-11', 'payments' => '30000.00', 'refunds' => '5000.00'])
        ->and($months['2026-05'])->toBe(['month' => '2026-05', 'payments' => '0.00', 'refunds' => '0.00'])
        ->and(collect($page['balances'])->map(fn (array $row): array => [$row['reference'], $row['paid'], $row['balance']])->all())
        ->toBe([[$this->flooring->reference, '30000.00', '70000.00'], [$this->staircase->reference, '15000.00', '35000.00']])
        ->and($page['balances'][0]['url'])->toBe(route('inquiry.show', $this->flooring->reference))
        ->and($page['byService'])->toBe([
            ['service' => 'Wall Cladding', 'count' => 1, 'total' => '200000.00'],
            ['service' => 'Flooring', 'count' => 1, 'total' => '100000.00'],
        ])
        ->and(collect($page['byPaymentStatus'])->firstWhere('status', 'partial'))
        ->toBe(['status' => 'partial', 'label' => 'Partially paid', 'count' => 1, 'total' => '100000.00'])
        ->and(collect($page['transactions'])->pluck('kind')->first())->toBe('refund');
});

it('counts cash in the month it was received on the studio\'s clock', function (): void {
    // 17:00 UTC on 31 October is 1 a.m. on 1 November in Manila.
    PaymentTransaction::query()->create([
        'inquiry_id' => $this->flooring->id,
        'kind' => 'payment',
        'amount' => '1000',
        'received_at' => Date::parse('2026-10-31 17:00', 'UTC'),
        'created_at' => now(),
    ]);

    $months = collect($this->actingAs($this->admin)->get(route('sales.index'))->viewData('page')['props']['months'])->keyBy('month');

    expect($months['2026-11']['payments'])->toBe('31000.00')
        ->and($months['2026-10']['payments'])->toBe('0.00');
});

it('gives accounting the figures of the inquiries assigned to it alone', function (): void {
    $accounting = User::factory()->role(Role::Accounting)->create();
    assignInquiry($this->flooring, $accounting);

    $this->actingAs($accounting)
        ->get(route('sales.index', ['period' => 'all']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('scope', 'assigned')
            ->where('figures.contractsWon', ['count' => 1, 'total' => '100000.00'])
            ->where('figures.cash.net', '30000.00')
            ->where('figures.balance', ['count' => 1, 'total' => '70000.00'])
            ->where('figures.openQuotes', ['count' => 0, 'total' => '0.00'])
            ->has('balances', 1)
            ->has('transactions', 1));
});

it('reads an unknown period as this year, and is for the admin, the owner and accounting', function (): void {
    $this->actingAs($this->admin)
        ->get(route('sales.index', ['period' => 'forever']))
        ->assertInertia(fn (Assert $page) => $page->where('period', 'this_year'));

    $this->actingAs(User::factory()->role(Role::Owner)->create())->get(route('sales.index'))->assertOk();

    foreach ([Role::Sales, Role::Marketing, Role::Client] as $role) {
        $this->actingAs(User::factory()->role($role)->create())->get(route('sales.index'))->assertForbidden();
    }
});
