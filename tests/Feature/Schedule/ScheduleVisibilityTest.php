<?php

use App\Enums\BookedByType;
use App\Enums\Role;
use App\Enums\ScheduleStatus;
use App\Models\Inquiry;
use App\Models\Schedule;
use App\Models\User;
use Carbon\CarbonImmutable;

/*
 * Which visits staff read. An inquiry's visit follows its inquiry
 * (Inquiry::scopeVisibleTo()), read-only. A visit booked outside an inquiry is
 * read by the marketers handling it (who booked or assigned it, and for a
 * client's own booking, whose queue it is in: the lead holder, else the thread
 * holder, else every marketer) and by the sales member it is assigned to. The
 * admin and the owner read every one. Acting on a visit needs the same, and
 * one a user does not read is a 404.
 */

beforeEach(function (): void {
    seedInquiryCatalog();
    $this->travelTo(CarbonImmutable::parse('2026-10-14 10:00', 'Asia/Manila'));
});

/** A pending visit on 15 October, for `$inquiry` or for nobody's inquiry, with `$attributes` over it. */
function visibilityVisit(?Inquiry $inquiry, string $title, ?User $client = null, array $attributes = []): Schedule
{
    $client ??= $inquiry?->client ?? inquiryClient();
    $schedule = new Schedule;
    $schedule->forceFill([
        'client_id' => $client->id,
        'booked_by' => $client->id,
        'booked_by_type' => BookedByType::Client,
        'inquiry_id' => $inquiry?->id,
        'title' => $title,
        'arrival_at' => CarbonImmutable::parse('2026-10-15 10:00', 'Asia/Manila')->utc(),
        'status' => $inquiry === null ? ScheduleStatus::Pending : ScheduleStatus::Confirmed,
        ...$attributes,
    ])->save();

    return $schedule;
}

/**
 * The visibility world's people, and its visits:
 *  - `queued`: a client's own booking, the client with no lead and no thread
 *    holder, so in every marketer's queue;
 *  - `otherLeads`: a client's own booking whose lead another marketer holds;
 *  - `booked`: one marketing booked and assigned to sales;
 *  - `elsewhere`: one another marketer booked and assigned to another sales member;
 *  - one per inquiry: its lead's, sales-assigned, assigned and foreign.
 *
 * @return array{users: array<string, User>, others: array{marketing: User, sales: User}, visits: array<string, Schedule>}
 */
function visitWorld(): array
{
    $world = visibilityWorld();
    $users = $world['users'];
    $others = ['marketing' => User::factory()->role(Role::Marketing)->create(), 'sales' => User::factory()->role(Role::Sales)->create()];
    $assignedBy = fn (User $marketer, User $sales): array => [
        'booked_by' => $marketer->id,
        'booked_by_type' => BookedByType::Staff,
        'status' => ScheduleStatus::AwaitingSales,
        'assigned_to' => $sales->id,
        'assigned_by' => $marketer->id,
        'assigned_at' => now(),
    ];
    $otherLeadsClient = inquiryClient();
    makeLead($otherLeadsClient, $others['marketing']);

    $visits = [
        'queued' => visibilityVisit(null, 'queued'),
        'otherLeads' => visibilityVisit(null, 'otherLeads', $otherLeadsClient),
        'booked' => visibilityVisit(null, 'booked', attributes: $assignedBy($users['marketing'], $users['sales'])),
        'elsewhere' => visibilityVisit(null, 'elsewhere', attributes: $assignedBy($others['marketing'], $others['sales'])),
    ];

    foreach (['lead', 'salesAssigned', 'assigned', 'foreign'] as $key) {
        $visits[$key] = visibilityVisit($world['inquiries'][$key], $key);
    }

    return ['users' => $users, 'others' => $others, 'visits' => $visits];
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
    'admin, every one' => [Role::Admin, ['queued', 'otherLeads', 'booked', 'elsewhere', 'lead', 'salesAssigned', 'assigned', 'foreign']],
    'owner, every one' => [Role::Owner, ['queued', 'otherLeads', 'booked', 'elsewhere', 'lead', 'salesAssigned', 'assigned', 'foreign']],
    'marketing, its queue, its booking and its lead\'s' => [Role::Marketing, ['queued', 'booked', 'lead']],
    'sales, the booking assigned to it and its assigned inquiry\'s' => [Role::Sales, ['booked', 'salesAssigned']],
    'technical, its assigned inquiry\'s only' => [Role::Technical, ['assigned']],
    'logistics, its assigned inquiry\'s only' => [Role::Logistics, ['assigned']],
    'accounting, its assigned inquiry\'s only' => [Role::Accounting, ['assigned']],
]);

it('gives technical, accounting and logistics their inquiries\' visits read-only', function (Role $role): void {
    $world = visitWorld();
    $props = $this->actingAs($world['users'][$role->value])->get(route('schedule.index', ['from' => '2026-10-01T00:00:00+08:00', 'to' => '2026-11-01T00:00:00+08:00']))
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['schedules'][0]['can'])->each->toBeFalse();
})->with([Role::Technical, Role::Accounting, Role::Logistics]);

it('puts a client\'s own booking in its lead holder\'s queue, else its thread holder\'s, else every marketer\'s', function (): void {
    $holder = messagingStaff(Role::Marketing, 'Holder');
    $other = messagingStaff(Role::Marketing, 'Other');
    $byLead = inquiryClient();
    makeLead($byLead, $holder);
    $byThread = inquiryClient();
    supportThreadFor($byThread)->forceFill(['assigned_to' => $holder->id, 'assigned_at' => now()])->save();
    $inPool = inquiryClient();
    supportThreadFor($inPool);

    $visits = [
        'byLead' => visibilityVisit(null, 'byLead', $byLead),
        'byThread' => visibilityVisit(null, 'byThread', $byThread),
        'inPool' => visibilityVisit(null, 'inPool', $inPool),
    ];

    expect(Schedule::query()->visibleTo($holder)->pluck('title')->all())->toEqualCanonicalizing(['byLead', 'byThread', 'inPool'])
        ->and(Schedule::query()->visibleTo($other)->pluck('title')->all())->toBe(['inPool'])
        ->and($visits['byLead']->marketingQueue()->modelKeys())->toBe([$holder->id])
        ->and($visits['byThread']->marketingQueue()->modelKeys())->toBe([$holder->id])
        ->and($visits['inPool']->marketingQueue()->modelKeys())->toEqualCanonicalizing([$holder->id, $other->id]);
});

it('keeps a declined visit with the marketer who assigned it, and takes it from the sales member who declined', function (): void {
    $world = visitWorld();
    $visit = $world['visits']['otherLeads'];
    $visit->forceFill([
        'assigned_by' => $world['users']['marketing']->id,
        'declined_by' => $world['users']['sales']->id,
        'declined_at' => now(),
        'decline_reason' => 'Out of the office',
    ])->save();

    expect($visit->fresh()->isVisibleTo($world['users']['marketing']))->toBeTrue()
        ->and($visit->fresh()->isVisibleTo($world['users']['sales']))->toBeFalse();
});

it('answers alike for a list and for one loaded visit', function (Role $role): void {
    $world = visitWorld();
    $user = $world['users'][$role->value];

    foreach ($world['visits'] as $key => $visit) {
        expect(Schedule::query()->find($visit->id)->isVisibleTo($user))
            ->toBe(Schedule::query()->visibleTo($user)->whereKey($visit->id)->exists(), "{$role->value} on {$key}");
    }
})->with(fn (): array => array_map(fn (Role $role): array => [$role], array_filter(Role::cases(), fn (Role $role): bool => $role !== Role::Client)));

it('404s every change to a visit the user does not read', function (string $role, string $action): void {
    $world = visitWorld();
    $user = $world['users'][$role];
    $hidden = $world['visits']['elsewhere'];

    $response = match ($action) {
        'edit' => $this->actingAs($user)->get(route('schedule.edit', $hidden)),
        'update' => $this->actingAs($user)->patch(route('schedule.update', $hidden), ['purpose' => 'Showroom visit', 'date' => '2026-10-17', 'arrivalTime' => '14:00', 'notes' => '', 'reason' => 'The architect can only come on Saturday']),
        'cancel' => $this->actingAs($user)->post(route('schedule.cancel', $hidden), ['reason' => 'Plans changed']),
        'assign' => $this->actingAs($user)->post(route('schedule.assign', $hidden), ['salesId' => $world['users']['sales']->id]),
        'confirm' => $this->actingAs($user)->post(route('schedule.confirm', $hidden)),
        'decline' => $this->actingAs($user)->post(route('schedule.decline', $hidden), ['reason' => 'Out of the office']),
        'serve' => $this->actingAs($user)->post(route('schedule.serve', $hidden)),
        'noShow' => $this->actingAs($user)->post(route('schedule.no-show', $hidden)),
    };

    $response->assertNotFound();
    expect($hidden->fresh()->revision)->toBe(0)
        ->and($hidden->fresh()->status)->toBe(ScheduleStatus::AwaitingSales);
})->with([
    ['marketing', 'edit'], ['marketing', 'update'], ['marketing', 'cancel'], ['marketing', 'assign'],
    ['sales', 'confirm'], ['sales', 'decline'], ['sales', 'serve'], ['sales', 'noShow'],
]);

it('still refuses a change to an inquiry\'s visit the user reads by its state, not as missing', function (): void {
    $world = visitWorld();

    $this->actingAs($world['users']['marketing'])
        ->post(route('schedule.cancel', $world['visits']['lead']), ['reason' => 'Plans changed'])
        ->assertSessionHasErrors(['schedule' => 'This visit belongs to an inquiry, so it changes with the inquiry.']);
});

it('lets the sales member a visit is assigned to confirm it', function (): void {
    $world = visitWorld();
    $visit = $world['visits']['booked'];

    $this->actingAs($world['users']['sales'])->post(route('schedule.confirm', $visit))->assertRedirect()->assertSessionHasNoErrors();

    expect($visit->fresh()->status)->toBe(ScheduleStatus::Confirmed)
        ->and($visit->fresh()->approved_by)->toBe($world['users']['sales']->id);
});
