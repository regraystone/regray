<?php

use App\Enums\BookedByType;
use App\Enums\Role;
use App\Enums\ScheduleStatus;
use App\Models\Schedule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;

/*
 * The sales picker on Book an appointment and in the Assign dialog: every
 * sales member, found by name or email, each with how many appointments are
 * still to happen with them. Marketing and the admin only. "Now" is
 * Wednesday 14 October 2026, 13:05 in Manila.
 */

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->travelTo(CarbonImmutable::parse('2026-10-14 13:05', 'Asia/Manila'));
});

/** An appointment for a client, assigned to `$sales`, `$status`, at `$at` in Manila. */
function salesLoad(User $sales, ScheduleStatus $status, string $at = '2026-10-16 10:00'): Schedule
{
    $client = inquiryClient();
    $schedule = new Schedule;
    $schedule->forceFill([
        'client_id' => $client->id,
        'booked_by' => $client->id,
        'booked_by_type' => BookedByType::Client,
        'title' => 'Showroom visit',
        'arrival_at' => CarbonImmutable::parse($at, 'Asia/Manila')->utc(),
        'status' => $status,
        'assigned_to' => $sales->id,
        'cancelled_at' => $status === ScheduleStatus::Cancelled ? now() : null,
        'cancel_reason' => $status === ScheduleStatus::Cancelled ? 'Plans changed' : null,
    ])->save();

    return $schedule;
}

it('lists the sales members by name, each with the appointments still to happen with them', function (): void {
    $sam = User::factory()->role(Role::Sales)->create(['name' => 'Sam Sales']);
    $ana = User::factory()->role(Role::Sales)->create(['name' => 'Ana Sales']);
    User::factory()->role(Role::Marketing)->create(['name' => 'Mara Marketing']);
    salesLoad($sam, ScheduleStatus::AwaitingSales);
    salesLoad($sam, ScheduleStatus::Confirmed);
    // Neither counts: one is over, one has already happened.
    salesLoad($sam, ScheduleStatus::Cancelled);
    salesLoad($sam, ScheduleStatus::Confirmed, '2026-10-14 11:00');

    $this->actingAs(inquiryClient(Role::Marketing))->getJson(route('schedule.sales'))
        ->assertOk()
        ->assertExactJson(['sales' => [
            ['id' => $ana->id, 'name' => 'Ana Sales', 'openAppointmentCount' => 0],
            ['id' => $sam->id, 'name' => 'Sam Sales', 'openAppointmentCount' => 2],
        ]]);
});

it('finds a sales member by name or email', function (): void {
    $sam = User::factory()->role(Role::Sales)->create(['name' => 'Sam Sales', 'email' => 'sam@regray.example']);
    User::factory()->role(Role::Sales)->create(['name' => 'Ana Sales', 'email' => 'ana@regray.example']);
    $marketing = inquiryClient(Role::Marketing);

    foreach (['sam', 'SAM@REGRAY'] as $search) {
        $this->actingAs($marketing)->getJson(route('schedule.sales', ['search' => $search]))
            ->assertOk()
            ->assertJsonPath('sales.*.id', [$sam->id]);
    }

    $this->actingAs($marketing)->getJson(route('schedule.sales', ['search' => '%']))
        ->assertOk()
        ->assertExactJson(['sales' => []]);
});

it('keeps the picker from everyone who does not assign appointments', function (Role $role): void {
    $this->actingAs(inquiryClient($role))->getJson(route('schedule.sales'))->assertForbidden();
})->with([Role::Client, Role::Sales, Role::Owner, Role::Technical, Role::Accounting, Role::Logistics]);

it('opens the picker to the admin', function (): void {
    User::factory()->role(Role::Sales)->create();

    $this->actingAs(inquiryClient(Role::Admin))->getJson(route('schedule.sales'))
        ->assertOk()
        ->assertJsonCount(1, 'sales');
});
