<?php

use App\Enums\BookedByType;
use App\Enums\Role;
use App\Enums\ScheduleStatus;
use App\Models\Schedule;
use App\Models\User;
use App\Services\ScheduleChangeService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;

/*
 * `schedules:remind`: a sales member who has not answered an appointment
 * assigned to them, and the marketers with one nobody is assigned to, are
 * reminded once, in the app, 24 hours after it started waiting or 24 hours
 * before its time, whichever comes first, and never within the hour. "Now" is
 * Wednesday 14 October 2026, 13:05 in Manila.
 */

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->travelTo(CarbonImmutable::parse('2026-10-14 13:05', 'Asia/Manila'));
    $this->sales = User::factory()->role(Role::Sales)->create(['name' => 'Sam']);
    $this->client = User::factory()->role(Role::Client)->create(['name' => 'Carla']);
});

/** An appointment awaiting `$sales`, assigned `$assignedHoursAgo` hours ago, arriving at `$at` in Manila. */
function waitingOn(User $client, User $sales, float $assignedHoursAgo, string $at, ScheduleStatus $status = ScheduleStatus::AwaitingSales): Schedule
{
    $schedule = new Schedule;
    $schedule->forceFill([
        'client_id' => $client->id,
        'booked_by' => $client->id,
        'booked_by_type' => BookedByType::Client,
        'title' => 'Showroom visit',
        'arrival_at' => CarbonImmutable::parse($at, 'Asia/Manila')->utc(),
        'status' => $status,
        'assigned_to' => $sales->id,
        'assigned_by' => User::factory()->role(Role::Marketing)->create()->id,
        'assigned_at' => now()->subMinutes((int) round($assignedHoursAgo * 60)),
        'approved_at' => $status === ScheduleStatus::Confirmed ? now() : null,
    ])->save();

    return $schedule;
}

it('reminds the sales member a day after it was assigned, or a day before its time, whichever comes first', function (): void {
    $dayOld = waitingOn($this->client, $this->sales, 25, '2026-10-20 10:00');
    $soon = waitingOn($this->client, $this->sales, 2, '2026-10-15 10:00');
    $notYet = waitingOn($this->client, $this->sales, 5, '2026-10-20 10:00');
    $justAssigned = waitingOn($this->client, $this->sales, 0.5, '2026-10-14 16:00');

    $this->artisan('schedules:remind')->assertSuccessful();

    expect($dayOld->fresh()->sales_reminded_at)->not->toBeNull()
        ->and($soon->fresh()->sales_reminded_at)->not->toBeNull()
        ->and($notYet->fresh()->sales_reminded_at)->toBeNull()
        ->and($justAssigned->fresh()->sales_reminded_at)->toBeNull()
        ->and(notificationBodies($this->sales))->toBe([
            'Reminder: the appointment with Carla on Oct 20, 2026 at 10:00 AM is still waiting for you. Confirm or decline it.',
            'Reminder: the appointment with Carla on Oct 15, 2026 at 10:00 AM is still waiting for you. Confirm or decline it.',
        ]);
});

it('reminds only once per assignment, and again after it is assigned anew', function (): void {
    $schedule = waitingOn($this->client, $this->sales, 25, '2026-10-20 10:00');

    $this->artisan('schedules:remind')->assertSuccessful();
    $this->artisan('schedules:remind')->assertSuccessful();

    expect(notificationBodies($this->sales))->toHaveCount(1);

    app(ScheduleChangeService::class)->assign($schedule->fresh(), $schedule->assignedBy, $this->sales, null, null);
    $this->travel(25)->hours();
    $this->artisan('schedules:remind')->assertSuccessful();

    expect(collect(notificationBodies($this->sales))->filter(fn (string $body): bool => str_starts_with($body, 'Reminder:')))->toHaveCount(2);
});

it('reminds nobody of an appointment confirmed, past, or for nobody\'s sales', function (): void {
    waitingOn($this->client, $this->sales, 30, '2026-10-20 10:00', ScheduleStatus::Confirmed);
    waitingOn($this->client, $this->sales, 30, '2026-10-14 12:00');
    $other = User::factory()->role(Role::Sales)->create();

    $this->artisan('schedules:remind')->assertSuccessful();

    expect(notificationBodies($this->sales))->toBe([])
        ->and(notificationBodies($other))->toBe([]);
});

it('tells marketing nothing, and changes nothing on the appointment itself', function (): void {
    $schedule = waitingOn($this->client, $this->sales, 25, '2026-10-20 10:00');
    $marketing = $schedule->assignedBy;

    $this->artisan('schedules:remind')->assertSuccessful();

    expect(notificationBodies($marketing))->toBe([])
        ->and(notificationBodies($this->client))->toBe([])
        ->and($schedule->fresh()->revision)->toBe(0)
        ->and($schedule->fresh()->status)->toBe(ScheduleStatus::AwaitingSales);
});

it('counts the reminder anew from a reschedule that sends it back to its sales member', function (): void {
    $schedule = waitingOn($this->client, $this->sales, 30, '2026-10-20 10:00', ScheduleStatus::Confirmed);
    $schedule->forceFill(['sales_reminded_at' => now()->subDay()])->save();

    app(ScheduleChangeService::class)->update($schedule->fresh(), $this->client, 'Showroom visit', CarbonImmutable::parse('2026-10-22 10:00', 'Asia/Manila'), null, 'Moved', null, null);

    expect($schedule->fresh()->status)->toBe(ScheduleStatus::AwaitingSales)
        ->and($schedule->fresh()->sales_reminded_at)->toBeNull();

    // Not an hour later: it was just asked again.
    $this->travel(30)->minutes();
    $this->artisan('schedules:remind')->assertSuccessful();
    expect($schedule->fresh()->sales_reminded_at)->toBeNull();

    $this->travel(24)->hours();
    $this->artisan('schedules:remind')->assertSuccessful();
    expect($schedule->fresh()->sales_reminded_at)->not->toBeNull();
});

/** A client's booking still pending with nobody assigned, booked `$bookedHoursAgo` hours ago. */
function unassigned(User $client, float $bookedHoursAgo, string $at): Schedule
{
    $schedule = new Schedule;
    $schedule->forceFill([
        'client_id' => $client->id,
        'booked_by' => $client->id,
        'booked_by_type' => BookedByType::Client,
        'title' => 'Showroom visit',
        'arrival_at' => CarbonImmutable::parse($at, 'Asia/Manila')->utc(),
        'status' => ScheduleStatus::Pending,
        'created_at' => now()->subMinutes((int) round($bookedHoursAgo * 60)),
    ])->save();

    return $schedule;
}

describe('marketing', function (): void {
    it('reminds the client\'s marketers of a booking nobody has assigned, a day on or a day before its time', function (): void {
        $holder = User::factory()->role(Role::Marketing)->create();
        $other = User::factory()->role(Role::Marketing)->create();
        makeLead($this->client, $holder);
        $dayOld = unassigned($this->client, 25, '2026-10-20 10:00');
        $soon = unassigned($this->client, 2, '2026-10-15 10:00');
        $notYet = unassigned($this->client, 5, '2026-10-20 10:00');

        $this->artisan('schedules:remind')->assertSuccessful();

        expect($dayOld->fresh()->marketing_reminded_at)->not->toBeNull()
            ->and($soon->fresh()->marketing_reminded_at)->not->toBeNull()
            ->and($notYet->fresh()->marketing_reminded_at)->toBeNull()
            ->and(notificationBodies($holder))->toBe([
                'Reminder: the appointment with Carla on Oct 20, 2026 at 10:00 AM has had no sales member. Assign it to sales to confirm.',
                'Reminder: the appointment with Carla on Oct 15, 2026 at 10:00 AM has had no sales member. Assign it to sales to confirm.',
            ])
            ->and(notificationBodies($other))->toBe([])
            ->and(notificationBodies($this->sales))->toBe([]);

        $this->artisan('schedules:remind')->assertSuccessful();

        expect(notificationBodies($holder))->toHaveCount(2);
    });

    it('reminds every marketer of a client nobody holds, and none of the admins', function (): void {
        $mara = User::factory()->role(Role::Marketing)->create();
        $mike = User::factory()->role(Role::Marketing)->create();
        $admin = User::factory()->role(Role::Admin)->create();
        unassigned($this->client, 25, '2026-10-20 10:00');

        $this->artisan('schedules:remind')->assertSuccessful();

        expect(notificationBodies($mara))->toHaveCount(1)
            ->and(notificationBodies($mike))->toHaveCount(1)
            ->and(notificationBodies($admin))->toBe([]);
    });

    it('counts a declined appointment from its decline, and tells whoever assigned it', function (): void {
        $schedule = waitingOn($this->client, $this->sales, 30, '2026-10-20 10:00');
        $marketing = $schedule->assignedBy;
        $schedule->forceFill(['marketing_reminded_at' => now()->subDays(2)])->save();

        app(ScheduleChangeService::class)->decline($schedule->fresh(), $this->sales, 'I am away that week', null, null);

        expect($schedule->fresh()->marketing_reminded_at)->toBeNull();

        $this->artisan('schedules:remind')->assertSuccessful();
        expect($schedule->fresh()->marketing_reminded_at)->toBeNull();

        $this->travel(25)->hours();
        $this->artisan('schedules:remind')->assertSuccessful();

        expect($schedule->fresh()->marketing_reminded_at)->not->toBeNull()
            ->and(collect(notificationBodies($marketing))->last())->toBe('Reminder: the appointment with Carla on Oct 20, 2026 at 10:00 AM has had no sales member since it was declined. Assign it to sales to confirm.');
    });

    it('reminds nobody of one already assigned, cancelled or past', function (): void {
        $marketing = User::factory()->role(Role::Marketing)->create();
        waitingOn($this->client, $this->sales, 30, '2026-10-20 10:00')->forceFill(['sales_reminded_at' => now()])->save();
        unassigned($this->client, 30, '2026-10-14 12:00');
        unassigned($this->client, 30, '2026-10-21 10:00')->forceFill(['status' => ScheduleStatus::Cancelled, 'cancelled_at' => now(), 'cancel_reason' => 'Plans changed'])->save();

        $this->artisan('schedules:remind')->assertSuccessful();

        expect(notificationBodies($marketing))->toBe([]);
    });
});
