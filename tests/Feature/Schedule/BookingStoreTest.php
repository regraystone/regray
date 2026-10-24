<?php

use App\Actions\Schedule\BookScheduleAction;
use App\Enums\BookedByType;
use App\Enums\ConversationType;
use App\Enums\MessageType;
use App\Enums\Role;
use App\Enums\ScheduleStatus;
use App\Events\ScheduleBooked;
use App\Listeners\PostScheduleCard;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Schedule;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Support\SessionKey;

/*
 * Book an appointment: a client's booking is saved as a pending schedule and
 * announced with a schedule card in their Regray Team thread. "Now" is
 * Wednesday 14 October 2026, 13:05 in Manila. A client books from tomorrow
 * on, staff from today on.
 */

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->travelTo(CarbonImmutable::parse('2026-10-14 13:05', 'Asia/Manila'));
});

/**
 * A valid booking in the shape the page posts, with `$overrides`.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function bookingInput(array $overrides = []): array
{
    return [
        'date' => '2026-10-15',
        'arrivalTime' => '10:00',
        'purpose' => 'Meet about a countertop project',
        'notes' => '',
        ...$overrides,
    ];
}

it('saves a client booking as their own pending schedule, ignoring any client id sent', function (): void {
    $client = inquiryClient();
    $other = inquiryClient();

    $this->actingAs($client)
        ->from(route('profile.schedule.book'))
        ->post(route('schedule.book.store'), bookingInput([
            'purpose' => '  Meet about a countertop project  ',
            'notes' => 'Bringing two samples',
            'client_id' => $other->id,
            'clientId' => $other->id,
            'user_id' => $other->id,
        ]))
        ->assertRedirect(route('profile.schedule.book'))
        ->assertSessionHasNoErrors()
        ->assertSessionHas(SessionKey::FLASH_DATA.'.booking.status', 'success');

    $schedule = Schedule::query()->sole();

    expect($schedule->client_id)->toBe($client->id)
        ->and($schedule->booked_by)->toBe($client->id)
        ->and($schedule->booked_by_type)->toBe(BookedByType::Client)
        ->and($schedule->inquiry_id)->toBeNull()
        ->and($schedule->status)->toBe(ScheduleStatus::Pending)
        ->and($schedule->title)->toBe('Meet about a countertop project')
        ->and($schedule->notes)->toBe('Bringing two samples')
        ->and($schedule->arrival_at->equalTo(CarbonImmutable::parse('2026-10-15 10:00', 'Asia/Manila')))->toBeTrue();
});

it('stores a 10:00 Manila booking as 02:00 UTC', function (): void {
    $this->actingAs(inquiryClient())->post(route('schedule.book.store'), bookingInput());

    expect((string) DB::table('schedules')->value('arrival_at'))->toStartWith('2026-10-15 02:00:00');
});

it('shows the booking on the schedule and in Upcoming as pending', function (): void {
    $client = inquiryClient();

    $this->actingAs($client)->post(route('schedule.book.store'), bookingInput());

    $props = $this->actingAs($client)
        ->get(route('profile.schedule', ['from' => '2026-10-12T00:00:00+08:00', 'to' => '2026-10-19T00:00:00+08:00']))
        ->assertOk()
        ->viewData('page')['props'];

    foreach (['schedules', 'upcoming'] as $prop) {
        expect($props[$prop])->toHaveCount(1)
            ->and($props[$prop][0]['title'])->toBe('Meet about a countertop project')
            ->and($props[$prop][0]['status'])->toBe(ScheduleStatus::Pending->value);
    }
});

it('posts one pending schedule card into the Regray Team thread, and a retry adds none', function (): void {
    $client = inquiryClient();

    $this->actingAs($client)->post(route('schedule.book.store'), bookingInput());

    $schedule = Schedule::query()->sole();
    $thread = Conversation::query()
        ->where('type', ConversationType::Support->value)
        ->where('support_client_id', $client->id)
        ->sole();

    app(PostScheduleCard::class)->handle(new ScheduleBooked($schedule));

    $card = Message::query()->where('conversation_id', $thread->id)->sole();

    expect($card->type)->toBe(MessageType::ScheduleCard)
        ->and($card->schedule_id)->toBe($schedule->id)
        ->and($card->announced_status)->toBe(ScheduleStatus::Pending->value)
        ->and($card->sender_id)->toBeNull();
});

it('refuses a booking the rules do not allow, and saves nothing', function (array $overrides, string $field): void {
    $this->actingAs(inquiryClient())
        ->post(route('schedule.book.store'), bookingInput($overrides))
        ->assertSessionHasErrors($field);

    expect(Schedule::query()->count())->toBe(0);
})->with([
    'yesterday' => [['date' => '2026-10-13'], 'date'],
    'after closing' => [['arrivalTime' => '18:15'], 'arrivalTime'],
    'before opening' => [['arrivalTime' => '09:30'], 'arrivalTime'],
    'off the 30-minute grid' => [['arrivalTime' => '10:15'], 'arrivalTime'],
    'an empty purpose' => [['purpose' => ''], 'purpose'],
    'an 81-character purpose' => [['purpose' => str_repeat('a', 81)], 'purpose'],
    'a 501-character note' => [['notes' => str_repeat('a', 501)], 'notes'],
]);

it('refuses a client booking for today, and saves nothing', function (): void {
    $this->actingAs(inquiryClient())
        ->post(route('schedule.book.store'), bookingInput(['date' => '2026-10-14', 'arrivalTime' => '13:30']))
        ->assertSessionHasErrors(['date' => 'Choose a day from tomorrow on']);

    expect(Schedule::query()->count())->toBe(0);
});

it('accepts and saves a client booking for tomorrow', function (): void {
    $this->actingAs(inquiryClient())
        ->post(route('schedule.book.store'), bookingInput(['date' => '2026-10-15', 'arrivalTime' => '10:00']))
        ->assertSessionHasNoErrors();

    expect(Schedule::query()->sole()->arrival_at->equalTo(CarbonImmutable::parse('2026-10-15 10:00', 'Asia/Manila')))->toBeTrue();
});

// Staff saving is still a stub (TODO(staff-booking)), so these assert on validation only.
it('lets staff book a slot still ahead today and the last slot of the day', function (string $time): void {
    $this->actingAs(inquiryClient(Role::Marketing))
        ->post(route('schedule.book.store'), bookingInput(['date' => '2026-10-14', 'arrivalTime' => $time]))
        ->assertSessionHasNoErrors();
})->with(['13:30', '18:00']);

it('refuses staff a time already past today', function (): void {
    $this->actingAs(inquiryClient(Role::Marketing))
        ->post(route('schedule.book.store'), bookingInput(['date' => '2026-10-14', 'arrivalTime' => '12:00']))
        ->assertSessionHasErrors(['arrivalTime' => 'That time has already passed'])
        ->assertSessionDoesntHaveErrors('date');
});

it('refuses yesterday for a client and for staff', function (Role $role, string $message): void {
    $this->actingAs(inquiryClient($role))
        ->post(route('schedule.book.store'), bookingInput(['date' => '2026-10-13']))
        ->assertSessionHasErrors(['date' => $message]);

    expect(Schedule::query()->count())->toBe(0);
})->with([
    'a client' => [Role::Client, 'Choose a day from tomorrow on'],
    'staff' => [Role::Marketing, 'Choose a day from today on'],
]);

it('flashes an error and keeps the page when the write fails', function (): void {
    $this->mock(BookScheduleAction::class)
        ->shouldReceive('execute')
        ->andThrow(new RuntimeException('database down'));

    $this->actingAs(inquiryClient())
        ->from(route('profile.schedule.book'))
        ->post(route('schedule.book.store'), bookingInput())
        ->assertRedirect(route('profile.schedule.book'))
        ->assertSessionHas(SessionKey::FLASH_DATA.'.booking.status', 'error');
});

it('redirects a guest to log in', function (): void {
    $this->post(route('schedule.book.store'), bookingInput())->assertRedirect(route('login'));

    expect(Schedule::query()->count())->toBe(0);
});

// TODO(staff-booking): when staff can book for a client, cover it here:
//
// it('books for the client a staff member picks, booked by the staff member', function (): void {
//     $staff = inquiryClient(Role::Marketing);
//     $client = inquiryClient();
//
//     $this->actingAs($staff)
//         ->post(route('schedule.book.store'), bookingInput(['clientId' => $client->id]))
//         ->assertSessionHasNoErrors();
//
//     $schedule = Schedule::query()->sole();
//
//     expect($schedule->client_id)->toBe($client->id)
//         ->and($schedule->booked_by)->toBe($staff->id)
//         ->and($schedule->booked_by_type)->toBe(BookedByType::Staff);
// });
