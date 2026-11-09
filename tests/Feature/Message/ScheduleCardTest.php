<?php

use App\Enums\BookedByType;
use App\Enums\MessageType;
use App\Enums\Role;
use App\Enums\ScheduleStatus;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Schedule;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

/** A visit for `$client`, announced in `$conversation` as `$announced`. */
function scheduleCard(Conversation $conversation, User $client, ?User $bookedBy, string $announced = 'confirmed'): Message
{
    $schedule = new Schedule;
    $schedule->forceFill([
        'client_id' => $client->id,
        'booked_by' => $bookedBy?->id,
        'booked_by_type' => $bookedBy === null ? BookedByType::Client : BookedByType::Staff,
        'title' => 'Site visit',
        'arrival_at' => now()->addDays(3),
        'status' => ScheduleStatus::from($announced),
        'cancelled_at' => $announced === 'cancelled' ? now() : null,
        'cancel_reason' => 'Rain',
    ])->save();

    return Message::query()->create([
        'conversation_id' => $conversation->id,
        'type' => MessageType::ScheduleCard,
        'schedule_id' => $schedule->id,
        'announced_status' => $announced,
        'schedule_revision' => 0,
        'announced_arrival_at' => $schedule->arrival_at,
        'announced_reason' => $announced === 'cancelled' ? 'Rain' : null,
    ]);
}

it('names the staff member on a visit staff booked, for staff and for the client', function (): void {
    $client = inquiryClient();
    $staff = messagingStaff(Role::Sales, 'Sam');
    $group = groupConversation([$staff, $client]);
    scheduleCard($group, $client, bookedBy: $staff);

    foreach ([$staff, $client] as $viewer) {
        $this->actingAs($viewer)->getJson(route('inbox.thread', $group))
            ->assertJsonPath('messages.0.scheduleCard.bookedByType', 'staff')
            ->assertJsonPath('messages.0.scheduleCard.bookedByName', 'Sam');
    }
});

it('names nobody on a visit the client booked', function (): void {
    $client = inquiryClient();
    $staff = messagingStaff(Role::Sales);
    $group = groupConversation([$staff, $client]);
    scheduleCard($group, $client, bookedBy: null);

    $this->actingAs($staff)->getJson(route('inbox.thread', $group))
        ->assertJsonPath('messages.0.scheduleCard.bookedByType', 'client')
        ->assertJsonPath('messages.0.scheduleCard.bookedByName', null);
});

it('sends the cancel reason only on a cancelled card', function (string $announced, ?string $reason): void {
    $client = inquiryClient();
    $group = groupConversation([$client]);
    scheduleCard($group, $client, bookedBy: null, announced: $announced);

    $this->actingAs($client)->getJson(route('inbox.thread', $group))
        ->assertJsonPath('messages.0.scheduleCard.cancelReason', $reason);
})->with([
    'confirmed' => ['confirmed', null],
    'cancelled' => ['cancelled', 'Rain'],
]);

it('leaves the card empty for a client whose visit it is not', function (): void {
    $owner = inquiryClient();
    $otherClient = inquiryClient();
    $group = groupConversation([$owner, $otherClient]);
    scheduleCard($group, $owner, bookedBy: messagingStaff(Role::Sales));

    $this->actingAs($otherClient)->getJson(route('inbox.thread', $group))
        ->assertJsonPath('messages.0.type', 'schedule_card')
        ->assertJsonPath('messages.0.scheduleCard', null);
});

it('sends a card exactly the fields the thread draws', function (): void {
    $client = inquiryClient();
    $group = groupConversation([$client]);
    scheduleCard($group, $client, bookedBy: null);

    $card = $this->actingAs($client)->getJson(route('inbox.thread', $group))->json('messages.0.scheduleCard');

    expect(array_keys($card))->toBe([
        'id', 'title', 'arrivalAt', 'announcedStatus', 'bookedByType', 'bookedByName', 'inquiryReference', 'cancelReason', 'changeReason', 'occurredAt',
    ]);
});
