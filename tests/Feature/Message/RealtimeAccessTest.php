<?php

use App\Enums\Role;
use App\Events\Message\MessageCreated;
use App\Events\Message\MessageUpdated;
use App\Http\Middleware\EnsureAccountActive;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Testing\TestResponse;

/*
 * Real-time chat only reaches those who may read the conversation now. A
 * channel is authorized when it is joined, so the channel carries no message
 * content: each open thread fetches what it announces over HTTP, where
 * access is checked on every request. Like BroadcastChannelsTest, this swaps
 * in the Reverb driver so /broadcasting/auth runs the real channel rules.
 */
beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);

    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => 'test-app',
    ]);
    Broadcast::purge();
    require base_path('routes/channels.php');
});

function joinChannel(User $user, string $channel): TestResponse
{
    return test()->actingAs($user)->postJson('/broadcasting/auth', [
        'channel_name' => "private-{$channel}",
        'socket_id' => '1234.5678',
    ]);
}

it('announces a new, edited or removed message by id, never with its words', function (): void {
    $client = inquiryClient();
    $thread = supportThreadFor($client);
    $message = textMessage($thread, $client, 'My kitchen measures 4 by 3 metres');

    $created = (new MessageCreated($message, $thread))->broadcastWith();
    $updated = (new MessageUpdated($message, $thread))->broadcastWith();

    expect($created)->toBe(['messageId' => $message->id, 'needsFetch' => true])
        ->and($updated)->toBe(['messageId' => $message->id])
        ->and(json_encode([$created, $updated]))->not->toContain('kitchen');

    $message->delete();

    expect((new MessageUpdated($message, $thread))->broadcastWith())->toBe(['messageId' => $message->id]);
});

it('hands an announced message to whoever may read the thread, and to no one else', function (): void {
    $client = inquiryClient();
    $thread = supportThreadFor($client);
    $message = textMessage($thread, $client, 'Is the onyx still available?');
    $marketing = messagingStaff(Role::Marketing);

    $this->actingAs($client)->getJson(route('inbox.message', [$thread, $message->id]))
        ->assertOk()
        ->assertJsonPath('message.id', $message->id)
        ->assertJsonPath('message.body', 'Is the onyx still available?');
    $this->actingAs($marketing)->getJson(route('inbox.message', [$thread, $message->id]))->assertOk();

    // Another client, and a message of another conversation.
    $this->actingAs(inquiryClient())->getJson(route('inbox.message', [$thread, $message->id]))->assertForbidden();
    $other = textMessage(supportThreadFor(inquiryClient()), $client);
    $this->actingAs($client)->getJson(route('inbox.message', [$thread, $other->id]))->assertNotFound();
});

it('stops a staff member who loses the thread from reading what is announced, on a channel joined before', function (): void {
    $client = inquiryClient();
    $thread = supportThreadFor($client);
    $marketing = messagingStaff(Role::Marketing);

    joinChannel($marketing, "conversation.{$thread->id}")->assertOk();

    // Someone else claims the thread; the earlier connection is still open.
    $thread->forceFill(['assigned_to' => messagingStaff(Role::Marketing, 'Mara')->id, 'assigned_at' => now()])->save();
    $message = textMessage($thread, $client, 'Here are the measurements');

    expect((new MessageCreated($message, $thread))->broadcastWith())->not->toHaveKey('message');
    $this->actingAs($marketing)->getJson(route('inbox.message', [$thread, $message->id]))->assertForbidden();
    $this->actingAs($marketing)->getJson(route('inbox.thread', $thread).'?after=0')->assertForbidden();

    // Joining again, as a reconnect does, is refused too.
    joinChannel($marketing, "conversation.{$thread->id}")->assertForbidden();
});

it('shows a removed message as removed to whoever fetches it', function (): void {
    $client = inquiryClient();
    $thread = supportThreadFor($client);
    $message = textMessage($thread, $client, 'Wrong thread, sorry');
    $message->delete();

    $this->actingAs($client)->getJson(route('inbox.message', [$thread, $message->id]))
        ->assertOk()
        ->assertJsonPath('message.id', $message->id)
        ->assertJsonMissing(['body' => 'Wrong thread, sorry']);
});

it('refuses every channel to an account that has been turned off', function (bool $archived): void {
    $client = inquiryClient();
    $thread = supportThreadFor($client);
    $client->forceFill($archived ? ['archived_at' => now()] : ['is_active' => false])->save();

    joinChannel($client, "user.{$client->id}")->assertUnauthorized();
    $this->assertGuest();

    // The channel rules refuse it on their own too, without the middleware.
    $this->withoutMiddleware(EnsureAccountActive::class);
    joinChannel($client, "user.{$client->id}")->assertForbidden();
    joinChannel($client, "conversation.{$thread->id}")->assertForbidden();
})->with(['deactivated' => false, 'archived' => true]);
