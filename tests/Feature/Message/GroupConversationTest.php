<?php

use App\Enums\ConversationMemberRole;
use App\Enums\ConversationType;
use App\Enums\Role;
use App\Models\Conversation;
use App\Models\ConversationMember;

/*
 * Group chats: marketing makes one for one client and one of that client's
 * inquiries, with sales and technical staff; every owner is always in it, and
 * the creator is its admin. It is the inquiry's group chat, and later a sales
 * member may add accounting and logistics.
 */

beforeEach(function (): void {
    seedInquiryCatalog();
    $this->marketing = messagingStaff(Role::Marketing, 'Marketing');
    $this->sales = messagingStaff(Role::Sales, 'Sales');
    $this->technical = messagingStaff(Role::Technical, 'Technical');
    $this->owner = messagingStaff(Role::Owner, 'Owner');
    $this->client = inquiryClient();
    $this->inquiry = makeInquiry($this->client, attributes: ['reference' => 'INQ-G1']);
});

/** @param  array<string, mixed>  $overrides */
function groupInput(object $test, array $overrides = []): array
{
    return [
        'name' => 'Kitchen project',
        'staffIds' => [$test->sales->id, $test->technical->id],
        'clientId' => $test->client->id,
        'inquiryId' => $test->inquiry->id,
        ...$overrides,
    ];
}

it('lets marketing make a group for a client\'s inquiry with sales and technical, always adds every owner, and makes the creator its admin', function (): void {
    $this->actingAs($this->marketing)->postJson(route('message.groups.store'), groupInput($this))
        ->assertCreated()
        ->assertJsonPath('conversation.name', 'Kitchen project');

    $group = Conversation::query()->where('type', ConversationType::Group)->sole();
    $roles = ConversationMember::query()->where('conversation_id', $group->id)->pluck('role', 'user_id');

    expect($group->inquiry_id)->toBe($this->inquiry->id)
        ->and($roles->all())->toEqualCanonicalizing([
            $this->marketing->id => ConversationMemberRole::Admin,
            $this->sales->id => ConversationMemberRole::Member,
            $this->technical->id => ConversationMemberRole::Member,
            $this->owner->id => ConversationMemberRole::Member,
            $this->client->id => ConversationMemberRole::Member,
        ]);
});

it('offers marketing only sales and technical as staff, and every owner as always included', function (): void {
    messagingStaff(Role::Accounting, 'Accounting');
    messagingStaff(Role::Logistics, 'Logistics');

    $people = $this->actingAs($this->marketing)->getJson(route('message.people', ['clientId' => $this->client->id]))->assertOk();

    expect(collect($people->json('staff'))->pluck('id')->all())->toEqualCanonicalizing([$this->sales->id, $this->technical->id])
        ->and(collect($people->json('owners'))->pluck('id')->all())->toBe([$this->owner->id])
        ->and($people->json('inquiries'))->toBe([['id' => $this->inquiry->id, 'reference' => 'INQ-G1', 'name' => $this->inquiry->name, 'hasGroup' => false]]);
});

it('refuses marketing any staff outside sales and technical', function (Role $role): void {
    $this->actingAs($this->marketing)
        ->postJson(route('message.groups.store'), groupInput($this, ['staffIds' => [messagingStaff($role)->id]]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['staffIds' => 'Choose staff from sales and technical.']);

    expect(Conversation::query()->where('type', ConversationType::Group)->exists())->toBeFalse();
})->with([
    'accounting' => [Role::Accounting],
    'logistics' => [Role::Logistics],
    'marketing' => [Role::Marketing],
    'an owner, who is added anyway' => [Role::Owner],
]);

it('refuses an inquiry that is not the chosen client\'s', function (): void {
    $other = makeInquiry(inquiryClient(), attributes: ['reference' => 'INQ-G2']);

    $this->actingAs($this->marketing)
        ->postJson(route('message.groups.store'), groupInput($this, ['inquiryId' => $other->id]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('inquiryId');

    expect(Conversation::query()->where('type', ConversationType::Group)->exists())->toBeFalse();
});

it('gives an inquiry one group chat at most', function (): void {
    $this->actingAs($this->marketing)->postJson(route('message.groups.store'), groupInput($this))->assertCreated();

    $this->actingAs($this->marketing)
        ->postJson(route('message.groups.store'), groupInput($this, ['name' => 'Again']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['inquiryId' => 'INQ-G1 already has a group chat.']);

    expect(Conversation::query()->where('type', ConversationType::Group)->count())->toBe(1);
});

it('refuses a client in the staff list with 422', function (): void {
    $this->actingAs($this->marketing)
        ->postJson(route('message.groups.store'), groupInput($this, ['staffIds' => [inquiryClient()->id]]))
        ->assertUnprocessable();

    expect(Conversation::query()->where('type', ConversationType::Group)->exists())->toBeFalse();
});

describe('adding people later', function (): void {
    beforeEach(function (): void {
        $this->group = groupConversation([$this->marketing, $this->sales, $this->technical, $this->client], [$this->marketing->id => ConversationMemberRole::Admin], ['inquiry_id' => $this->inquiry->id]);
        $this->accounting = messagingStaff(Role::Accounting, 'Accounting');
        $this->logistics = messagingStaff(Role::Logistics, 'Logistics');
    });

    it('lets a sales member add accounting and logistics', function (): void {
        $this->actingAs($this->sales)->getJson(route('message.members.index', $this->group))
            ->assertOk()
            ->assertJsonPath('people', [
                ['id' => $this->accounting->id, 'name' => 'Accounting'],
                ['id' => $this->logistics->id, 'name' => 'Logistics'],
            ]);

        $this->actingAs($this->sales)
            ->postJson(route('message.members.store', $this->group), ['userIds' => [$this->accounting->id, $this->logistics->id]])
            ->assertOk()
            ->assertJsonPath('conversation.memberCount', 6);

        expect(ConversationMember::query()->where('conversation_id', $this->group->id)->pluck('user_id')->all())
            ->toContain($this->accounting->id, $this->logistics->id);
    });

    it('lets a sales member add nobody but accounting and logistics', function (Role $role): void {
        $this->actingAs($this->sales)
            ->postJson(route('message.members.store', $this->group), ['userIds' => [messagingStaff($role)->id]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['userIds' => 'Choose people from accounting and logistics.']);

        expect(ConversationMember::query()->where('conversation_id', $this->group->id)->count())->toBe(4);
    })->with([
        'technical' => [Role::Technical],
        'sales' => [Role::Sales],
        'marketing' => [Role::Marketing],
        'an owner' => [Role::Owner],
    ]);

    it('lets nobody but a sales member of the group add people', function (): void {
        $outsider = messagingStaff(Role::Sales, 'Outsider');

        foreach ([$this->marketing, $this->technical, $this->client, $outsider] as $user) {
            $this->actingAs($user)
                ->postJson(route('message.members.store', $this->group), ['userIds' => [$this->accounting->id]])
                ->assertForbidden();
        }

        expect(ConversationMember::query()->where('conversation_id', $this->group->id)->count())->toBe(4);
    });

    it('offers the control to a sales member of an inquiry\'s group only', function (): void {
        $summary = fn ($user) => $this->actingAs($user)->getJson(route('message.thread', $this->group))->json('conversation.canAddMembers');

        expect($summary($this->sales))->toBeTrue()
            ->and($summary($this->marketing))->toBeFalse()
            ->and($summary($this->client))->toBeFalse();
    });
});
