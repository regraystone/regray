<?php

use App\Enums\BookedByType;
use App\Enums\Role;
use App\Enums\ScheduleStatus;
use App\Models\Inquiry;
use App\Models\Schedule;
use App\Models\User;
use Carbon\CarbonImmutable;

/*
 * Which visits staff read: an inquiry's visit follows its inquiry
 * (Inquiry::scopeVisibleTo()); a visit booked outside an inquiry is read by
 * every marketing and sales user; the admin and the owner read every one.
 * Acting on a visit needs the same, and one a user does not read is a 404.
 */

beforeEach(function (): void {
    seedInquiryCatalog();
    $this->travelTo(CarbonImmutable::parse('2026-10-14 10:00', 'Asia/Manila'));
});

/** A pending visit on 15 October, for `$inquiry` or for nobody's inquiry. */
function visibilityVisit(?Inquiry $inquiry, string $title): Schedule
{
    $client = $inquiry?->client ?? inquiryClient();
    $schedule = new Schedule;
    $schedule->forceFill([
        'client_id' => $client->id,
        'booked_by' => $client->id,
        'booked_by_type' => BookedByType::Client,
        'inquiry_id' => $inquiry?->id,
        'title' => $title,
        'arrival_at' => CarbonImmutable::parse('2026-10-15 10:00', 'Asia/Manila')->utc(),
        'status' => $inquiry === null ? ScheduleStatus::Pending : ScheduleStatus::Confirmed,
    ])->save();

    return $schedule;
}

/**
 * The visibility world's people, a standalone visit, and a visit for its lead,
 * sales-assigned, assigned and foreign inquiries.
 *
 * @return array{users: array<string, User>, visits: array<string, Schedule>}
 */
function visitWorld(): array
{
    $world = visibilityWorld();
    $visits = ['standalone' => visibilityVisit(null, 'standalone')];

    foreach (['lead', 'salesAssigned', 'assigned', 'foreign'] as $key) {
        $visits[$key] = visibilityVisit($world['inquiries'][$key], $key);
    }

    return ['users' => $world['users'], 'visits' => $visits];
}

it('shows each role exactly the visits it reads on the Schedule page', function (Role $role, array $visible): void {
    $world = visitWorld();
    $range = ['from' => '2026-10-01T00:00:00+08:00', 'to' => '2026-11-01T00:00:00+08:00'];

    $props = $this->actingAs($world['users'][$role->value])->get(route('schedule.index', $range))
        ->assertOk()
        ->viewData('page')['props'];

    expect(array_column($props['schedules'], 'title'))->toEqualCanonicalizing($visible)
        ->and(array_column($props['upcoming'], 'title'))->each->toBeIn($visible);
})->with([
    'admin, every one' => [Role::Admin, ['standalone', 'lead', 'salesAssigned', 'assigned', 'foreign']],
    'owner, every one' => [Role::Owner, ['standalone', 'lead', 'salesAssigned', 'assigned', 'foreign']],
    'marketing, standalone and its lead\'s' => [Role::Marketing, ['standalone', 'lead']],
    'sales, standalone and its assigned one\'s' => [Role::Sales, ['standalone', 'salesAssigned']],
    'technical, its assigned one\'s only' => [Role::Technical, ['assigned']],
    'logistics, its assigned one\'s only' => [Role::Logistics, ['assigned']],
]);

it('gives accounting its assigned inquiry\'s visit and no standalone one', function (): void {
    $world = visitWorld();

    expect(Schedule::query()->visibleTo($world['users']['accounting'])->pluck('title')->all())->toBe(['assigned']);
});

it('shows a standalone visit to every marketing and sales user, not only the one involved', function (Role $role): void {
    $world = visitWorld();

    expect(Schedule::query()->visibleTo(User::factory()->role($role)->create())->pluck('title')->all())->toBe(['standalone']);
})->with([
    'marketing' => [Role::Marketing],
    'sales' => [Role::Sales],
]);

it('answers alike for a list and for one loaded visit', function (Role $role): void {
    $world = visitWorld();
    $user = $world['users'][$role->value];

    foreach ($world['visits'] as $key => $visit) {
        expect(Schedule::query()->find($visit->id)->isVisibleTo($user))
            ->toBe(Schedule::query()->visibleTo($user)->whereKey($visit->id)->exists(), "{$role->value} on {$key}");
    }
})->with(fn (): array => array_map(fn (Role $role): array => [$role], array_filter(Role::cases(), fn (Role $role): bool => $role !== Role::Client)));

it('404s every change to a visit the user does not read', function (string $action): void {
    $world = visitWorld();
    $sales = $world['users']['sales'];
    $hidden = $world['visits']['foreign'];

    $response = match ($action) {
        'edit' => $this->actingAs($sales)->get(route('schedule.edit', $hidden)),
        'update' => $this->actingAs($sales)->patch(route('schedule.update', $hidden), ['purpose' => 'Showroom visit', 'date' => '2026-10-17', 'arrivalTime' => '14:00', 'notes' => '', 'reason' => 'The architect can only come on Saturday']),
        'cancel' => $this->actingAs($sales)->post(route('schedule.cancel', $hidden), ['reason' => 'Plans changed']),
        'approve' => $this->actingAs($sales)->post(route('schedule.approve', $hidden)),
        'serve' => $this->actingAs($sales)->post(route('schedule.serve', $hidden)),
        'noShow' => $this->actingAs($sales)->post(route('schedule.no-show', $hidden)),
    };

    $response->assertNotFound();
    expect($hidden->fresh()->revision)->toBe(0)
        ->and($hidden->fresh()->status)->toBe(ScheduleStatus::Confirmed);
})->with(['edit', 'update', 'cancel', 'approve', 'serve', 'noShow']);

it('still refuses a change to an inquiry\'s visit the user reads by its state, not as missing', function (): void {
    $world = visitWorld();

    $this->actingAs($world['users']['sales'])
        ->post(route('schedule.cancel', $world['visits']['salesAssigned']), ['reason' => 'Plans changed'])
        ->assertSessionHasErrors(['schedule' => 'This visit belongs to an inquiry, so it changes with the inquiry.']);
});

it('lets sales act on a standalone visit, which every sales user reads', function (): void {
    $world = visitWorld();
    $visit = $world['visits']['standalone'];

    $this->actingAs($world['users']['sales'])->post(route('schedule.approve', $visit))->assertRedirect()->assertSessionHasNoErrors();

    expect($visit->fresh()->status)->toBe(ScheduleStatus::Confirmed);
});
