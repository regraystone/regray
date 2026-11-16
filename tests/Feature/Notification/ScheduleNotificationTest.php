<?php

use App\Actions\Schedule\BookScheduleAction;
use App\Enums\Role;
use App\Models\Schedule;
use App\Models\User;
use App\Services\ScheduleChangeService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;

/*
 * Appointment notifications. Only those who read an appointment hear of it:
 * the owner and the admin, the marketers handling it, and the sales member it
 * is assigned to. Technical, accounting and logistics are told nothing, nor is
 * a sales member it is not assigned to. The client hears of a booking
 * marketing made only once its sales member confirms it. "Now" is Wednesday
 * 14 October 2026, 13:05 in Manila.
 */

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->travelTo(CarbonImmutable::parse('2026-10-14 13:05', 'Asia/Manila'));
});

/** @return array{client: User, marketing: User, otherMarketing: User, sales: User, otherSales: User, owner: User, admin: User, technical: User} */
function scheduleCast(): array
{
    return [
        'client' => User::factory()->role(Role::Client)->create(['name' => 'Carla']),
        'marketing' => User::factory()->role(Role::Marketing)->create(['name' => 'Mara']),
        'otherMarketing' => User::factory()->role(Role::Marketing)->create(['name' => 'Mike']),
        'sales' => User::factory()->role(Role::Sales)->create(['name' => 'Sam']),
        'otherSales' => User::factory()->role(Role::Sales)->create(['name' => 'Sue']),
        'owner' => User::factory()->role(Role::Owner)->create(['name' => 'Olive']),
        'admin' => User::factory()->role(Role::Admin)->create(['name' => 'Ada']),
        'technical' => User::factory()->role(Role::Technical)->create(['name' => 'Tim']),
    ];
}

function friday(string $time = '10:00'): CarbonImmutable
{
    return CarbonImmutable::parse("2026-10-16 {$time}", 'Asia/Manila');
}

function clientBooking(User $client): Schedule
{
    return app(BookScheduleAction::class)->execute($client, 'Showroom visit', friday(), null);
}

/** Marketing's booking for `$cast['client']`, assigned to `$cast['sales']`. */
function marketingBooking(array $cast, string $time = '10:00'): Schedule
{
    return app(BookScheduleAction::class)->executeForStaff($cast['marketing'], $cast['client'], $cast['sales'], 'Showroom visit', friday($time), null);
}

it('tells the client their booking was sent, and marketing and the admins to assign it, not sales', function (): void {
    $cast = scheduleCast();

    clientBooking($cast['client']);

    expect(notificationBodies($cast['client']))->toBe(['Your appointment request for Oct 16, 2026 at 10:00 AM has been sent.']);

    // With no lead and no thread holder, it is in every marketer's queue.
    foreach (['marketing', 'otherMarketing', 'owner', 'admin'] as $who) {
        expect(notificationBodies($cast[$who]))->toBe(['Carla has booked an appointment for Oct 16, 2026 at 10:00 AM. Assign it to sales to confirm.'], $who);
    }

    foreach (['sales', 'otherSales', 'technical'] as $who) {
        expect(notificationBodies($cast[$who]))->toBe([], $who);
    }
});

it('tells only the lead holder among marketers of their client\'s booking', function (): void {
    $cast = scheduleCast();
    makeLead($cast['client'], $cast['otherMarketing']);

    clientBooking($cast['client']);

    expect(notificationBodies($cast['otherMarketing']))->toHaveCount(1)
        ->and(notificationBodies($cast['marketing']))->toBe([]);
});

it('asks the sales member to confirm a marketing booking, and tells the client nothing yet', function (): void {
    $cast = scheduleCast();

    marketingBooking($cast);

    expect(notificationBodies($cast['sales']))->toBe(['Mara has assigned you an appointment with Carla on Oct 16, 2026 at 10:00 AM. Confirm or decline it.'])
        ->and(notificationBodies($cast['owner']))->toBe(['Mara has booked an appointment with Carla for Oct 16, 2026 at 10:00 AM.'])
        ->and(notificationBodies($cast['client']))->toBe([])
        ->and(notificationBodies($cast['marketing']))->toBe([])
        ->and(notificationBodies($cast['otherMarketing']))->toBe([])
        ->and(notificationBodies($cast['otherSales']))->toBe([])
        ->and(notificationBodies($cast['technical']))->toBe([]);
});

it('tells the client and marketing once sales confirms, and marketing why when sales declines', function (): void {
    $cast = scheduleCast();
    $changes = app(ScheduleChangeService::class);
    $confirmed = marketingBooking($cast);
    $declined = marketingBooking($cast, '15:00');

    $changes->confirm($confirmed, $cast['sales'], null, null);
    $changes->decline($declined, $cast['sales'], 'On leave that afternoon.', null, null);

    expect(notificationBodies($cast['client']))->toBe(['Your appointment on Oct 16, 2026 at 10:00 AM has been confirmed.'])
        ->and(notificationBodies($cast['marketing']))->toBe([
            'Sam has confirmed the appointment with Carla on Oct 16, 2026 at 10:00 AM.',
            'Sam has declined the appointment with Carla on Oct 16, 2026 at 3:00 PM: On leave that afternoon. Assign it to someone else.',
        ])
        // The one who answered is not told their own answer.
        ->and(array_slice(notificationBodies($cast['sales']), 2))->toBe([]);
});

it('asks the sales member marketing assigns a client\'s booking to, and tells the other marketers in its queue', function (): void {
    $cast = scheduleCast();
    $schedule = clientBooking($cast['client']);

    app(ScheduleChangeService::class)->assign($schedule, $cast['marketing'], $cast['otherSales'], null, null);

    expect(notificationBodies($cast['otherSales']))->toBe(['Mara has assigned you an appointment with Carla on Oct 16, 2026 at 10:00 AM. Confirm or decline it.'])
        ->and(array_slice(notificationBodies($cast['otherMarketing']), 1))->toBe(['Mara has assigned the appointment with Carla on Oct 16, 2026 at 10:00 AM to Sue.'])
        ->and(notificationBodies($cast['sales']))->toBe([])
        // The client still only knows it as pending.
        ->and(notificationBodies($cast['client']))->toHaveCount(1);
});

it('confirms the client\'s own update and cancel to them, and tells those handling it', function (): void {
    $cast = scheduleCast();
    $schedule = clientBooking($cast['client']);
    $changes = app(ScheduleChangeService::class);

    $changes->update($schedule, $cast['client'], 'Showroom visit', friday('14:00'), null, 'Later suits me', null, null);
    $changes->cancel($schedule->fresh(), $cast['client'], 'Plans changed', null, null);

    expect(array_slice(notificationBodies($cast['client']), 1))->toBe([
        'You have successfully updated your appointment on Oct 16, 2026 at 2:00 PM.',
        'You have successfully cancelled your appointment on Oct 16, 2026 at 2:00 PM.',
    ])
        ->and(array_slice(notificationBodies($cast['marketing']), 1))->toBe([
            'Carla has updated their appointment on Oct 16, 2026 at 2:00 PM.',
            'Carla has cancelled their appointment on Oct 16, 2026 at 2:00 PM.',
        ]);
});

it('tells marketing its update or cancel worked, and the client and the sales member what changed', function (): void {
    $cast = scheduleCast();
    $schedule = marketingBooking($cast);
    $changes = app(ScheduleChangeService::class);
    $changes->confirm($schedule, $cast['sales'], null, null);

    $changes->update($schedule->fresh(), $cast['marketing'], 'Showroom visit', friday('11:00'), null, 'Room is busy at ten', null, null);
    $changes->cancel($schedule->fresh(), $cast['marketing'], 'Client called', null, null);

    expect(array_slice(notificationBodies($cast['marketing']), 1))->toBe([
        'You have successfully updated the appointment with Carla.',
        'You have successfully cancelled the appointment with Carla.',
    ])
        ->and(array_slice(notificationBodies($cast['sales']), 1))->toBe([
            'Mara has updated the appointment with Carla on Oct 16, 2026 at 11:00 AM.',
            'Mara has cancelled the appointment with Carla on Oct 16, 2026 at 11:00 AM.',
        ])
        ->and(array_slice(notificationBodies($cast['client']), 1))->toBe([
            'Your appointment on Oct 16, 2026 at 11:00 AM has been updated.',
            'Your appointment on Oct 16, 2026 at 11:00 AM has been cancelled.',
        ]);
});

it('keeps a change to a marketing booking from the client until it is confirmed', function (): void {
    $cast = scheduleCast();
    $schedule = marketingBooking($cast);

    app(ScheduleChangeService::class)->update($schedule, $cast['marketing'], 'Showroom visit', friday('11:00'), null, 'Room is busy at ten', null, null);

    expect(notificationBodies($cast['client']))->toBe([])
        ->and(notificationBodies($cast['sales']))->toContain('Mara has updated the appointment with Carla on Oct 16, 2026 at 11:00 AM.');
});

it('tells the client and those handling it an appointment was served, or cancelled when the client did not come', function (): void {
    $cast = scheduleCast();
    $changes = app(ScheduleChangeService::class);
    $served = marketingBooking($cast);
    $missed = marketingBooking($cast, '15:00');
    $changes->confirm($served, $cast['sales'], null, null);
    $changes->confirm($missed, $cast['sales'], null, null);
    $this->travelTo(friday('16:00'));

    $changes->serve($served->fresh(), $cast['sales'], null, null);
    $changes->noShow($missed->fresh(), $cast['sales'], null, null);

    $servedLine = 'Sam has marked the appointment with Carla on Oct 16, 2026 at 10:00 AM as served.';

    expect(notificationBodies($cast['client']))->toContain('Your appointment on Oct 16, 2026 at 10:00 AM has been marked as served.', 'Your appointment on Oct 16, 2026 at 3:00 PM has been cancelled.')
        ->and(notificationBodies($cast['marketing']))->toContain($servedLine, 'Sam has cancelled the appointment with Carla on Oct 16, 2026 at 3:00 PM.')
        ->and(notificationBodies($cast['sales']))->not->toContain($servedLine)
        ->and(notificationBodies($cast['otherSales']))->toBe([]);
});

it('tells the client and those handling it when a booking expires, the client only of one they knew of', function (): void {
    $cast = scheduleCast();
    $own = clientBooking($cast['client']);
    $unconfirmed = marketingBooking($cast);
    $this->travelTo(friday('11:00'));

    app(ScheduleChangeService::class)->expire($own->id);
    app(ScheduleChangeService::class)->expire($unconfirmed->id);

    expect(notificationBodies($cast['client']))->toBe([
        'Your appointment request for Oct 16, 2026 at 10:00 AM has been sent.',
        'Your appointment request for Oct 16, 2026 at 10:00 AM has expired.',
    ])
        ->and(notificationBodies($cast['marketing']))->toContain('The appointment with Carla on Oct 16, 2026 at 10:00 AM has expired.')
        ->and(notificationBodies($cast['sales']))->toContain('The appointment with Carla on Oct 16, 2026 at 10:00 AM has expired.')
        ->and(notificationBodies($cast['admin']))->toContain('The appointment with Carla on Oct 16, 2026 at 10:00 AM has expired.');
});
