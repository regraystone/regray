<?php

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

    // The instant is the same whatever zone the session reads it in.
    expect(DB::selectOne("SELECT to_char(arrival_at AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI:SS') AS at FROM schedules")->at)->toBe('2026-10-15 02:00:00');
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
]);

it('takes a client booking from tomorrow on, and nothing for today', function (): void {
    $client = inquiryClient();

    $this->actingAs($client)
        ->post(route('schedule.book.store'), bookingInput(['date' => '2026-10-14', 'arrivalTime' => '13:30']))
        ->assertSessionHasErrors(['date' => 'Choose a day from tomorrow on']);

    expect(Schedule::query()->count())->toBe(0);

    $this->actingAs($client)
        ->post(route('schedule.book.store'), bookingInput(['date' => '2026-10-15', 'arrivalTime' => '10:00']))
        ->assertSessionHasNoErrors();

    expect(Schedule::query()->sole()->arrival_at->equalTo(CarbonImmutable::parse('2026-10-15 10:00', 'Asia/Manila')))->toBeTrue();
});

// Staff saving is still a stub, so this asserts on validation only.
it('lets staff book a slot still ahead today, up to the last one, and not one already past or yesterday', function (): void {
    $staff = inquiryClient(Role::Marketing);
    $book = fn (array $overrides) => $this->actingAs($staff)->post(route('schedule.book.store'), bookingInput(['date' => '2026-10-14', ...$overrides]));

    $book(['arrivalTime' => '13:30'])->assertSessionHasNoErrors();
    $book(['arrivalTime' => '18:00'])->assertSessionHasNoErrors();
    $book(['arrivalTime' => '12:00'])
        ->assertSessionHasErrors(['arrivalTime' => 'That time has already passed'])
        ->assertSessionDoesntHaveErrors('date');
    $book(['date' => '2026-10-13'])->assertSessionHasErrors(['date' => 'Choose a day from today on']);
});

// TODO(staff-booking): when staff can book for a client, cover it here: the
// schedule is the picked client's, booked by the staff member.
