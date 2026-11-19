<?php

use App\Enums\BookedByType;
use App\Enums\ClientKind;
use App\Enums\ConversationType;
use App\Enums\MessageType;
use App\Enums\Role;
use App\Enums\ScheduleStatus;
use App\Events\ScheduleBooked;
use App\Listeners\PostScheduleCard;
use App\Models\Client;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Schedule;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Support\SessionKey;

/*
 * Book an appointment: a client's booking is saved as a pending schedule and
 * announced with a schedule card in their Regray Team thread. Marketing books
 * for a client and assigns it to a sales member, who confirms it: until then
 * it awaits sales, and the client is not told. "Now" is
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
    $client = inquiryClient();
    makeLead($client, $staff);
    $sales = inquiryClient(Role::Sales);
    $book = fn (array $overrides) => $this->actingAs($staff)->post(route('schedule.book.store'), bookingInput(['date' => '2026-10-14', 'clientId' => $client->id, 'salesId' => $sales->id, ...$overrides]));

    $book(['arrivalTime' => '13:30'])->assertSessionHasNoErrors();
    $book(['arrivalTime' => '18:00'])->assertSessionHasNoErrors();
    $book(['arrivalTime' => '12:00'])
        ->assertSessionHasErrors(['arrivalTime' => 'That time has already passed'])
        ->assertSessionDoesntHaveErrors('date');
    $book(['date' => '2026-10-13'])->assertSessionHasErrors(['date' => 'Choose a day from today on']);
});

it('books for the client marketing chose, assigned to the sales member it chose, and tells the client nothing yet', function (): void {
    $staff = inquiryClient(Role::Marketing);
    $client = inquiryClient();
    makeLead($client, $staff);
    $sales = inquiryClient(Role::Sales);

    $this->actingAs($staff)
        ->post(route('schedule.book.store'), bookingInput(['clientId' => $client->id, 'salesId' => $sales->id]))
        ->assertSessionHasNoErrors()
        ->assertSessionHas(SessionKey::FLASH_DATA.'.booking.status', 'success');

    $schedule = Schedule::query()->sole();

    expect($schedule->client_id)->toBe($client->id)
        ->and($schedule->booked_by)->toBe($staff->id)
        ->and($schedule->booked_by_type)->toBe(BookedByType::Staff)
        ->and($schedule->status)->toBe(ScheduleStatus::AwaitingSales)
        ->and($schedule->assigned_to)->toBe($sales->id)
        ->and($schedule->assigned_by)->toBe($staff->id)
        ->and($schedule->assigned_at)->not->toBeNull()
        ->and($schedule->approved_by)->toBeNull()
        ->and($schedule->isKnownToClient())->toBeFalse()
        ->and(Message::query()->where('type', MessageType::ScheduleCard)->exists())->toBeFalse();

    // Its sales member confirms it: now the client is told, in their Regray chat.
    $this->actingAs($sales)->post(route('schedule.confirm', $schedule))->assertSessionHasNoErrors();

    $card = Message::query()->where('type', MessageType::ScheduleCard)->sole();
    $thread = Conversation::query()->find($card->conversation_id);

    expect($schedule->fresh()->status)->toBe(ScheduleStatus::Confirmed)
        ->and($schedule->fresh()->approved_by)->toBe($sales->id)
        ->and($card->announced_status)->toBe('confirmed')
        ->and($thread->type)->toBe(ConversationType::Support)
        ->and($thread->support_client_id)->toBe($client->id);
});

it('keeps booking from the staff who only view the schedule', function (Role $role): void {
    $staff = inquiryClient($role);

    $this->actingAs($staff)->get(route('schedule.index'))->assertOk();
    $this->actingAs($staff)->get(route('schedule.book'))->assertForbidden();
    $this->actingAs($staff)
        ->post(route('schedule.book.store'), bookingInput(['clientId' => inquiryClient()->id]))
        ->assertForbidden();

    expect(Schedule::query()->count())->toBe(0);
})->with([Role::Owner, Role::Sales, Role::Technical, Role::Accounting, Role::Logistics]);

it('refuses a staff booking with no client, or one naming staff instead of a client account', function (): void {
    $staff = inquiryClient(Role::Marketing);
    $sales = inquiryClient(Role::Sales);

    $this->actingAs($staff)
        ->post(route('schedule.book.store'), bookingInput(['salesId' => $sales->id]))
        ->assertSessionHasErrors(['clientId' => 'Choose the client this appointment is for']);

    $this->actingAs($staff)
        ->post(route('schedule.book.store'), bookingInput(['clientId' => inquiryClient(Role::Marketing)->id, 'salesId' => $sales->id]))
        ->assertSessionHasErrors(['clientId' => 'Choose a client with a Regray account']);

    expect(Schedule::query()->count())->toBe(0);
});

it('refuses a marketing booking with no sales member, or one naming someone not in sales', function (): void {
    $staff = inquiryClient(Role::Marketing);
    $client = inquiryClient();
    makeLead($client, $staff);

    $this->actingAs($staff)
        ->post(route('schedule.book.store'), bookingInput(['clientId' => $client->id]))
        ->assertSessionHasErrors(['salesId' => 'Choose a sales member to confirm this appointment']);

    foreach ([inquiryClient(Role::Marketing), inquiryClient(Role::Technical), inquiryClient()] as $notSales) {
        $this->actingAs($staff)
            ->post(route('schedule.book.store'), bookingInput(['clientId' => $client->id, 'salesId' => $notSales->id]))
            ->assertSessionHasErrors('salesId');
    }

    expect(Schedule::query()->count())->toBe(0);
});

it('finds the client accounts the staff member is authorized for, and keeps a client out', function (): void {
    seedInquiryCatalog();
    $client = inquiryClient();
    $client->update(['name' => 'Ramona Booker']);
    $marketing = inquiryClient(Role::Marketing);
    // Marketing finds the clients whose lead it holds, and no others.
    makeLead($client, $marketing);
    $elsewhere = inquiryClient();
    $elsewhere->update(['name' => 'Ramona Elsewhere']);
    makeLead($elsewhere, inquiryClient(Role::Marketing));

    $this->actingAs($marketing)
        ->getJson(route('schedule.book.accounts', ['search' => 'Ramona']))
        ->assertOk()
        ->assertJsonCount(1, 'accounts')
        ->assertJsonPath('accounts.0.id', $client->id);

    // Sales books nothing, so it searches no client accounts here.
    $this->actingAs(inquiryClient(Role::Sales))
        ->getJson(route('schedule.book.accounts', ['search' => 'Ramona']))
        ->assertForbidden();

    $this->actingAs($client)
        ->getJson(route('schedule.book.accounts', ['search' => 'Ramona']))
        ->assertForbidden();
});

describe('a walk-in', function (): void {
    it('books a walk-in, shows their name to staff, and never posts a card for them', function (): void {
        $marketing = inquiryClient(Role::Marketing);
        $marketing->update(['name' => 'Mara']);
        $sales = inquiryClient(Role::Sales);
        $sales->update(['name' => 'Sam']);
        $walkIn = walkInClient('Wanda Walk-in');

        $this->actingAs($marketing)
            ->post(route('schedule.book.store'), bookingInput(['directClientId' => $walkIn->id, 'salesId' => $sales->id]))
            ->assertSessionHasNoErrors();

        $schedule = Schedule::query()->sole();

        expect($schedule->client_id)->toBeNull()
            ->and($schedule->client_record_id)->toBe($walkIn->id)
            ->and($schedule->booked_by_type)->toBe(BookedByType::Staff)
            ->and($schedule->status)->toBe(ScheduleStatus::AwaitingSales)
            ->and($schedule->assigned_to)->toBe($sales->id)
            ->and(notificationBodies($sales))->toBe(['Mara has assigned you an appointment with Wanda Walk-in on Oct 15, 2026 at 10:00 AM. Confirm or decline it.']);

        $this->actingAs($sales)->post(route('schedule.confirm', $schedule))->assertSessionHasNoErrors();

        expect($schedule->fresh()->status)->toBe(ScheduleStatus::Confirmed)
            ->and(Message::query()->where('type', MessageType::ScheduleCard)->exists())->toBeFalse()
            ->and(notificationBodies($marketing))->toContain('Sam has confirmed the appointment with Wanda Walk-in on Oct 15, 2026 at 10:00 AM.');

        $range = ['from' => '2026-10-01', 'to' => '2026-11-01'];
        $row = collect($this->actingAs($marketing)->get(route('schedule.index', $range))->viewData('page')['props']['schedules'])->sole();

        expect($row['clientName'])->toBe('Wanda Walk-in')
            ->and($row['walkIn']['contacts'])->toBe([['kind' => 'phone', 'value' => $walkIn->contacts()->value('value')]])
            // Only how to reach them: a walk-in has no company or address any more.
            ->and($row['walkIn'])->toBe(['contacts' => $row['walkIn']['contacts']]);

        // The sales member confirming it reads how to reach them too.
        $salesRow = collect($this->actingAs($sales)->get(route('schedule.index', $range))->viewData('page')['props']['schedules'])->sole();

        expect($salesRow['walkIn']['contacts'])->toHaveCount(1);
    });

    it('refuses a walk-in id that names a client with an account, or nobody', function (): void {
        $marketing = inquiryClient(Role::Marketing);
        $sales = inquiryClient(Role::Sales);
        $platform = Client::query()->forceCreate(['kind' => ClientKind::Platform, 'user_id' => inquiryClient()->id, 'full_name' => 'Has An Account']);

        foreach ([$platform->id, 999999] as $id) {
            $this->actingAs($marketing)
                ->post(route('schedule.book.store'), bookingInput(['directClientId' => $id, 'salesId' => $sales->id]))
                ->assertSessionHasErrors(['clientId' => 'Choose a walk-in client on file']);
        }

        expect(Schedule::query()->count())->toBe(0);
    });
});
