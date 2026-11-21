<?php

use App\Enums\ConversationMemberRole;
use App\Enums\Role;
use App\Events\Message\UserTyping;
use App\Models\Conversation;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Laravel\Reverb\Contracts\ApplicationProvider;

/*
 * "{name} is typing…" is announced by the server, under the signed-in account,
 * and only for someone who may write in the thread now. Browsers cannot
 * whisper on a channel themselves.
 */
beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    Event::fake([UserTyping::class]);
});

function signalTyping(User $user, Conversation $conversation, array $body = []): TestResponse
{
    return test()->actingAs($user)->postJson(route('inbox.typing', $conversation), $body);
}

it('announces the typist by their own account, whatever the browser claims', function (): void {
    $client = inquiryClient();
    $thread = supportThreadFor($client);
    messagingStaff(Role::Marketing, 'Mara');

    signalTyping($client, $thread, ['userId' => 999, 'name' => 'Mara'])
        ->assertNoContent();

    Event::assertDispatchedTimes(UserTyping::class, 1);
    Event::assertDispatched(UserTyping::class, fn (UserTyping $event): bool => $event->conversationId === $thread->id
        && $event->broadcastWith() === ['userId' => $client->id, 'name' => $client->name]);
});

it('announces no staff name on a Regray Team thread until it is claimed, and then only the assignee', function (): void {
    $client = inquiryClient();
    $thread = supportThreadFor($client);
    $marketing = messagingStaff(Role::Marketing, 'Mara');
    $otherMarketing = messagingStaff(Role::Marketing, 'Mike');

    signalTyping($marketing, $thread)->assertConflict();
    Event::assertNotDispatched(UserTyping::class);

    $thread->forceFill(['assigned_to' => $marketing->id, 'assigned_at' => now()])->save();

    signalTyping($otherMarketing, $thread)->assertConflict();
    Event::assertNotDispatched(UserTyping::class);

    signalTyping($marketing, $thread)->assertNoContent();
    Event::assertDispatched(UserTyping::class, fn (UserTyping $event): bool => $event->userId === $marketing->id
        && $event->userName === 'Mara');
});

it('refuses anyone who may not write in the conversation', function (): void {
    $client = inquiryClient();
    $member = messagingStaff(Role::Sales, 'Sam');
    $viewer = messagingStaff(Role::Sales, 'Vic');
    $outsider = messagingStaff(Role::Sales, 'Otto');
    seedInquiryCatalog();
    $inquiry = makeInquiry($client);
    letStaffRead($inquiry, $member, $viewer, $outsider);
    $group = groupConversation([$client, $member, $viewer], [$viewer->id => ConversationMemberRole::Viewer], ['inquiry_id' => $inquiry->id]);

    // Reads the inquiry, but is not in its group chat.
    signalTyping($outsider, $group)->assertForbidden();
    signalTyping($viewer, $group)->assertForbidden();
    signalTyping(messagingStaff(Role::Owner, 'Olive'), $group)->assertForbidden();
    signalTyping(inquiryClient(), supportThreadFor($client))->assertForbidden();
    Event::assertNotDispatched(UserTyping::class);

    signalTyping($member, $group)->assertNoContent();
    signalTyping($client, $group)->assertNoContent();
    Event::assertDispatchedTimes(UserTyping::class, 2);
});

it('refuses an account that has been turned off', function (): void {
    $client = inquiryClient();
    $thread = supportThreadFor($client);
    $client->forceFill(['is_active' => false])->save();

    signalTyping($client, $thread)->assertUnauthorized();
    Event::assertNotDispatched(UserTyping::class);
});

it('leaves out the browser that typed, and takes no whispers on the socket', function (): void {
    $client = inquiryClient();
    $thread = supportThreadFor($client);

    $this->actingAs($client)
        ->withHeader('X-Socket-ID', '1234.5678')
        ->postJson(route('inbox.typing', $thread))
        ->assertNoContent();

    Event::assertDispatched(UserTyping::class, fn (UserTyping $event): bool => $event->socket === '1234.5678');

    config(['reverb.apps.apps.0.key' => 'test-key']);
    expect(app(ApplicationProvider::class)->findByKey('test-key')->acceptClientEventsFrom())->toBe('none');
});
