<?php

use App\Enums\BookedByType;
use App\Enums\NotificationType;
use App\Enums\Role;
use App\Enums\ScheduleStatus;
use App\Events\Notification\NotificationCreated;
use App\Models\Schedule;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Notification\NotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The workspace bell: its pages, its badge, opening a notification (which
 * reads it and says where it leads, or that it is no longer available),
 * marking all read, and pruning after 30 days.
 */

beforeEach(function (): void {
    seedInquiryCatalog();
});

function notify(User $user, NotificationType $type, int $targetId, string $body = 'Something happened'): UserNotification
{
    app(NotificationService::class)->send([$user], $type, $targetId, $body);

    return UserNotification::query()->where('user_id', $user->id)->latest('id')->firstOrFail();
}

function standaloneVisit(User $client): Schedule
{
    $schedule = new Schedule;
    $schedule->forceFill([
        'client_id' => $client->id,
        'booked_by' => $client->id,
        'booked_by_type' => BookedByType::Client,
        'title' => 'Showroom visit',
        'arrival_at' => now()->addDays(2),
        'status' => ScheduleStatus::Pending,
    ])->save();

    return $schedule;
}

it('lists the reader\'s own notifications newest first, ten at a time', function (): void {
    $user = messagingStaff(Role::Sales);
    $other = messagingStaff(Role::Sales);
    notify($other, NotificationType::Inquiry, 1, 'Not yours');

    foreach (range(1, 15) as $n) {
        notify($user, NotificationType::Inquiry, 1, "Line {$n}");
    }

    $first = $this->actingAs($user)->getJson(route('notifications.index'))
        ->assertOk()
        ->assertJsonCount(10, 'notifications')
        ->assertJsonPath('notifications.0.body', 'Line 15')
        ->assertJsonPath('notifications.9.body', 'Line 6')
        ->assertJsonPath('hasMore', true)
        ->assertJsonPath('unreadCount', 15);

    $this->actingAs($user)->getJson(route('notifications.index', ['before' => $first->json('notifications.9.id')]))
        ->assertOk()
        ->assertJsonCount(5, 'notifications')
        ->assertJsonPath('notifications.4.body', 'Line 1')
        ->assertJsonPath('hasMore', false);
});

it('counts the unread for the badge, and listing them reads none', function (): void {
    $user = messagingStaff(Role::Sales);

    foreach (range(1, 10) as $n) {
        notify($user, NotificationType::Inquiry, 1);
    }

    $this->actingAs($user)->getJson(route('notifications.index'))->assertOk();

    $this->actingAs($user)->get(route('overview.index'))
        ->assertInertia(fn (Assert $page) => $page->where('unreadNotificationCount', 10));
});

it('pushes each new notification to its recipient with their unread count', function (): void {
    Event::fake([NotificationCreated::class]);
    $user = messagingStaff(Role::Sales);

    notify($user, NotificationType::Inquiry, 1, 'First');
    notify($user, NotificationType::Inquiry, 1, 'Second');

    Event::assertDispatched(NotificationCreated::class, fn (NotificationCreated $event): bool => $event->notification->body === 'Second'
        && $event->unreadCount === 2
        && $event->broadcastOn()[0]->name === "private-user.{$user->id}");
});

it('reads a notification when it is opened and leads to its thread', function (): void {
    $client = inquiryClient();
    $thread = supportThreadFor($client);
    $notification = notify($client, NotificationType::Message, $thread->id);

    $this->actingAs($client)->postJson(route('notifications.open', $notification))
        ->assertOk()
        ->assertJsonPath('url', route('inbox.index', ['conversation' => $thread->id]))
        ->assertJsonPath('unreadCount', 0);

    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('leads the client and staff to their own pages for an inquiry and an appointment', function (): void {
    $client = inquiryClient();
    $marketing = messagingStaff(Role::Marketing);
    $inquiry = makeInquiry($client, attributes: ['reference' => 'INQ-B1', 'lead' => makeLead($client, $marketing)]);
    $visit = standaloneVisit($client);

    $open = fn (User $user, NotificationType $type, int $id) => $this->actingAs($user)
        ->postJson(route('notifications.open', notify($user, $type, $id)))
        ->assertOk()
        ->json('url');

    expect($open($client, NotificationType::Inquiry, $inquiry->id))->toBe(route('quote.show', 'INQ-B1'))
        ->and($open($marketing, NotificationType::Inquiry, $inquiry->id))->toBe(route('inquiry.show', 'INQ-B1'))
        ->and($open($client, NotificationType::Schedule, $visit->id))->toBe(route('profile.schedule'))
        ->and($open($marketing, NotificationType::Schedule, $visit->id))->toBe(route('schedule.index'));
});

it('says an item is no longer available when the reader can no longer open it', function (): void {
    $client = inquiryClient();
    $technical = messagingStaff(Role::Technical);
    $inquiry = makeInquiry($client, attributes: ['reference' => 'INQ-B2']);
    $group = groupConversation([$client], attributes: ['name' => 'Gone']);
    $visit = standaloneVisit($client);

    $notifications = [
        notify($technical, NotificationType::Inquiry, $inquiry->id),
        notify($technical, NotificationType::Message, $group->id),
        notify($technical, NotificationType::Schedule, $visit->id),
        notify($client, NotificationType::Schedule, $visit->id + 1000),
    ];

    foreach ($notifications as $notification) {
        $this->actingAs($notification->user)->postJson(route('notifications.open', $notification))
            ->assertOk()
            ->assertJsonPath('url', null);
    }
});

it('takes marketing to the client\'s thread for an inquiry nobody has claimed yet', function (): void {
    $client = inquiryClient();
    $thread = supportThreadFor($client);
    $marketing = messagingStaff(Role::Marketing);
    $inquiry = makeInquiry($client, attributes: ['reference' => 'INQ-B3']);

    $this->actingAs($marketing)->postJson(route('notifications.open', notify($marketing, NotificationType::Inquiry, $inquiry->id)))
        ->assertJsonPath('url', route('inbox.index', ['conversation' => $thread->id]));
});

it('never opens someone else\'s notification', function (): void {
    $notification = notify(messagingStaff(Role::Sales), NotificationType::Inquiry, 1);

    $this->actingAs(messagingStaff(Role::Sales))->postJson(route('notifications.open', $notification))->assertNotFound();

    expect($notification->fresh()->read_at)->toBeNull();
});

it('marks every notification read at once, and only the reader\'s', function (): void {
    $user = messagingStaff(Role::Sales);
    $other = messagingStaff(Role::Sales);
    notify($user, NotificationType::Inquiry, 1);
    notify($user, NotificationType::Inquiry, 1);
    notify($other, NotificationType::Inquiry, 1);

    $this->actingAs($user)->postJson(route('notifications.read-all'))->assertOk()->assertJsonPath('unreadCount', 0);

    expect(app(NotificationService::class)->unreadCount($user))->toBe(0)
        ->and(app(NotificationService::class)->unreadCount($other))->toBe(1);
});

it('deletes notifications older than 30 days', function (): void {
    $user = messagingStaff(Role::Sales);
    $this->travelTo(CarbonImmutable::parse('2026-10-01 09:00'));
    notify($user, NotificationType::Inquiry, 1, 'Old');
    $this->travelTo(CarbonImmutable::parse('2026-10-20 09:00'));
    notify($user, NotificationType::Inquiry, 1, 'Recent');
    $this->travelTo(CarbonImmutable::parse('2026-11-01 09:00'));

    $this->artisan('model:prune', ['--model' => [UserNotification::class]])->assertSuccessful();

    expect(notificationBodies($user))->toBe(['Recent']);
});

it('keeps the bell to signed-in users', function (): void {
    $this->getJson(route('notifications.index'))->assertUnauthorized();
});
