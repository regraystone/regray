<?php

use App\Enums\ConversationMemberRole;
use App\Enums\ConversationType;
use App\Enums\Role;
use App\Models\Conversation;
use App\Models\ConversationMember;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

it('lets marketing create a group with staff and one client, and makes them its admin', function (): void {
    $marketing = messagingStaff(Role::Marketing);
    $sales = messagingStaff(Role::Sales);
    $client = inquiryClient();

    $this->actingAs($marketing)->postJson(route('message.groups.store'), [
        'name' => 'Kitchen project',
        'staffIds' => [$sales->id],
        'clientId' => $client->id,
    ])->assertCreated()->assertJsonPath('conversation.name', 'Kitchen project');

    $group = Conversation::query()->where('type', ConversationType::Group)->sole();
    $roles = ConversationMember::query()->where('conversation_id', $group->id)->pluck('role', 'user_id');

    expect($roles->all())->toEqual([
        $marketing->id => ConversationMemberRole::Admin,
        $sales->id => ConversationMemberRole::Member,
        $client->id => ConversationMemberRole::Member,
    ]);
});

it('refuses sales a new group with 403', function (): void {
    $this->actingAs(messagingStaff(Role::Sales))->postJson(route('message.groups.store'), [
        'name' => 'Kitchen project',
        'staffIds' => [messagingStaff(Role::Marketing)->id],
        'clientId' => inquiryClient()->id,
    ])->assertForbidden();

    $this->actingAs(messagingStaff(Role::Sales))->getJson(route('message.people'))->assertForbidden();

    expect(Conversation::query()->where('type', ConversationType::Group)->exists())->toBeFalse();
});

it('tells the creator what a group still needs', function (): void {
    $this->actingAs(messagingStaff(Role::Marketing))->postJson(route('message.groups.store'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'name' => 'Give the group a name.',
            'staffIds' => 'Add at least one staff member.',
            'clientId' => 'Choose one client.',
        ]);
});

it('refuses a client in the staff list with 422', function (): void {
    $client = inquiryClient();

    $this->actingAs(messagingStaff(Role::Marketing))->postJson(route('message.groups.store'), [
        'name' => 'Kitchen project',
        'staffIds' => [inquiryClient()->id],
        'clientId' => $client->id,
    ])->assertUnprocessable();

    expect(Conversation::query()->where('type', ConversationType::Group)->exists())->toBeFalse();
});

it('tells the page whether the user may create a group', function (): void {
    $this->actingAs(messagingStaff(Role::Marketing))->get(route('message.index'))
        ->assertInertia(fn ($page) => $page->where('canCreateGroup', true));

    $this->actingAs(messagingStaff(Role::Sales))->get(route('message.index'))
        ->assertInertia(fn ($page) => $page->where('canCreateGroup', false));
});
