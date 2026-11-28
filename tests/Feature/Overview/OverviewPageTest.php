<?php

use App\Enums\InquiryStatusTrack;
use App\Enums\LeadStatus;
use App\Enums\Role;
use App\Enums\SalesStatus;
use App\Models\Inquiry;
use App\Models\InquiryStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The Overview: each staff role's own summary over From–To. Known inquiries on
 * known days (the studio's clock), and what each person is credited with: a
 * sales rep the inquiries assigned to them, whoever clicked; a marketing user
 * the inquiries of the leads they hold.
 *
 * Today is 23 November 2026, so the default dates are 1–23 November and the
 * days before them 9–31 October.
 */

beforeEach(function (): void {
    seedInquiryCatalog();
    Date::setTestNow(Date::parse('2026-11-23 10:00', 'Asia/Manila'));

    $this->admin = User::factory()->role(Role::Admin)->create(['name' => 'Admin']);
    $this->owner = User::factory()->role(Role::Owner)->create(['name' => 'Owner']);
    $this->ana = User::factory()->role(Role::Sales)->create(['name' => 'Ana']);
    $this->ben = User::factory()->role(Role::Sales)->create(['name' => 'Ben']);
    $this->client = inquiryClient();
});

/** A sales history row: `$by` moved it to `$to` at `$at`, Manila time. */
function overviewSalesMove(Inquiry $inquiry, SalesStatus $to, string $at, ?User $by = null): void
{
    InquiryStatusHistory::query()->create([
        'inquiry_id' => $inquiry->id,
        'track' => InquiryStatusTrack::Sales,
        'to_status' => $to->value,
        'changed_by' => $by?->id,
        'changed_at' => Date::parse($at, 'Asia/Manila'),
    ]);
}

/** An inquiry assigned to these reps, sales at `$status`, with these totals. */
function overviewDeal(User $client, array $reps, SalesStatus $status, ?string $contract = null, ?string $quoted = null): Inquiry
{
    $inquiry = makeInquiry($client, [], ['sales_status' => $status]);
    $inquiry->forceFill(['contract_total' => $contract, 'quoted_total' => $quoted ?? $contract])->save();
    assignInquiry($inquiry, ...$reps);

    return $inquiry;
}

/** The sales world: Ana's and Ben's deals in November and before. */
function overviewSalesWorld(object $test): void
{
    // Ana's: two won this month (one valued by its quote, not yet billed), one lost.
    overviewSalesMove(overviewDeal($test->client, [$test->ana], SalesStatus::Won, '100000'), SalesStatus::Won, '2026-11-05 09:00', $test->ana);
    overviewSalesMove(overviewDeal($test->client, [$test->ana], SalesStatus::Won, null, '50000'), SalesStatus::Won, '2026-11-10 09:00', $test->ana);
    overviewSalesMove(overviewDeal($test->client, [$test->ana], SalesStatus::Rejected, null, '9000'), SalesStatus::Rejected, '2026-11-12 09:00', $test->ana);
    // Won in October: the days before.
    overviewSalesMove(overviewDeal($test->client, [$test->ana], SalesStatus::Won, '30000'), SalesStatus::Won, '2026-10-20 09:00', $test->ana);
    // On both: Ben clicked Won, and it counts for each of them.
    overviewSalesMove(overviewDeal($test->client, [$test->ana, $test->ben], SalesStatus::Won, '20000'), SalesStatus::Won, '2026-11-15 09:00', $test->ben);
    // Ben's alone: Ana clicked Won while covering, and it is Ben's.
    overviewSalesMove(overviewDeal($test->client, [$test->ben], SalesStatus::Won, '40000'), SalesStatus::Won, '2026-11-06 09:00', $test->ana);
}

/** @return array<string, mixed> */
function overviewSection(object $page, string $kind): array
{
    return collect($page->viewData('page')['props']['sections'])->firstWhere('kind', $kind);
}

/** @return array<string, mixed> A section's figures by key. */
function overviewFigures(array $section): array
{
    return collect($section['figures'])->keyBy('key')->all();
}

it('covers the first of this month to today by default, against as many days before', function (): void {
    $this->actingAs($this->ana)->get(route('overview.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('overview')
            ->where('range', ['from' => '2026-11-01', 'to' => '2026-11-23', 'days' => 23, 'bucket' => 'day', 'previousFrom' => '2026-10-09', 'previousTo' => '2026-10-31'])
            ->where('today', '2026-11-23')
            ->where('showsDates', true)
            ->where('viewAs', null));
});

it('keeps To at today at the latest and From at To, and ignores a date that is not one', function (array $query, array $expected): void {
    $this->actingAs($this->ana)->get(route('overview.index', $query))
        ->assertInertia(fn (Assert $page) => $page
            ->where('range.from', $expected[0])
            ->where('range.to', $expected[1]));
})->with([
    'a chosen span' => [['from' => '2026-10-01', 'to' => '2026-10-15'], ['2026-10-01', '2026-10-15']],
    'To in the future' => [['from' => '2026-11-10', 'to' => '2026-12-31'], ['2026-11-10', '2026-11-23']],
    'From after To' => [['from' => '2026-11-20', 'to' => '2026-11-05'], ['2026-11-05', '2026-11-05']],
    'not a date' => [['from' => '2026-02-31', 'to' => 'soon'], ['2026-11-01', '2026-11-23']],
]);

it('groups the trend by day up to a month, by week up to six months, then by month', function (string $from, string $bucket): void {
    $this->actingAs($this->ana)->get(route('overview.index', ['from' => $from, 'to' => '2026-11-23']))
        ->assertInertia(fn (Assert $page) => $page->where('range.bucket', $bucket));
})->with([
    ['2026-10-24', 'day'],
    ['2026-09-01', 'week'],
    ['2026-01-01', 'month'],
]);

it('credits a sales rep with the inquiries assigned to them, whoever clicked won', function (): void {
    overviewSalesWorld($this);

    $sales = overviewSection($this->actingAs($this->ana)->get(route('overview.index')), 'sales');
    $figures = overviewFigures($sales);

    expect($figures['won']['value'])->toBe(3)
        ->and($figures['won']['previous'])->toBe(1)
        // Billed contract totals, as on the Sales page; the deal not yet billed is beside them.
        ->and($figures['value']['value'])->toBe('120000.00')
        ->and($figures['value']['note'])->toBe('+ ₱50,000 quoted, not billed yet')
        ->and($figures['value']['previous'])->toBe('30000.00')
        ->and($figures['lost']['value'])->toBe(1)
        // won ÷ (won + lost)
        ->and($figures['closing']['value'])->toBe(75)
        ->and($figures['closing']['previous'])->toBe(100)
        // Every win, billed or not.
        ->and($figures['averageDeal']['value'])->toBe('56666.67')
        ->and(collect($sales['figures'])->every(fn (array $figure): bool => $figure['dated']))->toBeTrue()
        ->and(collect($sales['trend']['points'])->firstWhere('start', '2026-11-05'))->toMatchArray(['won' => 1, 'lost' => 0])
        ->and(collect($sales['trend']['points'])->firstWhere('start', '2026-11-12'))->toMatchArray(['won' => 0, 'lost' => 1]);

    $ben = overviewFigures(overviewSection($this->actingAs($this->ben)->get(route('overview.index')), 'sales'));

    expect($ben['won']['value'])->toBe(2)
        ->and($ben['value']['value'])->toBe('60000.00')
        ->and($ben['value']['note'])->toBeNull()
        ->and($ben['closing']['value'])->toBe(100);
});

it('counts only the chosen dates, and nothing decided shows as no closing %', function (): void {
    overviewSalesWorld($this);

    $figures = overviewFigures(overviewSection($this->actingAs($this->ana)->get(route('overview.index', ['from' => '2026-11-23', 'to' => '2026-11-23'])), 'sales'));

    expect($figures['won']['value'])->toBe(0)
        ->and($figures['closing']['value'])->toBeNull()
        ->and($figures['averageDeal']['value'])->toBeNull();
});

it('lists a sales rep\'s pipeline now, and what is waiting on them', function (): void {
    $stale = overviewDeal($this->client, [$this->ana], SalesStatus::Quoted, null, '80000');
    $stale->forceFill(['sales_status_changed_at' => Date::parse('2026-11-10 09:00')])->save();
    $fresh = overviewDeal($this->client, [$this->ana], SalesStatus::Quoted, null, '20000');
    $fresh->forceFill(['sales_status_changed_at' => Date::parse('2026-11-22 09:00')])->save();
    overviewDeal($this->client, [$this->ana], SalesStatus::Negotiation);
    // Won with no job order yet.
    overviewDeal($this->client, [$this->ana], SalesStatus::Won, '10000');

    $sales = overviewSection($this->actingAs($this->ana)->get(route('overview.index')), 'sales');
    $pipeline = collect($sales['pipeline'])->keyBy('status');
    $tasks = collect($sales['tasks'])->keyBy('key');

    expect($pipeline['quoted'])->toMatchArray(['count' => 2, 'total' => '100000.00'])
        ->and($pipeline['negotiation']['count'])->toBe(1)
        ->and($tasks['staleQuotes']['count'])->toBe(1)
        ->and($tasks['staleQuotes']['items'][0]['title'])->toStartWith($stale->reference)
        ->and($tasks['jobOrder']['count'])->toBe(1)
        // A to-do with nothing in it is left out.
        ->and($tasks->has('confirm'))->toBeFalse();
});

it('credits a marketing user with the inquiries of the leads they hold', function (): void {
    $mia = User::factory()->role(Role::Marketing)->create(['name' => 'Mia']);
    $max = User::factory()->role(Role::Marketing)->create(['name' => 'Max']);
    $newLead = makeLead($this->client, $mia);
    $newLead->forceFill(['created_at' => Date::parse('2026-11-03 09:00')])->save();
    $oldLead = makeLead(inquiryClient(), $mia);
    $oldLead->forceFill(['created_at' => Date::parse('2026-10-15 09:00')])->save();
    $otherLead = makeLead(inquiryClient(), $max);

    // Won on the 4th, then won by sales: sold after handover.
    $sold = makeInquiry($this->client, [], ['lead' => $newLead, 'marketing_status' => LeadStatus::Won, 'sales_status' => SalesStatus::Won, 'created_at' => Date::parse('2026-11-03 10:00')]);
    $sold->forceFill(['marketing_status_changed_at' => Date::parse('2026-11-04 10:00'), 'contract_total' => '80000'])->save();
    overviewSalesMove($sold, SalesStatus::Won, '2026-11-10 09:00');
    // Lost a day after it came in.
    makeInquiry($this->client, [], ['lead' => $newLead, 'marketing_status' => LeadStatus::Lost, 'created_at' => Date::parse('2026-11-05 10:00')])
        ->forceFill(['marketing_status_changed_at' => Date::parse('2026-11-06 10:00')])->save();
    // Still waiting for Mia.
    makeInquiry($oldLead->client, [], ['lead' => $oldLead, 'created_at' => Date::parse('2026-11-20 10:00')]);
    // Max's.
    makeInquiry($otherLead->client, [], ['lead' => $otherLead, 'marketing_status' => LeadStatus::Won]);

    $marketing = overviewSection($this->actingAs($mia)->get(route('overview.index')), 'marketing');
    $figures = overviewFigures($marketing);

    expect($figures['newLeads']['value'])->toBe(1)
        ->and($figures['received']['value'])->toBe(3)
        ->and($figures['won']['value'])->toBe(1)
        ->and($figures['lost']['value'])->toBe(1)
        ->and($figures['closing']['value'])->toBe(50)
        ->and($figures['sold']['value'])->toBe(1)
        ->and($figures['sold']['note'])->toContain('80,000.00')
        ->and($figures['hoursToDecide']['value'])->toBe(24.0)
        ->and(collect($marketing['tasks'])->firstWhere('key', 'idle')['count'])->toBe(1)
        ->and(collect($marketing['trend']['points'])->firstWhere('start', '2026-11-03')['newLeads'])->toBe(1);
});

it('gives the owner the studio\'s totals, counting a shared win once, and each person\'s row', function (): void {
    overviewSalesWorld($this);

    $page = $this->actingAs($this->owner)->get(route('overview.index'));
    $team = overviewSection($page, 'team');
    $figures = overviewFigures($team);

    expect(collect($page->viewData('page')['props']['sections'])->pluck('kind')->all())->toBe(['team'])
        ->and($figures['won']['value'])->toBe(4)
        ->and($figures['value']['value'])->toBe('160000.00')
        ->and($figures['value']['note'])->toBe('+ ₱50,000 quoted, not billed yet')
        // As of today, whatever the dates.
        ->and($figures['toCollect']['dated'])->toBeFalse()
        ->and($figures['won']['dated'])->toBeTrue()
        ->and($figures['closing']['value'])->toBe(80)
        ->and(collect($team['salesTeam'])->keyBy('name')->map->won->all())->toBe(['Ana' => 3, 'Ben' => 2]);
});

it('shows each role its own sections, and technical no dates', function (Role $role, array $kinds, bool $dates): void {
    $user = User::factory()->role($role)->create();

    $this->actingAs($user)->get(route('overview.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('sections', fn ($sections): bool => collect($sections)->pluck('kind')->all() === $kinds)
            ->where('showsDates', $dates));
})->with([
    'admin' => [Role::Admin, ['team', 'admin'], true],
    'owner' => [Role::Owner, ['team'], true],
    'marketing' => [Role::Marketing, ['marketing'], true],
    'sales' => [Role::Sales, ['sales'], true],
    'accounting' => [Role::Accounting, ['accounting'], true],
    'logistics' => [Role::Logistics, ['logistics'], true],
    'technical' => [Role::Technical, ['technical'], false],
]);

it('lets the admin, and only the admin, view a staff member\'s overview', function (): void {
    overviewSalesWorld($this);

    $this->actingAs($this->admin)->get(route('overview.index', ['as' => $this->ana->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('viewingAs', $this->ana->id)
            ->where('subject.name', 'Ana')
            ->where('sections.0.kind', 'sales')
            // Worded about Ana, not the admin.
            ->where('sections.0.title', "Ana's sales")
            ->where('sections.0.note', 'The inquiries assigned to them and the walk-ins they own.')
            ->where('sections.0.figures.0.value', 3)
            ->where('viewAs.options', fn ($options): bool => collect($options)->pluck('name')->contains('Ana')));

    $this->actingAs($this->ben)->get(route('overview.index', ['as' => $this->ana->id]))->assertForbidden();
    $this->actingAs($this->admin)->get(route('overview.index', ['as' => $this->client->id]))->assertNotFound();
    $this->actingAs($this->admin)->get(route('overview.index', ['as' => $this->admin->id]))->assertNotFound();
});

it('is not a client\'s page', function (): void {
    $this->actingAs($this->client)->get(route('overview.index'))->assertForbidden();
});
