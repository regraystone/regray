<?php

use App\Data\Message\ConversationListFilters;
use App\Enums\ConversationMemberRole;
use App\Models\Conversation;
use App\Models\InquiryHandler;
use App\Models\User;
use App\Services\Message\ConversationService;
use Illuminate\Support\Str;

/*
 * Who reads and writes in an inquiry's group chat. Staff who read the inquiry
 * read the group, in its member list or not; the admin and the owner read
 * every one. Writing takes a member who is not a viewer and who reads the
 * inquiry. The owner writes in no conversation at all. A group bound to no
 * inquiry keeps the membership rule.
 */

beforeEach(function (): void {
    seedInquiryCatalog();
});

/**
 * The visibility world, and a group for its `assigned` inquiry (assigned to
 * technical, accounting and logistics) holding its client, technical, sales
 * (who does not read the inquiry), logistics as a viewer and, as an older group
 * might, the owner.
 *
 * @return array{users: array<string, User>, group: Conversation, client: User}
 */
function groupWorld(): array
{
    $world = visibilityWorld();
    $users = $world['users'];
    $client = $world['inquiries']['assigned']->client;

    $group = groupConversation(
        [$client, $users['technical'], $users['sales'], $users['logistics'], $users['owner']],
        [$users['logistics']->id => ConversationMemberRole::Viewer],
        ['inquiry_id' => $world['inquiries']['assigned']->id, 'name' => 'Assigned group'],
    );

    return ['users' => $users, 'group' => $group, 'client' => $client];
}

/** @return list<string> */
function listedGroupNames(User $user): array
{
    return app(ConversationService::class)->list($user, new ConversationListFilters)->getCollection()->pluck('name')->all();
}

it('lets whoever reads the inquiry read its group, member or not, and the admin and the owner every group', function (): void {
    $world = groupWorld();
    $users = $world['users'];

    expect($users['technical']->can('view', $world['group']))->toBeTrue()
        ->and($users['accounting']->can('view', $world['group']))->toBeTrue('accounting reads it without being a member')
        ->and($users['logistics']->can('view', $world['group']))->toBeTrue()
        ->and($users['admin']->can('view', $world['group']))->toBeTrue()
        ->and($users['owner']->can('view', $world['group']))->toBeTrue()
        ->and($world['client']->can('view', $world['group']))->toBeTrue()
        ->and($users['sales']->can('view', $world['group']))->toBeFalse('sales is a member but does not read the inquiry')
        ->and($users['marketing']->can('view', $world['group']))->toBeFalse();
});

it('lets only members who are not viewers and read the inquiry send', function (): void {
    $world = groupWorld();
    $users = $world['users'];

    expect($users['technical']->can('send', $world['group']))->toBeTrue()
        ->and($world['client']->can('send', $world['group']))->toBeTrue()
        ->and($users['admin']->can('send', $world['group']))->toBeTrue('the admin sends in any group')
        ->and($users['accounting']->can('send', $world['group']))->toBeFalse('not a member')
        ->and($users['logistics']->can('send', $world['group']))->toBeFalse('a viewer')
        ->and($users['sales']->can('send', $world['group']))->toBeFalse('does not read the inquiry')
        ->and($users['owner']->can('send', $world['group']))->toBeFalse('the owner writes in no group, even with a membership row');
});

it('opens the thread to a reader who is not a member, and refuses their message', function (): void {
    $world = groupWorld();
    $accounting = $world['users']['accounting'];
    textMessage($world['group'], $world['users']['technical'], 'Deposit is in');

    $this->actingAs($accounting)->getJson(route('inbox.thread', $world['group']))
        ->assertOk()
        ->assertJsonPath('messages.0.body', 'Deposit is in');

    $this->actingAs($accounting)->postJson(route('inbox.send', $world['group']), ['body' => 'Hello', 'clientKey' => (string) Str::uuid()])
        ->assertForbidden();
    $this->actingAs($world['users']['sales'])->getJson(route('inbox.thread', $world['group']))->assertForbidden();
});

it('lets the owner read every group and send in none', function (): void {
    $world = groupWorld();
    $owner = $world['users']['owner'];
    $other = groupConversation([inquiryClient()], attributes: ['inquiry_id' => makeInquiry(inquiryClient())->id, 'name' => 'Other group']);

    $this->actingAs($owner)->getJson(route('inbox.thread', $other))->assertOk();
    $this->actingAs($owner)->postJson(route('inbox.send', $other), ['body' => 'Hello', 'clientKey' => (string) Str::uuid()])->assertForbidden();
    $this->actingAs($owner)->postJson(route('inbox.send', $world['group']), ['body' => 'Hello', 'clientKey' => (string) Str::uuid()])->assertForbidden();

    expect(listedGroupNames($owner))->toContain('Assigned group', 'Other group')
        ->and(InquiryHandler::query()->where('user_id', $owner->id)->exists())->toBeFalse();
});

it('lists an inquiry\'s group to the staff who read its inquiry, and to no member who does not', function (): void {
    $world = groupWorld();

    expect(listedGroupNames($world['users']['accounting']))->toContain('Assigned group')
        ->and(listedGroupNames($world['users']['admin']))->toContain('Assigned group')
        ->and(listedGroupNames($world['users']['sales']))->not->toContain('Assigned group')
        ->and(listedGroupNames($world['users']['marketing']))->not->toContain('Assigned group')
        ->and(listedGroupNames($world['client']))->toContain('Assigned group');
});

it('keeps the membership rule for a group bound to no inquiry', function (): void {
    $world = groupWorld();
    $legacy = groupConversation([$world['users']['sales'], $world['users']['owner']], attributes: ['name' => 'Legacy group']);

    expect($world['users']['sales']->can('view', $legacy))->toBeTrue()
        ->and($world['users']['sales']->can('send', $legacy))->toBeTrue()
        ->and($world['users']['owner']->can('send', $legacy))->toBeFalse('the owner writes in no conversation, whatever its membership')
        ->and($world['users']['accounting']->can('view', $legacy))->toBeFalse()
        ->and(listedGroupNames($world['users']['sales']))->toContain('Legacy group')
        ->and(listedGroupNames($world['users']['accounting']))->not->toContain('Legacy group');
});

it('does not count a group the user only reads in their unread badge', function (): void {
    $world = groupWorld();
    textMessage($world['group'], $world['users']['technical'], 'Unread by members');

    $badge = fn (User $user): int => app(ConversationService::class)->unreadTotal($user);

    expect($badge($world['users']['accounting']))->toBe(0)
        ->and($badge($world['client']))->toBe(1);
});

it('offers "Message the team" on the details page to staff who read the group', function (): void {
    $world = groupWorld();
    $reference = $world['group']->inquiry->reference;

    $conversation = fn (User $user) => $this->actingAs($user)->get(route('inquiry.show', $reference))
        ->assertOk()
        ->viewData('page')['props']['inquiry']['conversation'];

    expect($conversation($world['users']['accounting']))->toMatchArray(['id' => $world['group']->id])
        ->and($conversation($world['users']['owner']))->toMatchArray(['id' => $world['group']->id]);
});
