<?php

use App\Enums\ConversationMemberRole;
use App\Enums\ConversationType;
use App\Enums\MessageType;
use App\Enums\Role;
use App\Enums\SystemEvent;
use App\Models\Conversation;
use App\Models\ConversationMember;
use App\Models\Message;
use App\Models\User;
use App\Repositories\Eloquent\EloquentConversationRepository;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

/** @return array<string, mixed> */
function sendPayload(string $body = 'Hello there'): array
{
    return ['body' => $body, 'clientKey' => (string) Str::uuid()];
}

/** @return list<string> */
function listedConversationNames(User $viewer): array
{
    // The list is deferred: the first visit leaves it out, and the page asks
    // for it in a partial reload, as the browser does.
    $page = test()->actingAs($viewer)->get(route('message.index'))->assertOk()->viewData('page');
    expect($page['props'])->not->toHaveKey('conversations');

    $reload = test()->actingAs($viewer)->get(route('message.index'), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) $page['version'],
        'X-Inertia-Partial-Component' => 'message',
        'X-Inertia-Partial-Data' => 'conversations',
    ])->assertOk()->json();

    return array_column($reload['props']['conversations']['data'], 'name');
}

it('gives a newly registered client one Regray Team thread', function (): void {
    Notification::fake();

    $this->post(route('register.store'), [
        'name' => 'New Client',
        'email' => 'new-client@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect();

    $client = User::query()->where('email', 'new-client@example.com')->firstOrFail();
    $client->markEmailAsVerified();

    expect(Conversation::query()->where('support_client_id', $client->id)->count())->toBe(1)
        ->and(listedConversationNames($client))->toBe(['Regray Team']);
});

it('creates the Regray Team thread once, however often it is asked for', function (): void {
    $client = inquiryClient();

    $first = supportThreadFor($client);
    $second = supportThreadFor($client);

    expect($second->id)->toBe($first->id)
        ->and(Conversation::query()->where('type', ConversationType::Support)->count())->toBe(1)
        ->and(ConversationMember::query()->where('conversation_id', $first->id)->count())->toBe(1);
});

it('lists an unassigned thread under the client name for the pool, and not for sales', function (): void {
    $client = inquiryClient();
    $client->forceFill(['name' => 'Rafael'])->save();
    supportThreadFor($client);

    expect(listedConversationNames(messagingStaff(Role::Marketing)))->toBe(['Rafael'])
        ->and(listedConversationNames(messagingStaff(Role::Sales)))->toBe([]);
});

it('assigns the thread to the marketing member who claims it and writes a system row', function (): void {
    $thread = supportThreadFor(inquiryClient());
    $marketing = messagingStaff(Role::Marketing, 'Mara');

    $this->actingAs($marketing)->postJson(route('message.claim', $thread))->assertOk();

    $thread->refresh();
    $system = Message::query()->where('conversation_id', $thread->id)->sole();

    expect($thread->assigned_to)->toBe($marketing->id)
        ->and($thread->assigned_at)->not->toBeNull()
        ->and($system->type)->toBe(MessageType::System)
        ->and($system->system_event)->toBe(SystemEvent::Assigned)
        ->and($system->system_user_id)->toBe($marketing->id);
});

it('refuses a claim from sales with 403', function (): void {
    $thread = supportThreadFor(inquiryClient());

    $this->actingAs(messagingStaff(Role::Sales))->postJson(route('message.claim', $thread))->assertForbidden();

    expect($thread->refresh()->assigned_to)->toBeNull();
});

it('lets only the first of two claims win and tells the second who did with 409', function (): void {
    $thread = supportThreadFor(inquiryClient());
    $first = messagingStaff(Role::Marketing, 'Mara');
    $admin = messagingStaff(Role::Admin, 'Ada');

    $this->actingAs($first)->postJson(route('message.claim', $thread))->assertOk();
    $this->actingAs($admin)->postJson(route('message.claim', $thread))
        ->assertConflict()
        ->assertJsonPath('message', 'Mara already took this conversation.');

    expect($thread->refresh()->assigned_to)->toBe($first->id)
        ->and(Message::query()->where('conversation_id', $thread->id)->count())->toBe(1);
});

it('shows staff each client\'s thread under the client\'s name and initials, and the client the Regray logo', function (): void {
    $rafael = inquiryClient();
    $rafael->forceFill(['name' => 'Rafael'])->save();
    $bianca = inquiryClient();
    $bianca->forceFill(['name' => 'Bianca'])->save();
    supportThreadFor($rafael);
    supportThreadFor($bianca);

    $rowsFor = function (User $viewer): array {
        $page = test()->actingAs($viewer)->get(route('message.index'))->viewData('page');

        return test()->actingAs($viewer)->get(route('message.index'), [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) $page['version'],
            'X-Inertia-Partial-Component' => 'message',
            'X-Inertia-Partial-Data' => 'conversations',
        ])->json('props.conversations.data');
    };

    $staffRows = collect($rowsFor(messagingStaff(Role::Marketing)));

    expect($staffRows->pluck('name')->sort()->values()->all())->toBe(['Bianca', 'Rafael'])
        ->and($staffRows->pluck('avatar')->unique()->all())->toBe([null]);

    $clientRow = $rowsFor($rafael)[0];

    expect($clientRow['name'])->toBe('Regray Team')
        ->and($clientRow['avatar'])->toBe(EloquentConversationRepository::SUPPORT_AVATAR);
});

it('closes a claimed thread to the rest of the pool with 403 and drops it from their list', function (): void {
    $client = inquiryClient();
    $client->forceFill(['name' => 'Rafael'])->save();
    $thread = supportThreadFor($client);
    $other = messagingStaff(Role::Marketing, 'Mike');

    $this->actingAs($other)->getJson(route('message.thread', $thread))->assertOk();
    $this->actingAs(messagingStaff(Role::Marketing, 'Mara'))->postJson(route('message.claim', $thread))->assertOk();

    $this->actingAs($other)->getJson(route('message.thread', $thread))->assertForbidden();
    expect(listedConversationNames($other))->toBe([])
        ->and(ConversationMember::query()->where('conversation_id', $thread->id)->where('user_id', $other->id)->exists())->toBeFalse();
});

it('refuses a staff message on an unassigned thread with 409', function (): void {
    $thread = supportThreadFor(inquiryClient());

    $this->actingAs(messagingStaff(Role::Marketing))->postJson(route('message.send', $thread), sendPayload())
        ->assertConflict()
        ->assertJsonPath('message', 'Assign this conversation to yourself first.');

    expect(Message::query()->count())->toBe(0);
});

it('tells a pool member who lost the claim who is handling the thread, with 409, while reading stays 403', function (): void {
    $thread = supportThreadFor(inquiryClient());
    $other = messagingStaff(Role::Marketing, 'Mike');
    $this->actingAs(messagingStaff(Role::Marketing, 'Mara'))->postJson(route('message.claim', $thread))->assertOk();

    $this->actingAs($other)->postJson(route('message.send', $thread), sendPayload('Can I help?'))
        ->assertConflict()
        ->assertJsonPath('message', 'Mara is handling this conversation.');
    $this->actingAs($other)->getJson(route('message.thread', $thread))->assertForbidden();

    expect(Message::query()->where('body', 'Can I help?')->exists())->toBeFalse();
});

it('still refuses a send from staff outside the pool with 403', function (): void {
    $thread = supportThreadFor(inquiryClient());
    $this->actingAs(messagingStaff(Role::Marketing, 'Mara'))->postJson(route('message.claim', $thread))->assertOk();

    $this->actingAs(messagingStaff(Role::Sales))->postJson(route('message.send', $thread), sendPayload())
        ->assertForbidden();
});

it('refuses the admin a message on a thread someone else handles with 409', function (): void {
    $thread = supportThreadFor(inquiryClient());
    $this->actingAs(messagingStaff(Role::Marketing, 'Mara'))->postJson(route('message.claim', $thread))->assertOk();

    $this->actingAs(messagingStaff(Role::Admin))->postJson(route('message.send', $thread), sendPayload())
        ->assertConflict()
        ->assertJsonPath('message', 'Mara is handling this conversation.');
});

it('lets the client write whether or not anyone is handling the thread', function (): void {
    $client = inquiryClient();
    $thread = supportThreadFor($client);

    $this->actingAs($client)->postJson(route('message.send', $thread), sendPayload('Before'))->assertCreated();
    $this->actingAs(messagingStaff(Role::Marketing))->postJson(route('message.claim', $thread))->assertOk();
    $this->actingAs($client)->postJson(route('message.send', $thread), sendPayload('After'))->assertCreated();

    expect(Message::query()->where('type', MessageType::Text)->pluck('body')->all())->toBe(['Before', 'After']);
});

it('lets the assignee write once they have claimed the thread', function (): void {
    $thread = supportThreadFor(inquiryClient());
    $marketing = messagingStaff(Role::Marketing);
    $this->actingAs($marketing)->postJson(route('message.claim', $thread))->assertOk();

    $this->actingAs($marketing)->postJson(route('message.send', $thread), sendPayload('On it'))
        ->assertCreated()
        ->assertJsonPath('message.body', 'On it');
});

it('hands a released thread back to the pool, keeps the former assignee as a viewer and writes a system row', function (): void {
    $thread = supportThreadFor(inquiryClient());
    $marketing = messagingStaff(Role::Marketing, 'Mara');
    $this->actingAs($marketing)->postJson(route('message.claim', $thread))->assertOk();

    $this->actingAs($marketing)->postJson(route('message.release', $thread))->assertOk();

    $thread->refresh();
    $released = Message::query()->where('system_event', SystemEvent::Released->value)->sole();

    expect($thread->assigned_to)->toBeNull()
        ->and($thread->assigned_at)->toBeNull()
        ->and($released->system_user_id)->toBe($marketing->id)
        ->and(ConversationMember::query()->where('conversation_id', $thread->id)->where('user_id', $marketing->id)->value('role'))
        ->toBe(ConversationMemberRole::Viewer);
});

it('refuses a release from anyone but the assignee with 403', function (): void {
    $thread = supportThreadFor(inquiryClient());
    $marketing = messagingStaff(Role::Marketing);
    $this->actingAs($marketing)->postJson(route('message.claim', $thread))->assertOk();

    $this->actingAs(messagingStaff(Role::Marketing))->postJson(route('message.release', $thread))->assertForbidden();

    expect($thread->refresh()->assigned_to)->toBe($marketing->id);
});

it('lets the admin reassign a thread to another pool member', function (): void {
    $thread = supportThreadFor(inquiryClient());
    $from = messagingStaff(Role::Marketing, 'Mara');
    $to = messagingStaff(Role::Marketing, 'Mike');
    $this->actingAs($from)->postJson(route('message.claim', $thread))->assertOk();

    $this->actingAs(messagingStaff(Role::Admin))->postJson(route('message.reassign', $thread), ['userId' => $to->id])->assertOk();

    expect($thread->refresh()->assigned_to)->toBe($to->id)
        ->and(Message::query()->where('system_event', SystemEvent::Reassigned->value)->value('system_user_id'))->toBe($to->id)
        ->and(ConversationMember::query()->where('conversation_id', $thread->id)->pluck('user_id')->sort()->values()->all())
        ->toBe(collect([$thread->support_client_id, $to->id])->sort()->values()->all());
});

it('refuses a reassign from the assignee with 403', function (): void {
    $thread = supportThreadFor(inquiryClient());
    $marketing = messagingStaff(Role::Marketing);
    $this->actingAs($marketing)->postJson(route('message.claim', $thread))->assertOk();

    $this->actingAs($marketing)
        ->postJson(route('message.reassign', $thread), ['userId' => messagingStaff(Role::Marketing)->id])
        ->assertForbidden();

    expect($thread->refresh()->assigned_to)->toBe($marketing->id);
});

it('refuses to reassign a thread to someone outside the pool with 422', function (): void {
    $thread = supportThreadFor(inquiryClient());
    $sales = messagingStaff(Role::Sales, 'Sam');

    $this->actingAs(messagingStaff(Role::Admin))->postJson(route('message.reassign', $thread), ['userId' => $sales->id])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Sam cannot handle Regray Team conversations.');

    expect($thread->refresh()->assigned_to)->toBeNull();
});

it('sends the assignment state to staff only, and only for a Regray Team thread', function (): void {
    $client = inquiryClient();
    $thread = supportThreadFor($client);
    $marketing = messagingStaff(Role::Marketing);
    $group = groupConversation([$marketing, $client]);

    $this->actingAs($marketing)->getJson(route('message.thread', $thread))
        ->assertJsonPath('assignment.canClaim', true)
        ->assertJsonPath('assignment.assignee', null);
    $this->actingAs($client)->getJson(route('message.thread', $thread))->assertJsonPath('assignment', null);
    $this->actingAs($marketing)->getJson(route('message.thread', $group))->assertJsonPath('assignment', null);
});

it('shows the client "Regray Team" for staff messages while nobody handles the thread', function (): void {
    $client = inquiryClient();
    $thread = supportThreadFor($client);
    $marketing = messagingStaff(Role::Marketing, 'Mara');
    $this->actingAs($marketing)->postJson(route('message.claim', $thread))->assertOk();
    $this->actingAs($marketing)->postJson(route('message.send', $thread), sendPayload('On it'))->assertCreated();

    $this->actingAs($client)->getJson(route('message.thread', $thread))->assertJsonPath('messages.1.sender.name', 'Mara');

    $this->actingAs($marketing)->postJson(route('message.release', $thread))->assertOk();

    $this->actingAs($client)->getJson(route('message.thread', $thread))
        ->assertJsonPath('messages.1.sender.name', 'Regray Team')
        ->assertJsonPath('conversation.supportClient', null);
});

it('hands the thread back to the pool when the assignee account is deleted', function (): void {
    $thread = supportThreadFor(inquiryClient());
    $marketing = messagingStaff(Role::Marketing);
    $thread->forceFill(['assigned_to' => $marketing->id, 'assigned_at' => now()])->save();

    $marketing->delete();

    $thread->refresh();
    expect($thread->assigned_to)->toBeNull()
        ->and($thread->assigned_at)->toBeNull();
});
