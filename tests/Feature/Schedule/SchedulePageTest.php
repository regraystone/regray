<?php

use App\Enums\BookedByType;
use App\Enums\Role;
use App\Enums\ScheduleStatus;
use App\Models\Inquiry;
use App\Models\Schedule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->travelTo(CarbonImmutable::parse('2026-10-14 10:00', 'Asia/Manila'));
});

/** A visit for `$client` arriving at `$at`, Manila time. */
function scheduledVisit(User $client, string $at, ScheduleStatus $status = ScheduleStatus::Pending, ?Inquiry $inquiry = null): Schedule
{
    $schedule = new Schedule;
    $schedule->forceFill([
        'client_id' => $client->id,
        'booked_by' => $client->id,
        'booked_by_type' => BookedByType::Client,
        'inquiry_id' => $inquiry?->id,
        'title' => "Visit at {$at}",
        'arrival_at' => CarbonImmutable::parse($at, 'Asia/Manila')->utc(),
        'status' => $status,
    ])->save();

    return $schedule;
}

/** The ids in one of the page's props. */
function propIds(TestResponse $response, string $prop): array
{
    return array_column($response->viewData('page')['props'][$prop], 'id');
}

/** A range around the frozen "now", well inside the 62-day limit. */
function octoberRange(): array
{
    return ['from' => '2026-10-01T00:00:00+08:00', 'to' => '2026-11-01T00:00:00+08:00'];
}

it('shows a client only their own visits, whatever client_id the URL names', function (): void {
    $client = inquiryClient();
    $other = inquiryClient();
    $own = scheduledVisit($client, '2026-10-15 10:00');
    scheduledVisit($other, '2026-10-15 09:00');

    $response = $this->actingAs($client)
        ->get(route('profile.schedule', [...octoberRange(), 'client_id' => $other->id]))
        ->assertOk();

    expect(propIds($response, 'schedules'))->toBe([$own->id])
        ->and(propIds($response, 'upcoming'))->toBe([$own->id]);
});

it('shows staff every client\'s visits', function (Role $role): void {
    $first = scheduledVisit(inquiryClient(), '2026-10-15 09:00');
    $second = scheduledVisit(inquiryClient(), '2026-10-16 09:00');

    $response = $this->actingAs(User::factory()->role($role)->create())
        ->get(route('schedule.index', octoberRange()))
        ->assertOk();

    expect(propIds($response, 'schedules'))->toBe([$first->id, $second->id]);
})->with([
    'sales' => [Role::Sales],
    'owner' => [Role::Owner],
    'admin' => [Role::Admin],
]);

it('lists every status inside the range, ordered by arrival, with `to` excluded', function (): void {
    $client = inquiryClient();
    scheduledVisit($client, '2026-09-30 23:59');
    $cancelled = scheduledVisit($client, '2026-10-20 09:00', ScheduleStatus::Cancelled);
    $first = scheduledVisit($client, '2026-10-01 00:00', ScheduleStatus::Completed);
    $confirmed = scheduledVisit($client, '2026-10-31 23:00', ScheduleStatus::Confirmed);
    scheduledVisit($client, '2026-11-01 00:00');

    $response = $this->actingAs($client)->get(route('profile.schedule', octoberRange()));

    expect(propIds($response, 'schedules'))->toBe([$first->id, $cancelled->id, $confirmed->id]);
});

it('reads the mini calendar\'s six weeks for this month when no range is given', function (): void {
    $client = inquiryClient();
    $gridStart = scheduledVisit($client, '2026-09-28 09:00');
    $gridEnd = scheduledVisit($client, '2026-11-08 18:00');
    scheduledVisit($client, '2026-11-09 09:00');

    $response = $this->actingAs($client)->get(route('profile.schedule'));

    expect(propIds($response, 'schedules'))->toBe([$gridStart->id, $gridEnd->id]);
});

it('accepts a range of 62 days and refuses a longer one', function (): void {
    $client = inquiryClient();

    $this->actingAs($client)
        ->get(route('profile.schedule', ['from' => '2026-10-01', 'to' => '2026-12-02']))
        ->assertOk();

    $this->actingAs($client)
        ->get(route('profile.schedule', ['from' => '2026-10-01', 'to' => '2026-12-03']))
        ->assertSessionHasErrors('to');
});

it('refuses a range that ends before it starts', function (): void {
    $this->actingAs(inquiryClient())
        ->get(route('profile.schedule', ['from' => '2026-10-10', 'to' => '2026-10-01']))
        ->assertSessionHasErrors('to');
});

describe('upcoming', function (): void {
    it('lists the next three pending or confirmed visits from an hour ago on, soonest first', function (): void {
        $client = inquiryClient();
        scheduledVisit($client, '2026-10-14 08:59');
        scheduledVisit($client, '2026-10-14 09:30', ScheduleStatus::Completed);
        scheduledVisit($client, '2026-10-14 09:45', ScheduleStatus::Cancelled);
        $later = scheduledVisit($client, '2026-11-20 10:00');
        $tomorrowConfirmed = scheduledVisit($client, '2026-10-15 10:00', ScheduleStatus::Confirmed);
        $ongoing = scheduledVisit($client, '2026-10-14 09:15', ScheduleStatus::Confirmed);
        $tomorrowPending = scheduledVisit($client, '2026-10-15 10:00');

        $response = $this->actingAs($client)->get(route('profile.schedule'));

        expect(propIds($response, 'upcoming'))->toBe([$ongoing->id, $tomorrowConfirmed->id, $tomorrowPending->id])
            ->and(propIds($response, 'upcoming'))->not->toContain($later->id);
    });

    it('is empty when nothing is coming up', function (): void {
        $client = inquiryClient();
        scheduledVisit($client, '2026-10-13 10:00');
        scheduledVisit($client, '2026-10-20 10:00', ScheduleStatus::Cancelled);

        $response = $this->actingAs($client)->get(route('profile.schedule'));

        expect(propIds($response, 'upcoming'))->toBe([]);
    });
});

it('shows a booking without an inquiry, and an inquiry visit with its reference', function (): void {
    $client = inquiryClient();
    $inquiry = new Inquiry;
    $inquiry->forceFill([
        'reference' => 'INQ-S1',
        'client_id' => $client->id,
        'name' => 'Kitchen',
        'location' => 'Makati',
        'sales_status_changed_at' => now(),
        'payment_status_changed_at' => now(),
        'process_status_changed_at' => now(),
    ])->save();
    $booking = scheduledVisit($client, '2026-10-15 10:00');
    scheduledVisit($client, '2026-10-16 10:00', inquiry: $inquiry);

    $schedules = $this->actingAs($client)
        ->get(route('profile.schedule', octoberRange()))
        ->viewData('page')['props']['schedules'];

    expect($schedules[0])->toBe([
        'id' => $booking->id,
        'title' => 'Visit at 2026-10-15 10:00',
        // The same instant, shown in Manila.
        'arrivalAt' => '2026-10-15T10:00:00+08:00',
        'status' => 'pending',
        'notes' => null,
        'inquiryReference' => null,
    ])->and($schedules[1]['inquiryReference'])->toBe('INQ-S1');
});
