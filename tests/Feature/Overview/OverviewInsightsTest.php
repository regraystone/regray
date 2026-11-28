<?php

use App\Enums\BookedByType;
use App\Enums\InquiryStatusTrack;
use App\Enums\LeadStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProcessStatus;
use App\Enums\Role;
use App\Enums\SalesStatus;
use App\Enums\ScheduleKind;
use App\Enums\ScheduleStatus;
use App\Models\Inquiry;
use App\Models\InquiryStatusHistory;
use App\Models\PaymentTransaction;
use App\Models\Schedule;
use App\Models\Stone;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/*
 * The Overview's insights, over 1–23 November 2026 (today the 23rd), from a
 * small world of known inquiries:
 *
 * - `sold`: in on 2 Nov, won on the 5th (100,000, one quote), paid in full on
 *   the 8th, completed on the 20th, needed by the 21st: on time.
 * - `quoting`: in on 3 Nov, opened by marketing, first quoted on the 5th.
 * - `waiting`: in on 4 Nov as a walk-in, nothing since.
 * - `late`: in in October, won on 1 Nov (45,000 after three quotes of 50,000),
 *   completed on the 10th, needed by the 8th: two days late; nothing paid.
 * - `lost`: in in October, flooring, rejected on 12 Nov.
 * - `old`: won in August (30,000), 10,000 paid: owed for over 90 days.
 *
 * And showroom visits: one attended on 2 Nov (its client then won on the 5th),
 * one expired, one cancelled. The target dates of `sold` and `late` are not
 * visits, nor is an installation booked for `sold`.
 */

beforeEach(function (): void {
    seedInquiryCatalog();
    Date::setTestNow(Date::parse('2026-11-23 10:00', 'Asia/Manila'));

    $this->owner = User::factory()->role(Role::Owner)->create();
    $this->marketer = User::factory()->role(Role::Marketing)->create();
    $this->rep = User::factory()->role(Role::Sales)->create();
    $client = $this->client = inquiryClient();
    $at = fn (string $when): CarbonImmutable => CarbonImmutable::parse($when, 'Asia/Manila');

    $sold = insightDeal($client, ['created_at' => $at('2026-11-02 10:00'), 'sales_status' => SalesStatus::Won, 'process_status' => ProcessStatus::Completed, 'completed_at' => $at('2026-11-20 10:00')], ['contract_total' => '100000', 'quoted_total' => '100000', 'quote_revision' => 1]);
    insightMove($sold, InquiryStatusTrack::Sales, SalesStatus::Won->value, $at('2026-11-05 10:00'));
    insightMove($sold, InquiryStatusTrack::Payment, PaymentStatus::FullyPaid->value, $at('2026-11-08 10:00'));
    insightCash($sold, '100000', $at('2026-11-08 10:00'));
    insightVisit($client, ScheduleStatus::Confirmed, $at('2026-11-21 09:00'), $sold);
    DB::table('inquiry_brands')->insert(['inquiry_id' => $sold->id, 'brand_id' => Stone::query()->orderBy('id')->value('brand_id')]);
    assignInquiry($sold, $this->rep);

    $quoting = insightDeal($client, ['created_at' => $at('2026-11-03 10:00'), 'created_by' => $this->marketer, 'sales_status' => SalesStatus::Quoted], ['quote_revision' => 1, 'quoted_total' => '60000']);
    insightMove($quoting, InquiryStatusTrack::Sales, SalesStatus::Quoted->value, $at('2026-11-05 10:00'));

    insightDeal($client, ['created_at' => $at('2026-11-04 10:00')], ['owner_user_id' => $this->rep->id, 'client_id' => null, 'client_record_id' => walkInClient()->id]);

    $late = insightDeal($client, ['created_at' => $at('2026-10-10 10:00'), 'sales_status' => SalesStatus::Won, 'process_status' => ProcessStatus::Completed, 'completed_at' => $at('2026-11-10 10:00')], ['contract_total' => '45000', 'quoted_total' => '50000', 'quote_revision' => 3]);
    insightMove($late, InquiryStatusTrack::Sales, SalesStatus::Won->value, $at('2026-11-01 10:00'));
    insightVisit($client, ScheduleStatus::Served, $at('2026-11-08 09:00'), $late);

    $lost = insightDeal($client, ['created_at' => $at('2026-10-11 10:00'), 'sales_status' => SalesStatus::Rejected], [], 'flooring');
    insightMove($lost, InquiryStatusTrack::Sales, SalesStatus::Rejected->value, $at('2026-11-12 10:00'));

    $old = insightDeal($client, ['created_at' => $at('2026-07-01 10:00'), 'sales_status' => SalesStatus::Won, 'payment_status' => PaymentStatus::Partial], ['contract_total' => '30000', 'quoted_total' => '30000', 'quote_revision' => 1]);
    insightMove($old, InquiryStatusTrack::Sales, SalesStatus::Won->value, $at('2026-08-01 10:00'));
    insightCash($old, '10000', $at('2026-08-02 10:00'));

    insightVisit($client, ScheduleStatus::Served, $at('2026-11-02 09:00'));
    insightVisit($client, ScheduleStatus::Expired, $at('2026-11-06 09:00'));
    insightVisit($client, ScheduleStatus::Cancelled, $at('2026-11-07 09:00'));
    (new Schedule)->forceFill(['order_id' => $sold->id, 'kind' => ScheduleKind::Installation, 'client_id' => $client->id, 'booked_by' => $client->id, 'booked_by_type' => BookedByType::Client, 'title' => 'Installation', 'arrival_at' => $at('2026-11-15 09:00')->utc(), 'status' => ScheduleStatus::Served])->save();
});

function insightDeal(User $client, array $attributes, array $columns = [], string $service = 'slab-supply-only'): Inquiry
{
    $inquiry = makeInquiry($client, ['service' => $service], $attributes);
    $inquiry->forceFill($columns)->save();

    return $inquiry;
}

function insightMove(Inquiry $inquiry, InquiryStatusTrack $track, string $to, CarbonImmutable $at): void
{
    InquiryStatusHistory::query()->create(['inquiry_id' => $inquiry->id, 'track' => $track, 'to_status' => $to, 'changed_at' => $at]);
}

function insightCash(Inquiry $inquiry, string $amount, CarbonImmutable $at): void
{
    PaymentTransaction::query()->create(['inquiry_id' => $inquiry->id, 'kind' => 'payment', 'amount' => $amount, 'received_at' => $at, 'created_at' => $at]);
}

function insightVisit(User $client, ScheduleStatus $status, CarbonImmutable $at, ?Inquiry $inquiry = null): void
{
    (new Schedule)->forceFill([
        'client_id' => $inquiry === null ? $client->id : null,
        'inquiry_id' => $inquiry?->id,
        // An inquiry's own target date has no kind; a booking is a showroom visit.
        'kind' => $inquiry === null ? ScheduleKind::ShowroomVisit : null,
        'booked_by' => $client->id,
        'booked_by_type' => BookedByType::Client,
        'title' => 'Showroom visit',
        'arrival_at' => $at->utc(),
        'status' => $status,
        'cancelled_at' => $status === ScheduleStatus::Cancelled ? $at : null,
        'cancel_reason' => $status === ScheduleStatus::Cancelled ? 'Plans changed' : null,
    ])->save();
}

/** @return array<string, array<string, mixed>> The section's insights by kind. */
function insightsOf(User $user, string $kind): array
{
    $props = test()->actingAs($user)->get(route('overview.index'))->assertOk()->viewData('page')['props'];

    return collect(collect($props['sections'])->firstWhere('kind', $kind)['insights'])->keyBy('kind')->all();
}

/** @return array<string, mixed> Figures by key. */
function insightFigures(array $insight): array
{
    return collect($insight['figures'])->keyBy('key')->map->value->all();
}

it('follows the inquiries received within the dates through each step', function (): void {
    $steps = collect(insightsOf($this->owner, 'team')['funnel']['steps'])->pluck('count', 'key')->all();

    expect($steps)->toBe(['received' => 3, 'passed' => 2, 'quoted' => 2, 'won' => 1, 'paid' => 1, 'completed' => 1]);
});

it('counts jobs delivered on or before the day they were needed', function (): void {
    expect(insightFigures(insightsOf($this->owner, 'team')['onTime']))->toBe(['rate' => 50, 'completed' => 2, 'late' => 1, 'daysLate' => 2.0]);
});

it('times the first quote, the job and the payment', function (): void {
    expect(insightFigures(insightsOf($this->owner, 'team')['speed']))->toBe(['quote' => 2.0, 'completed' => 12.0, 'paid' => 3.0]);
});

it('splits won and lost by service, largest value first', function (): void {
    $rows = insightsOf($this->owner, 'team')['byService']['rows'];

    expect($rows[0])->toMatchArray(['service' => 'Slab Supply Only', 'won' => 2, 'value' => '145000.00', 'lost' => 0, 'closing' => 100])
        ->and($rows[1])->toMatchArray(['won' => 0, 'lost' => 1, 'closing' => 0])
        ->and($rows[1]['service'])->toContain('Flooring');
});

it('says how inquiries came in and from where', function (): void {
    $sources = insightsOf($this->owner, 'team')['sources'];

    expect(collect($sources['channels'])->pluck('count', 'key')->all())->toBe(['online' => 1, 'staff' => 1, 'walkIn' => 1])
        ->and($sources['places'])->toHaveCount(1)
        ->and($sources['places'][0]['count'])->toBe(3);
});

it('reads the quotes behind the deals won', function (): void {
    expect(insightFigures(insightsOf($this->owner, 'team')['quotes']))->toBe(['averageQuotes' => 2.0, 'revised' => 1, 'contractVsQuote' => -5.0]);
});

it('counts how visits went and which led to a win', function (): void {
    expect(insightFigures(insightsOf($this->owner, 'team')['visits']))->toBe(['attended' => 1, 'rate' => 33, 'missed' => 2, 'ledToWin' => 1]);
});

it('ages what is still owed by how long ago it was won', function (): void {
    $buckets = collect(insightsOf($this->owner, 'team')['ageing']['buckets'])->keyBy('bucket');

    expect($buckets['0-30'])->toMatchArray(['count' => 1, 'total' => '45000.00'])
        ->and($buckets['90+'])->toMatchArray(['count' => 1, 'total' => '20000.00'])
        ->and($buckets['31-60']['count'])->toBe(0);
});

it('lists the stones and brands asked for, and how many of those inquiries were won', function (): void {
    $popular = insightsOf($this->owner, 'team')['popular'];

    expect($popular['stones'][0])->toMatchArray(['inquiries' => 3, 'won' => 1])
        ->and($popular['brands'][0])->toMatchArray(['inquiries' => 1, 'won' => 1]);
});

it('gives each role the insights for its work', function (Role $role, array $kinds): void {
    $user = User::factory()->role($role)->create();

    expect(array_keys(insightsOf($user, match ($role) {
        Role::Owner => 'team',
        default => $role->value,
    })))->toBe($kinds);
})->with([
    'owner' => [Role::Owner, ['funnel', 'onTime', 'speed', 'ageing', 'byService', 'quotes', 'visits', 'sources', 'popular']],
    'sales' => [Role::Sales, ['funnel', 'speed', 'quotes', 'visits', 'byService']],
    'marketing' => [Role::Marketing, ['funnel', 'sources', 'visits', 'popular']],
    'accounting' => [Role::Accounting, ['ageing', 'speed']],
    'logistics' => [Role::Logistics, ['onTime', 'speed']],
]);

it('starts a sales rep\'s funnel at their own inquiries, with no marketing step', function (): void {
    $steps = collect(insightsOf($this->rep, 'sales')['funnel']['steps'])->pluck('count', 'key')->all();

    // `sold` (assigned) and `waiting` (their walk-in), both in within the dates.
    expect($steps)->toBe(['received' => 2, 'quoted' => 1, 'won' => 1, 'paid' => 1, 'completed' => 1]);
});

it('counts a marketing user\'s own leads only', function (): void {
    $lead = makeLead(inquiryClient(), $this->marketer);
    makeInquiry($lead->client, [], ['lead' => $lead, 'marketing_status' => LeadStatus::Won, 'created_at' => Date::parse('2026-11-10 10:00')]);

    $steps = collect(insightsOf($this->marketer, 'marketing')['funnel']['steps'])->pluck('count', 'key')->all();

    expect($steps)->toBe(['received' => 1, 'passed' => 1, 'quoted' => 0, 'won' => 0, 'paid' => 0, 'completed' => 0]);
});
