<?php

use App\Enums\ConversationMemberRole;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\ConversationMember;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\Models\Role as RoleModel;

beforeEach(function (): void {
    seedInquiryCatalog();
    $this->marketing = messagingStaff(Role::Marketing, 'Marketing');
    $this->sales = messagingStaff(Role::Sales, 'Sales');
    $this->client = inquiryClient();
});

/** The badge count the shared props hand `$user` on any page. */
function unreadBadgeFor(mixed $test, User $user): int
{
    return $test->actingAs($user)->get(route('home.index'))->viewData('page')['props']['unreadMessageCount'];
}

it('counts the messages others sent the user across every conversation', function (): void {
    $group = groupConversation([$this->sales, $this->client]);
    textMessage($group, $this->sales, 'First');
    textMessage($group, $this->sales, 'Second');
    textMessage(supportThreadFor($this->client), $this->marketing, 'Hello from the team');

    expect(unreadBadgeFor($this, $this->client))->toBe(3);
});

it('leaves out the user\'s own messages and the ones already read', function (): void {
    $group = groupConversation([$this->sales, $this->client]);
    $read = textMessage($group, $this->sales, 'Seen');
    textMessage($group, $this->client, 'My reply');
    ConversationMember::query()
        ->where('conversation_id', $group->id)
        ->where('user_id', $this->client->id)
        ->update(['last_read_message_id' => $read->id]);

    expect(unreadBadgeFor($this, $this->client))->toBe(0);

    textMessage($group, $this->sales, 'New since');

    expect(unreadBadgeFor($this, $this->client))->toBe(1);
});

it('counts an unclaimed Regray Team thread for the support pool, as its list does', function (): void {
    textMessage(supportThreadFor($this->client), $this->client, 'Is anyone there?');

    expect(unreadBadgeFor($this, $this->marketing))->toBe(1)
        ->and(unreadBadgeFor($this, $this->sales))->toBe(0);
});

it('does not count a conversation the user only views', function (): void {
    $group = groupConversation(
        [$this->marketing, $this->sales],
        [$this->sales->id => ConversationMemberRole::Viewer],
    );
    textMessage($group, $this->marketing, 'For the members');

    expect(unreadBadgeFor($this, $this->sales))->toBe(0);
});

it('is zero for a user who may not open Message', function (): void {
    $role = RoleModel::create(['name' => 'home-only', 'guard_name' => RolePermissionSeeder::GUARD]);
    $role->givePermissionTo(Permission::HomeView->value);
    $reader = User::factory()->role($role->name)->create();
    textMessage(groupConversation([$this->sales, $reader]), $this->sales, 'Unseen');

    expect(unreadBadgeFor($this, $reader))->toBe(0);
});
