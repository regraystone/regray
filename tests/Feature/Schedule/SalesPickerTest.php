<?php

use App\Enums\BookedByType;
use App\Enums\Role;
use App\Enums\ScheduleStatus;
use App\Models\Inquiry;
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
            ['id' => $ana->id, 'name' => 'Ana Sales', 'openAppointmentCount' => 0, 'clash' => null],
            ['id' => $sam->id, 'name' => 'Sam Sales', 'openAppointmentCount' => 2, 'clash' => null],
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

it('warns of a sales member with an appointment within an hour of the time asked about, and only them', function (): void {
    $sam = User::factory()->role(Role::Sales)->create(['name' => 'Sam Sales']);
    $ana = User::factory()->role(Role::Sales)->create(['name' => 'Ana Sales']);
    $lee = User::factory()->role(Role::Sales)->create(['name' => 'Lee Sales']);
    salesLoad($sam, ScheduleStatus::Confirmed, '2026-10-16 10:30')->update(['title' => 'Slab viewing']);
    // Exactly an hour away is no overlap; a cancelled one never is.
    salesLoad($ana, ScheduleStatus::AwaitingSales, '2026-10-16 11:00');
    salesLoad($lee, ScheduleStatus::Cancelled, '2026-10-16 10:00');

    $sales = collect($this->actingAs(inquiryClient(Role::Marketing))
        ->getJson(route('schedule.sales', ['date' => '2026-10-16', 'time' => '10:00']))
        ->assertOk()
        ->json('sales'))->keyBy('name');

    expect($sales['Sam Sales']['clash'])->toBe(['arrivalAt' => '2026-10-16T10:30:00+08:00', 'time' => '10:30', 'title' => 'Slab viewing'])
        ->and($sales['Ana Sales']['clash'])->toBeNull()
        ->and($sales['Lee Sales']['clash'])->toBeNull();
});

it('reads the time from the appointment being assigned, which never clashes with itself', function (): void {
    $sam = User::factory()->role(Role::Sales)->create(['name' => 'Sam Sales']);
    $marketing = inquiryClient(Role::Marketing);
    $assigning = salesLoad($sam, ScheduleStatus::AwaitingSales, '2026-10-16 10:00');
    $assigning->update(['booked_by' => $marketing->id, 'booked_by_type' => BookedByType::Staff]);

    $clash = fn () => $this->actingAs($marketing)->getJson(route('schedule.sales', ['scheduleId' => $assigning->id]))->assertOk()->json('sales.0.clash');

    expect($clash())->toBeNull();

    salesLoad($sam, ScheduleStatus::Confirmed, '2026-10-16 09:30');

    expect($clash()['time'])->toBe('09:30');

    // One this marketer does not read (another marketer booked it) gives no time to check.
    $hidden = salesLoad($sam, ScheduleStatus::Confirmed, '2026-10-20 10:00');
    $hidden->update(['booked_by' => inquiryClient(Role::Marketing)->id, 'booked_by_type' => BookedByType::Staff]);
    salesLoad($sam, ScheduleStatus::Confirmed, '2026-10-20 10:15');

    expect($this->actingAs($marketing)->getJson(route('schedule.sales', ['scheduleId' => $hidden->id]))->json('sales.0.clash'))->toBeNull();
});

/** A confirmed visit of an inquiry, at `$at` in Manila. */
function inquiryVisitAt(Inquiry $inquiry, string $at, string $title = 'Site ocular'): Schedule
{
    $schedule = new Schedule;
    $schedule->forceFill([
        'inquiry_id' => $inquiry->id,
        'booked_by_type' => BookedByType::Staff,
        'title' => $title,
        'arrival_at' => CarbonImmutable::parse($at, 'Asia/Manila')->utc(),
        'status' => ScheduleStatus::Confirmed,
    ])->save();

    return $schedule;
}

it('counts the visits of the inquiries a sales member handles or opened as theirs', function (): void {
    seedInquiryCatalog();
    $sam = User::factory()->role(Role::Sales)->create(['name' => 'Sam Sales']);
    $ana = User::factory()->role(Role::Sales)->create(['name' => 'Ana Sales']);
    $lee = User::factory()->role(Role::Sales)->create(['name' => 'Lee Sales']);
    $handled = makeInquiry(inquiryClient());
    assignInquiry($handled, $sam);
    inquiryVisitAt($handled, '2026-10-16 10:15', 'Site ocular');
    $opened = makeInquiry(inquiryClient());
    $opened->forceFill(['created_by' => $ana->id])->save();
    inquiryVisitAt($opened, '2026-10-16 09:45', 'Kitchen measuring');
    // Nobody's: Lee neither handles nor opened it.
    inquiryVisitAt(makeInquiry(inquiryClient()), '2026-10-16 10:00');

    $sales = collect($this->actingAs(inquiryClient(Role::Marketing))
        ->getJson(route('schedule.sales', ['date' => '2026-10-16', 'time' => '10:00']))
        ->assertOk()
        ->json('sales'))->keyBy('name');

    expect($sales['Sam Sales']['clash']['title'])->toBe('Site ocular')
        ->and($sales['Ana Sales']['clash']['title'])->toBe('Kitchen measuring')
        ->and($sales['Lee Sales']['clash'])->toBeNull();
});

it('warns the sales member confirming an appointment of theirs it would overlap, and only them', function (): void {
    $sam = User::factory()->role(Role::Sales)->create(['name' => 'Sam Sales']);
    $other = User::factory()->role(Role::Sales)->create();
    $confirming = salesLoad($sam, ScheduleStatus::AwaitingSales, '2026-10-16 10:00');

    $this->actingAs($sam)->getJson(route('schedule.clash', $confirming))->assertOk()->assertExactJson(['clash' => null]);

    salesLoad($sam, ScheduleStatus::Confirmed, '2026-10-16 10:30')->update(['title' => 'Slab viewing']);

    $this->actingAs($sam)->getJson(route('schedule.clash', $confirming))
        ->assertOk()
        ->assertJsonPath('clash.time', '10:30')
        ->assertJsonPath('clash.title', 'Slab viewing');

    // Another sales member does not read it at all; marketing reads it but does not confirm.
    $this->actingAs($other)->getJson(route('schedule.clash', $confirming))->assertNotFound();
    $this->actingAs(inquiryClient(Role::Marketing))->getJson(route('schedule.clash', $confirming))->assertForbidden();
});
