<?php

use App\Enums\Role;
use App\Jobs\DeliverMessageNotification;
use App\Models\Conversation;
use App\Models\InquiryHandler;
use App\Models\PendingNotification;
use App\Models\User;
use App\Notifications\MessageWaitingNotification;
use App\Services\Notification\MessageNotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/*
 * Message notifications and their emails: staff after 3 minutes, clients after
 * an hour, from the first message they have not seen, one per thread until
 * they open it. Delivery runs through `notifications:deliver-due` here; the
 * queued job does the same at the due time.
 */

beforeEach(function (): void {
    seedInquiryCatalog();
    Notification::fake();
    $this->travelTo(CarbonImmutable::parse('2026-10-14 13:00', 'Asia/Manila'));
});

/** @return array{body: string, clientKey: string} */
function notifiedMessage(string $body): array
{
    return ['body' => $body, 'clientKey' => (string) Str::uuid()];
}

function sendAs(mixed $test, User $sender, Conversation $conversation, string $body = 'Hello'): void
{
    $test->actingAs($sender)->postJson(route('inbox.send', $conversation), notifiedMessage($body))->assertCreated();
}

function deliverDue(mixed $test, int $minutesLater): void
{
    $test->travel($minutesLater)->minutes();
    $test->artisan('notifications:deliver-due')->assertSuccessful();
}

/**
 * An inquiry's group chat: its client, the marketer who made it, and sales and
 * technical assigned on the inquiry.
 *
 * @return array{group: Conversation, client: User, marketing: User, sales: User, technical: User}
 */
function inquiryGroup(): array
{
    $client = User::factory()->role(Role::Client)->create(['name' => 'Carla']);
    $marketing = messagingStaff(Role::Marketing, 'Mara');
    $sales = messagingStaff(Role::Sales, 'Sam');
    $technical = messagingStaff(Role::Technical, 'Tim');
    $inquiry = makeInquiry($client, attributes: ['lead' => makeLead($client, $marketing)]);
    assignInquiry($inquiry, $sales, $technical);

    return [
        'group' => groupConversation([$marketing, $client, $sales, $technical], attributes: ['inquiry_id' => $inquiry->id, 'name' => 'Kitchen project']),
        'client' => $client,
        'marketing' => $marketing,
        'sales' => $sales,
        'technical' => $technical,
    ];
}

it('tells every marketer once about client messages in an unclaimed thread, three minutes after the first', function (): void {
    $client = User::factory()->role(Role::Client)->create(['name' => 'Carla']);
    $thread = supportThreadFor($client);
    $mara = messagingStaff(Role::Marketing, 'Mara');
    $mike = messagingStaff(Role::Marketing, 'Mike');
    $sales = messagingStaff(Role::Sales);

    sendAs($this, $client, $thread, 'Hi');
    $this->travel(1)->minutes();
    sendAs($this, $client, $thread, 'Are you there?');
    $this->travel(1)->minutes();
    sendAs($this, $client, $thread, 'About the counter');

    deliverDue($this, 0);
    expect(notificationBodies($mara))->toBe([]);

    deliverDue($this, 1);

    expect(notificationBodies($mara))->toBe(['Carla sent a new message.'])
        ->and(notificationBodies($mike))->toBe(['Carla sent a new message.'])
        ->and(notificationBodies($sales))->toBe([]);
    Notification::assertSentToTimes($mara, MessageWaitingNotification::class, 1);
});

it('cancels the notification and its email when the recipient opens the thread first', function (): void {
    $client = User::factory()->role(Role::Client)->create();
    $thread = supportThreadFor($client);
    $mara = messagingStaff(Role::Marketing, 'Mara');
    $mike = messagingStaff(Role::Marketing, 'Mike');

    sendAs($this, $client, $thread);
    $this->travel(2)->minutes();
    $this->actingAs($mara)->getJson(route('inbox.thread', $thread))->assertOk();
    deliverDue($this, 5);

    expect(notificationBodies($mara))->toBe([])
        ->and(notificationBodies($mike))->toHaveCount(1);
    Notification::assertNotSentTo($mara, MessageWaitingNotification::class);
});

it('starts a new notification with the next message only after the recipient has opened the thread', function (): void {
    $client = User::factory()->role(Role::Client)->create(['name' => 'Carla']);
    $thread = supportThreadFor($client);
    $mara = messagingStaff(Role::Marketing, 'Mara');

    sendAs($this, $client, $thread, 'One');
    deliverDue($this, 3);
    sendAs($this, $client, $thread, 'Two, before she looked');
    deliverDue($this, 3);
    expect(notificationBodies($mara))->toHaveCount(1);

    $this->actingAs($mara)->getJson(route('inbox.thread', $thread))->assertOk();
    sendAs($this, $client, $thread, 'Three, after she looked');
    deliverDue($this, 3);

    expect(notificationBodies($mara))->toBe(['Carla sent a new message.', 'Carla sent a new message.']);
});

it('tells only the assignee about a claimed thread', function (): void {
    $client = User::factory()->role(Role::Client)->create(['name' => 'Carla']);
    $thread = supportThreadFor($client);
    $mara = messagingStaff(Role::Marketing, 'Mara');
    $mike = messagingStaff(Role::Marketing, 'Mike');
    $this->actingAs($mara)->postJson(route('inbox.claim', $thread))->assertOk();

    sendAs($this, $client, $thread);
    deliverDue($this, 3);

    expect(notificationBodies($mara))->toBe(['Carla sent a new message.'])
        ->and(notificationBodies($mike))->toBe([]);
});

it('drops a pool member\'s notification when someone else claims the thread before it is due', function (): void {
    $client = User::factory()->role(Role::Client)->create();
    $thread = supportThreadFor($client);
    $mara = messagingStaff(Role::Marketing, 'Mara');
    $mike = messagingStaff(Role::Marketing, 'Mike');

    sendAs($this, $client, $thread);
    $this->travel(1)->minutes();
    $this->actingAs($mara)->postJson(route('inbox.claim', $thread))->assertOk();
    deliverDue($this, 3);

    expect(notificationBodies($mike))->toBe([]);
});

it('tells the client about a staff reply after an hour if unseen, and not before', function (): void {
    $client = User::factory()->role(Role::Client)->create();
    $thread = supportThreadFor($client);
    $mara = messagingStaff(Role::Marketing, 'Mara');
    $this->actingAs($mara)->postJson(route('inbox.claim', $thread))->assertOk();

    sendAs($this, $mara, $thread, 'Your quote is ready');
    deliverDue($this, 59);
    expect(notificationBodies($client))->toBe([]);
    Notification::assertNotSentTo($client, MessageWaitingNotification::class);

    deliverDue($this, 1);

    expect(notificationBodies($client))->toBe(['Mara sent you a new message.']);
    Notification::assertSentTo($client, MessageWaitingNotification::class, fn (MessageWaitingNotification $mail): bool => $mail->headline === 'Mara sent you a new message.' && $mail->conversationId === $thread->id);
});

it('queues the delivery for the due time', function (): void {
    Queue::fake();
    $client = User::factory()->role(Role::Client)->create();
    $thread = supportThreadFor($client);
    messagingStaff(Role::Marketing);

    sendAs($this, $client, $thread);

    $pending = PendingNotification::query()->sole();
    expect($pending->due_at->equalTo(now()->addMinutes(3)))->toBeTrue();
    Queue::assertPushed(DeliverMessageNotification::class, fn (DeliverMessageNotification $job): bool => $job->pendingId === $pending->id && $job->delay !== null);
});

it('never sends a notification twice, nor one that was cancelled', function (): void {
    $client = User::factory()->role(Role::Client)->create();
    $thread = supportThreadFor($client);
    $mara = messagingStaff(Role::Marketing, 'Mara');
    $mike = messagingStaff(Role::Marketing, 'Mike');
    sendAs($this, $client, $thread);
    $this->actingAs($mike)->getJson(route('inbox.thread', $thread))->assertOk();
    $this->travel(3)->minutes();

    foreach (PendingNotification::query()->pluck('id') as $id) {
        (new DeliverMessageNotification($id))->handle(app(MessageNotificationService::class));
        (new DeliverMessageNotification($id))->handle(app(MessageNotificationService::class));
    }

    expect(notificationBodies($mara))->toHaveCount(1)
        ->and(notificationBodies($mike))->toBe([]);
});

it('tells the staff assigned on the inquiry about the client\'s message in its group, never marketing', function (): void {
    $world = inquiryGroup();

    sendAs($this, $world['client'], $world['group']);
    $this->travel(1)->minutes();
    $this->actingAs($world['technical'])->getJson(route('inbox.thread', $world['group']))->assertOk();
    deliverDue($this, 2);

    expect(notificationBodies($world['sales']))->toBe(['Carla sent a new message in Kitchen project.'])
        ->and(notificationBodies($world['technical']))->toBe([], 'opened the group in time')
        ->and(notificationBodies($world['marketing']))->toBe([]);
    Notification::assertSentTo($world['sales'], MessageWaitingNotification::class);
    Notification::assertNotSentTo($world['marketing'], MessageWaitingNotification::class);
});

it('tells only the client about a staff message in the group', function (): void {
    $world = inquiryGroup();

    sendAs($this, $world['sales'], $world['group'], 'Measurements on Friday');
    deliverDue($this, 3);
    expect(notificationBodies($world['technical']))->toBe([])
        ->and(notificationBodies($world['marketing']))->toBe([])
        ->and(notificationBodies($world['client']))->toBe([]);

    deliverDue($this, 57);

    expect(notificationBodies($world['client']))->toBe(['Sam sent you a new message.']);
});

it('stops telling staff who lost their assignment before the notification was due', function (): void {
    $world = inquiryGroup();

    sendAs($this, $world['client'], $world['group']);
    InquiryHandler::query()->where('user_id', $world['technical']->id)->delete();
    deliverDue($this, 3);

    expect(notificationBodies($world['technical']))->toBe([])
        ->and(notificationBodies($world['sales']))->toHaveCount(1);
});

it('sends the owner and the admin no message notifications', function (): void {
    $client = User::factory()->role(Role::Client)->create(['name' => 'Carla']);
    $owner = messagingStaff(Role::Owner, 'Olive');
    $admin = messagingStaff(Role::Admin, 'Ada');
    $sales = messagingStaff(Role::Sales, 'Sam');
    $group = groupConversation([$sales, $client, $owner, $admin], attributes: ['name' => 'Old group']);
    $thread = supportThreadFor($client);
    $this->actingAs($admin)->postJson(route('inbox.claim', $thread))->assertOk();

    sendAs($this, $client, $group);
    sendAs($this, $client, $thread);
    deliverDue($this, 3);

    expect(notificationBodies($owner))->toBe([])
        ->and(notificationBodies($admin))->toBe([])
        ->and(notificationBodies($sales))->toBe(['Carla sent a new message in Old group.']);
});

it('disables the owner\'s composer in every conversation and leaves the admin\'s', function (): void {
    $world = inquiryGroup();
    $owner = messagingStaff(Role::Owner);
    $admin = messagingStaff(Role::Admin);

    $this->actingAs($owner)->getJson(route('inbox.thread', $world['group']))->assertOk()->assertJsonPath('conversation.canSend', false);
    $this->actingAs($admin)->getJson(route('inbox.thread', $world['group']))->assertOk()->assertJsonPath('conversation.canSend', true);
    $this->actingAs($world['client'])->getJson(route('inbox.thread', $world['group']))->assertOk()->assertJsonPath('conversation.canSend', true);
    $this->actingAs($owner)->postJson(route('inbox.send', $world['group']), notifiedMessage('Hello'))->assertForbidden();
});
