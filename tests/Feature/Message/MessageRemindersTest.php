<?php

use App\Enums\MessageType;
use App\Enums\Role;
use App\Models\Conversation;
use App\Models\ConversationMember;
use App\Models\Message;
use App\Models\User;
use App\Notifications\AwaitingReplyNotification;
use App\Notifications\ClientsWaitingNotification;
use App\Notifications\UnreadMessagesNotification;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Notification;

/*
 * `messages:send-reminders`: an email once a message has waited unread past
 * `messaging.reminders.after_minutes` (3 by default), to the staff member who has
 * not read it, and to the support pool for a client nobody has claimed. A client
 * is emailed after `client_after_minutes` (30 by default) if they have not replied.
 */

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    Notification::fake();
});

function runReminders(): void
{
    test()->artisan('messages:send-reminders')->assertSuccessful();
}

/** A client's Regray Team thread, claimed by a marketing user. */
function claimedThread(User $client, User $marketing): Conversation
{
    $thread = supportThreadFor($client);
    test()->actingAs($marketing)->postJson(route('inbox.claim', $thread))->assertOk();

    return $thread;
}

it('emails a client who has not replied after 30 minutes, once', function (): void {
    $client = inquiryClient();
    $marketing = messagingStaff(Role::Marketing, 'Mara');
    $thread = claimedThread($client, $marketing);
    textMessage($thread, $marketing, 'Your quote is ready.');

    $this->travel(29)->minutes();
    runReminders();
    Notification::assertNothingSentTo($client);

    $this->travel(2)->minutes();
    runReminders();
    Notification::assertNotSentTo($client, UnreadMessagesNotification::class);
    Notification::assertSentTo($client, AwaitingReplyNotification::class, function (AwaitingReplyNotification $notification) use ($thread): bool {
        return $notification->threads === [[
            'conversationId' => $thread->id,
            'name' => 'Regray',
            'count' => 1,
            'latestId' => Message::query()->where('body', 'Your quote is ready.')->value('id'),
        ]];
    });

    runReminders();
    Notification::assertSentToTimes($client, AwaitingReplyNotification::class, 1);
});

it('emails a client who read the message but did not reply', function (): void {
    $client = inquiryClient();
    $marketing = messagingStaff(Role::Marketing);
    $thread = claimedThread($client, $marketing);
    $message = textMessage($thread, $marketing);

    ConversationMember::query()->where('conversation_id', $thread->id)->where('user_id', $client->id)
        ->update(['last_read_message_id' => $message->id]);

    $this->travel(31)->minutes();
    runReminders();

    Notification::assertSentToTimes($client, AwaitingReplyNotification::class, 1);
});

it('does not email a client who has replied', function (): void {
    $client = inquiryClient();
    $marketing = messagingStaff(Role::Marketing);
    $thread = claimedThread($client, $marketing);
    textMessage($thread, $marketing, 'Is the 12th fine?');
    $this->travel(1)->minutes();
    textMessage($thread, $client, 'Yes, thanks.');

    $this->travel(31)->minutes();
    runReminders();

    Notification::assertNothingSentTo($client);
});

it('does not count a client reply that was removed', function (): void {
    $client = inquiryClient();
    $marketing = messagingStaff(Role::Marketing);
    $thread = claimedThread($client, $marketing);
    textMessage($thread, $marketing);
    $this->travel(1)->minutes();
    textMessage($thread, $client, 'Oops')->delete();

    $this->travel(31)->minutes();
    runReminders();

    Notification::assertSentToTimes($client, AwaitingReplyNotification::class, 1);
});

it('does not email a staff member who has read the message', function (): void {
    $client = inquiryClient();
    $marketing = messagingStaff(Role::Marketing);
    $thread = claimedThread($client, $marketing);
    $message = textMessage($thread, $client);

    ConversationMember::query()->where('conversation_id', $thread->id)->where('user_id', $marketing->id)
        ->update(['last_read_message_id' => $message->id]);

    $this->travel(4)->minutes();
    runReminders();

    Notification::assertNothingSentTo($marketing);
});

it('emails again only about messages newer than the last reminder', function (): void {
    $client = inquiryClient();
    $marketing = messagingStaff(Role::Marketing);
    $thread = claimedThread($client, $marketing);
    textMessage($thread, $marketing, 'First');

    $this->travel(31)->minutes();
    runReminders();

    textMessage($thread, $marketing, 'Second');
    $this->travel(10)->minutes();
    runReminders();
    Notification::assertSentToTimes($client, AwaitingReplyNotification::class, 1);

    $this->travel(25)->minutes();
    runReminders();
    Notification::assertSentToTimes($client, AwaitingReplyNotification::class, 2);
    // The second email is about what arrived since the first, not about both.
    $second = Message::query()->where('body', 'Second')->value('id');
    Notification::assertSentTo($client, AwaitingReplyNotification::class, fn (AwaitingReplyNotification $notification): bool => $notification->threads[0]['count'] === 1
        && $notification->threads[0]['latestId'] === $second);
});

it('emails the staff member handling a thread about the client\'s unread message', function (): void {
    $client = inquiryClient();
    $client->forceFill(['name' => 'Rafael'])->save();
    $marketing = messagingStaff(Role::Marketing, 'Mara');
    $thread = claimedThread($client, $marketing);
    textMessage($thread, $client, 'Any update?');

    $this->travel(4)->minutes();
    runReminders();

    Notification::assertSentTo($marketing, UnreadMessagesNotification::class, fn (UnreadMessagesNotification $notification): bool => $notification->threads[0]['name'] === 'Rafael');
    Notification::assertNotSentTo($client, UnreadMessagesNotification::class);
});

it('emails the whole support pool, once, about a client nobody has claimed', function (): void {
    $client = inquiryClient();
    $client->forceFill(['name' => 'Rafael'])->save();
    $thread = supportThreadFor($client);
    $mara = messagingStaff(Role::Marketing, 'Mara');
    $mike = messagingStaff(Role::Marketing, 'Mike');
    $sales = messagingStaff(Role::Sales, 'Sam');
    textMessage($thread, $client, 'Hello?');
    textMessage($thread, $client, 'Anyone there?');

    $this->travel(4)->minutes();
    runReminders();

    foreach ([$mara, $mike] as $member) {
        Notification::assertSentTo($member, ClientsWaitingNotification::class, fn (ClientsWaitingNotification $notification): bool => $notification->threads === [[
            'conversationId' => $thread->id,
            'name' => 'Rafael',
            'count' => 2,
            'latestId' => Message::query()->where('body', 'Anyone there?')->value('id'),
        ]]);
    }
    Notification::assertNothingSentTo($sales);
    Notification::assertNothingSentTo($client);

    runReminders();
    Notification::assertSentToTimes($mara, ClientsWaitingNotification::class, 1);
});

it('stops emailing the pool once the thread is claimed', function (): void {
    $client = inquiryClient();
    $thread = supportThreadFor($client);
    $mara = messagingStaff(Role::Marketing, 'Mara');
    textMessage($thread, $client, 'Hello?');
    $this->actingAs($mara)->postJson(route('inbox.claim', $thread))->assertOk();

    $this->travel(4)->minutes();
    runReminders();

    Notification::assertNotSentTo($mara, ClientsWaitingNotification::class);
});

it('ignores cards, system lines, the reader\'s own messages and removed messages', function (): void {
    $client = inquiryClient();
    $marketing = messagingStaff(Role::Marketing);
    $thread = claimedThread($client, $marketing);
    textMessage($thread, $client, 'My own');
    textMessage($thread, $marketing, 'Removed')->delete();
    Message::query()->create(['conversation_id' => $thread->id, 'type' => MessageType::System, 'system_event' => 'released', 'system_user_id' => $marketing->id]);

    ConversationMember::query()->where('conversation_id', $thread->id)->where('user_id', $marketing->id)
        ->update(['last_read_message_id' => Message::query()->where('body', 'My own')->value('id')]);

    $this->travel(4)->minutes();
    runReminders();

    Notification::assertNotSentTo($marketing, UnreadMessagesNotification::class);

    $this->travel(31)->minutes();
    runReminders();

    Notification::assertNothingSentTo($client);
});

it('does not email an address that was never verified', function (): void {
    $client = User::factory()->role(Role::Client)->unverified()->create();
    $marketing = messagingStaff(Role::Marketing);
    $thread = claimedThread($client, $marketing);
    textMessage($thread, $marketing);

    $this->travel(31)->minutes();
    runReminders();

    Notification::assertNothingSentTo($client);
});

it('tells a client what is waiting for their reply, without the message text', function (): void {
    $mail = (new AwaitingReplyNotification([[
        'conversationId' => 42,
        'name' => 'Regray',
        'count' => 1,
        'latestId' => 7,
    ]]))->toMail(inquiryClient());

    expect($mail->subject)->toBe('A message is waiting for your reply')
        ->and($mail->introLines)->toContain('Regray: 1 message')
        ->and($mail->actionUrl)->toBe(route('inbox.index', ['conversation' => 42]));
});

it('puts no message text in the email, only who and how many, with a link to the thread', function (): void {
    $notification = new UnreadMessagesNotification([[
        'conversationId' => 42,
        'name' => 'Regray',
        'count' => 2,
        'latestId' => 7,
    ]]);

    $mail = $notification->toMail(inquiryClient());

    expect($mail->subject)->toBe('You have 2 new messages')
        ->and($mail->introLines)->toContain('Regray: 2 new messages')
        ->and($mail->actionUrl)->toBe(route('inbox.index', ['conversation' => 42]));
});

it('runs every minute on the scheduler', function (): void {
    $this->artisan('schedule:list')
        ->expectsOutputToContain('messages:send-reminders')
        ->assertSuccessful();

    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event): bool => str_contains((string) $event->command, 'messages:send-reminders'));

    expect($event?->expression)->toBe('* * * * *');
});
