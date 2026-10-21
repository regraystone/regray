<?php

use App\Enums\Role;
use App\Models\ConversationMember;
use App\Models\Message;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

it('pages the newest messages first and says whether older ones remain', function (): void {
    $client = inquiryClient();
    $staff = messagingStaff(Role::Sales);
    $group = groupConversation([$staff, $client]);
    $ids = collect(range(1, 5))->map(fn (int $n): int => textMessage($group, $client, "Message {$n}")->id)->all();

    $this->actingAs($staff)->getJson(route('message.thread', ['conversation' => $group, 'limit' => 2]))
        ->assertOk()
        ->assertJsonPath('messages.*.id', [$ids[3], $ids[4]])
        ->assertJsonPath('hasMore', true);

    $this->actingAs($staff)->getJson(route('message.thread', ['conversation' => $group, 'before' => $ids[2], 'limit' => 2]))
        ->assertJsonPath('messages.*.id', [$ids[0], $ids[1]])
        ->assertJsonPath('hasMore', false);

    $this->actingAs($staff)->getJson(route('message.thread', ['conversation' => $group, 'after' => $ids[2]]))
        ->assertJsonPath('messages.*.id', [$ids[3], $ids[4]])
        ->assertJsonPath('hasMore', false);
});

it('refuses before and after together with 422', function (): void {
    $client = inquiryClient();
    $group = groupConversation([$client]);

    $this->actingAs($client)->getJson(route('message.thread', ['conversation' => $group, 'before' => 5, 'after' => 1]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('after');
});

it('refuses a client the Regray Team thread of another client with 403', function (): void {
    $thread = supportThreadFor(inquiryClient());
    $intruder = inquiryClient();

    $this->actingAs($intruder)->getJson(route('message.thread', $thread))->assertForbidden();
    $this->actingAs($intruder)->postJson(route('message.send', $thread), ['body' => 'Hi', 'clientKey' => fake()->uuid()])->assertForbidden();

    expect(Message::query()->count())->toBe(0);
});

it('returns the first message for a retried send instead of posting it twice', function (): void {
    $client = inquiryClient();
    $thread = supportThreadFor($client);
    $payload = ['body' => 'Only once', 'clientKey' => fake()->uuid()];

    $first = $this->actingAs($client)->postJson(route('message.send', $thread), $payload)->assertCreated();
    $retry = $this->actingAs($client)->postJson(route('message.send', $thread), $payload)->assertOk();

    expect($retry->json('message.id'))->toBe($first->json('message.id'))
        ->and(Message::query()->count())->toBe(1);
});

it('refuses a reply to a message from another conversation with 422', function (): void {
    $client = inquiryClient();
    $thread = supportThreadFor($client);
    $elsewhere = textMessage(groupConversation([$client]), $client);

    $this->actingAs($client)->postJson(route('message.send', $thread), ['body' => 'Hi', 'clientKey' => fake()->uuid(), 'replyToId' => $elsewhere->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('replyToId');
});

it('lets the sender edit their own text message', function (): void {
    $client = inquiryClient();
    $message = textMessage(supportThreadFor($client), $client, 'Helo');

    $this->actingAs($client)->patchJson(route('message.messages.update', $message), ['body' => 'Hello'])
        ->assertOk()
        ->assertJsonPath('message.body', 'Hello')
        ->assertJsonPath('message.isEdited', true);

    expect($message->refresh()->body)->toBe('Hello')
        ->and($message->edited_at)->not->toBeNull();
});

it('refuses to edit or remove somebody else\'s message with 403', function (): void {
    $client = inquiryClient();
    $staff = messagingStaff(Role::Sales);
    $message = textMessage(groupConversation([$staff, $client]), $client, 'Mine');

    $this->actingAs($staff)->patchJson(route('message.messages.update', $message), ['body' => 'Yours'])->assertForbidden();
    $this->actingAs($staff)->deleteJson(route('message.messages.destroy', $message))->assertForbidden();

    expect($message->refresh()->body)->toBe('Mine')
        ->and($message->trashed())->toBeFalse();
});

it('leaves a tombstone without the words when a message is removed', function (): void {
    $client = inquiryClient();
    $staff = messagingStaff(Role::Sales);
    $group = groupConversation([$staff, $client]);
    $message = textMessage($group, $client, 'Oops');

    $this->actingAs($client)->deleteJson(route('message.messages.destroy', $message))->assertOk();

    expect(Message::withTrashed()->find($message->id)?->trashed())->toBeTrue();

    $this->actingAs($staff)->getJson(route('message.thread', $group))
        ->assertJsonPath('messages.0.id', $message->id)
        ->assertJsonPath('messages.0.isRemoved', true)
        ->assertJsonPath('messages.0.body', null);
});

it('cannot find a removed message to edit, 404', function (): void {
    $client = inquiryClient();
    $message = textMessage(supportThreadFor($client), $client);
    $message->delete();

    $this->actingAs($client)->patchJson(route('message.messages.update', $message->id), ['body' => 'Back'])
        ->assertNotFound();

    expect(Message::withTrashed()->find($message->id)?->body)->toBe('Hello');
});

it('never moves the read pointer backward', function (): void {
    $client = inquiryClient();
    $staff = messagingStaff(Role::Sales);
    $group = groupConversation([$staff, $client]);
    $older = textMessage($group, $client, 'One');
    $newer = textMessage($group, $client, 'Two');

    $this->actingAs($staff)->postJson(route('message.read', $group), ['lastReadMessageId' => $newer->id])->assertSuccessful();
    $this->actingAs($staff)->postJson(route('message.read', $group), ['lastReadMessageId' => $older->id])->assertSuccessful();

    $member = ConversationMember::query()->where('conversation_id', $group->id)->where('user_id', $staff->id)->sole();

    expect($member->last_read_message_id)->toBe($newer->id)
        ->and($member->last_delivered_message_id)->toBe($newer->id);
});

it('never moves the delivered pointer backward', function (): void {
    $client = inquiryClient();
    $staff = messagingStaff(Role::Sales);
    $group = groupConversation([$staff, $client]);
    $older = textMessage($group, $client, 'One');
    $newer = textMessage($group, $client, 'Two');

    $this->actingAs($staff)->postJson(route('message.delivered', $group), ['lastDeliveredMessageId' => $newer->id])->assertSuccessful();
    $this->actingAs($staff)->postJson(route('message.delivered', $group), ['lastDeliveredMessageId' => $older->id])->assertSuccessful();

    $member = ConversationMember::query()->where('conversation_id', $group->id)->where('user_id', $staff->id)->sole();

    expect($member->last_delivered_message_id)->toBe($newer->id)
        ->and($member->last_read_message_id)->toBeNull();
});

it('refuses a pointer to a message from another conversation with 422', function (): void {
    $client = inquiryClient();
    $group = groupConversation([$client]);
    $elsewhere = textMessage(supportThreadFor($client), $client);

    $this->actingAs($client)->postJson(route('message.read', $group), ['lastReadMessageId' => $elsewhere->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('lastReadMessageId');
});

it('keeps pool viewers out of the receipts the client reads', function (): void {
    $client = inquiryClient();
    $thread = supportThreadFor($client);
    textMessage($thread, $client);

    $this->actingAs(messagingStaff(Role::Marketing))->getJson(route('message.thread', $thread))->assertOk();

    $this->actingAs($client)->getJson(route('message.thread', $thread))
        ->assertJsonPath('pointers.*.userId', [$client->id]);
});
