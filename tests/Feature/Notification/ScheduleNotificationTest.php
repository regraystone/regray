<?php

use App\Actions\Schedule\BookScheduleAction;
use App\Enums\Role;
use App\Models\Schedule;
use App\Models\User;
use App\Services\ScheduleChangeService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;

/*
 * Appointment notifications. The team is every marketing and sales user, with
 * the owner and the admin; technical, accounting and logistics are told
 * nothing. "Now" is Wednesday 14 October 2026, 13:05 in Manila.
 */

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->travelTo(CarbonImmutable::parse('2026-10-14 13:05', 'Asia/Manila'));
});

/** @return array{client: User, marketing: User, otherMarketing: User, sales: User, owner: User, admin: User, technical: User} */
function scheduleCast(): array
{
    return [
        'client' => User::factory()->role(Role::Client)->create(['name' => 'Carla']),
        'marketing' => User::factory()->role(Role::Marketing)->create(['name' => 'Mara']),
        'otherMarketing' => User::factory()->role(Role::Marketing)->create(['name' => 'Mike']),
        'sales' => User::factory()->role(Role::Sales)->create(['name' => 'Sam']),
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

it('confirms a client\'s booking to them and tells the whole team', function (): void {
    $cast = scheduleCast();

    clientBooking($cast['client']);

    expect(notificationBodies($cast['client']))->toBe(['Your appointment request for Oct 16, 2026 at 10:00 AM has been sent.']);

    foreach (['marketing', 'otherMarketing', 'sales', 'owner', 'admin'] as $who) {
        expect(notificationBodies($cast[$who]))->toBe(['Carla has booked an appointment for Oct 16, 2026 at 10:00 AM.'], $who);
    }

    expect(notificationBodies($cast['technical']))->toBe([]);
});

it('tells the staff booker it is booked, and puts them in the team\'s notification too', function (): void {
    $cast = scheduleCast();

    app(BookScheduleAction::class)->executeForStaff($cast['sales'], $cast['client'], 'Showroom visit', friday(), null);

    $teamLine = 'Sam has booked an appointment with Carla for Oct 16, 2026 at 10:00 AM.';

    expect(notificationBodies($cast['sales']))->toBe([
        'You have successfully booked an appointment with Carla for Oct 16, 2026 at 10:00 AM.',
        $teamLine,
    ])
        ->and(notificationBodies($cast['client']))->toBe(['An appointment has been booked for you on Oct 16, 2026 at 10:00 AM.'])
        ->and(notificationBodies($cast['marketing']))->toBe([$teamLine])
        ->and(notificationBodies($cast['owner']))->toBe([$teamLine])
        ->and(notificationBodies($cast['technical']))->toBe([]);
});

it('confirms the client\'s own update and cancel to them, and tells the team', function (): void {
    $cast = scheduleCast();
    $schedule = clientBooking($cast['client']);
    $changes = app(ScheduleChangeService::class);

    $changes->update($schedule, $cast['client'], 'Showroom visit', friday('14:00'), null, 'Later suits me', null, null);
    $changes->cancel($schedule->fresh(), $cast['client'], 'Plans changed', null, null);

    expect(array_slice(notificationBodies($cast['client']), 1))->toBe([
        'You have successfully updated your appointment on Oct 16, 2026 at 2:00 PM.',
        'You have successfully cancelled your appointment on Oct 16, 2026 at 2:00 PM.',
    ])
        ->and(array_slice(notificationBodies($cast['sales']), 1))->toBe([
            'Carla has updated their appointment on Oct 16, 2026 at 2:00 PM.',
            'Carla has cancelled their appointment on Oct 16, 2026 at 2:00 PM.',
        ]);
});

it('tells staff who update or cancel that it worked, and the client and the team, them included, what changed', function (): void {
    $cast = scheduleCast();
    $schedule = clientBooking($cast['client']);
    $changes = app(ScheduleChangeService::class);

    $changes->update($schedule, $cast['marketing'], 'Showroom visit', friday('11:00'), null, 'Room is busy at ten', null, null);
    $changes->cancel($schedule->fresh(), $cast['sales'], 'Client called', null, null);

    expect(array_slice(notificationBodies($cast['marketing']), 1))->toBe([
        'You have successfully updated the appointment with Carla.',
        'Mara has updated the appointment with Carla on Oct 16, 2026 at 11:00 AM.',
        'Sam has cancelled the appointment with Carla on Oct 16, 2026 at 11:00 AM.',
    ])
        ->and(array_slice(notificationBodies($cast['sales']), 1))->toBe([
            'Mara has updated the appointment with Carla on Oct 16, 2026 at 11:00 AM.',
            'You have successfully cancelled the appointment with Carla.',
            'Sam has cancelled the appointment with Carla on Oct 16, 2026 at 11:00 AM.',
        ])
        ->and(array_slice(notificationBodies($cast['client']), 1))->toBe([
            'Your appointment on Oct 16, 2026 at 11:00 AM has been updated.',
            'Your appointment on Oct 16, 2026 at 11:00 AM has been cancelled.',
        ]);
});

it('tells the client their appointment is confirmed, and the team except whoever approved it', function (): void {
    $cast = scheduleCast();
    $schedule = clientBooking($cast['client']);

    app(ScheduleChangeService::class)->approve($schedule, $cast['marketing'], null, null);

    $teamLine = 'Mara has approved the appointment with Carla on Oct 16, 2026 at 10:00 AM.';

    expect(notificationBodies($cast['client']))->toContain('Your appointment on Oct 16, 2026 at 10:00 AM has been confirmed.')
        ->and(notificationBodies($cast['sales']))->toContain($teamLine)
        ->and(notificationBodies($cast['owner']))->toContain($teamLine)
        ->and(notificationBodies($cast['marketing']))->not->toContain($teamLine);
});

it('tells the client and the team an appointment was served, or cancelled when the client did not come', function (): void {
    $cast = scheduleCast();
    $changes = app(ScheduleChangeService::class);
    $served = app(BookScheduleAction::class)->executeForStaff($cast['sales'], $cast['client'], 'Showroom visit', friday(), null);
    $missed = app(BookScheduleAction::class)->executeForStaff($cast['sales'], $cast['client'], 'Showroom visit', friday('15:00'), null);
    $this->travelTo(friday('16:00'));

    $changes->serve($served, $cast['marketing'], null, null);
    $changes->noShow($missed, $cast['marketing'], null, null);

    $servedLine = 'Mara has marked the appointment with Carla on Oct 16, 2026 at 10:00 AM as served.';

    expect(notificationBodies($cast['client']))->toContain('Your appointment on Oct 16, 2026 at 10:00 AM has been marked as served.', 'Your appointment on Oct 16, 2026 at 3:00 PM has been cancelled.')
        ->and(notificationBodies($cast['sales']))->toContain($servedLine, 'Mara has cancelled the appointment with Carla on Oct 16, 2026 at 3:00 PM.')
        ->and(notificationBodies($cast['marketing']))->not->toContain($servedLine);
});

it('tells the client and the whole team when a booking expires', function (): void {
    $cast = scheduleCast();
    $schedule = clientBooking($cast['client']);
    $this->travelTo(friday('11:00'));

    app(ScheduleChangeService::class)->expire($schedule->id);

    expect(notificationBodies($cast['client']))->toContain('Your appointment request for Oct 16, 2026 at 10:00 AM has expired.')
        ->and(notificationBodies($cast['marketing']))->toContain('The appointment with Carla on Oct 16, 2026 at 10:00 AM has expired.')
        ->and(notificationBodies($cast['admin']))->toContain('The appointment with Carla on Oct 16, 2026 at 10:00 AM has expired.');
});
