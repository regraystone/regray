<?php

use App\Enums\ConversationMemberRole;
use App\Enums\ConversationType;
use App\Enums\InquiryStatus;
use App\Enums\LeadStatus;
use App\Enums\Role;
use App\Enums\SalesStatus;
use App\Models\Conversation;
use App\Models\ConversationMember;
use App\Models\InquiryHandler;
use App\Repositories\Contracts\ConversationMemberRepositoryInterface;

/*
 * Group chats: marketing makes one for one client and one of that client's
 * inquiries it reads, with exactly one sales and one technical member, and the
 * creator is its admin. Being in the group assigns them to the inquiry. Later a
 * sales member adds exactly one accounting and one logistics member, together.
 * Owners are never members, and nobody is assigned but the group's staff.
 */

beforeEach(function (): void {
    seedInquiryCatalog();
    $this->marketing = messagingStaff(Role::Marketing, 'Marketing');
    $this->sales = messagingStaff(Role::Sales, 'Sales');
    $this->technical = messagingStaff(Role::Technical, 'Technical');
    $this->owner = messagingStaff(Role::Owner, 'Owner');
    $this->client = inquiryClient();
    // Won by marketing: a group chat waits for that.
    $this->inquiry = makeInquiry($this->client, attributes: ['reference' => 'INQ-G1', 'lead' => makeLead($this->client, $this->marketing), 'marketing_status' => LeadStatus::Won, 'status' => InquiryStatus::Negotiation]);
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

/** @return array<int, int> Each handler's user id, by who assigned them. */
function handlersOf(int $inquiryId): array
{
    return InquiryHandler::query()->where('inquiry_id', $inquiryId)->pluck('assigned_by', 'user_id')->all();
}

describe('making a group', function (): void {
    it('puts one sales and one technical member in it, makes the creator its admin, and assigns the two to the inquiry', function (): void {
        $this->actingAs($this->marketing)->postJson(route('inbox.groups.store'), groupInput($this))
            ->assertCreated()
            ->assertJsonPath('conversation.name', 'Kitchen project');

        $group = Conversation::query()->where('type', ConversationType::Group)->sole();
        $roles = ConversationMember::query()->where('conversation_id', $group->id)->pluck('role', 'user_id');

        expect($group->inquiry_id)->toBe($this->inquiry->id)
            ->and($roles->all())->toEqualCanonicalizing([
                $this->marketing->id => ConversationMemberRole::Admin,
                $this->sales->id => ConversationMemberRole::Member,
                $this->technical->id => ConversationMemberRole::Member,
                $this->client->id => ConversationMemberRole::Member,
            ])
            ->and(handlersOf($this->inquiry->id))->toEqualCanonicalizing([
                $this->sales->id => $this->marketing->id,
                $this->technical->id => $this->marketing->id,
            ]);
    });

    it('never puts an owner in it or assigns one', function (): void {
        $this->actingAs($this->marketing)->postJson(route('inbox.groups.store'), groupInput($this))->assertCreated();

        expect(ConversationMember::query()->where('user_id', $this->owner->id)->exists())->toBeFalse()
            ->and(InquiryHandler::query()->where('user_id', $this->owner->id)->exists())->toBeFalse();
    });

    it('refuses anything but exactly one sales and one technical member, and writes nothing', function (Closure $staff): void {
        $this->actingAs($this->marketing)
            ->postJson(route('inbox.groups.store'), groupInput($this, ['staffIds' => $staff($this)]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['staffIds' => 'Choose one sales member and one technical member.']);

        expect(Conversation::query()->where('type', ConversationType::Group)->exists())->toBeFalse()
            ->and(InquiryHandler::query()->exists())->toBeFalse();
    })->with([
        'no sales member' => [fn (object $test): array => [$test->technical->id]],
        'two sales members' => [fn (object $test): array => [$test->sales->id, messagingStaff(Role::Sales)->id, $test->technical->id]],
        'two sales and no technical' => [fn (object $test): array => [$test->sales->id, messagingStaff(Role::Sales)->id]],
        'no technical member' => [fn (object $test): array => [$test->sales->id]],
        'two technical members' => [fn (object $test): array => [$test->technical->id, messagingStaff(Role::Technical)->id]],
        'accounting in place of technical' => [fn (object $test): array => [$test->sales->id, messagingStaff(Role::Accounting)->id]],
        'an owner in place of technical' => [fn (object $test): array => [$test->sales->id, $test->owner->id]],
        'a client in place of technical' => [fn (object $test): array => [$test->sales->id, inquiryClient()->id]],
    ]);

    it('refuses an inquiry the marketer does not read', function (): void {
        $foreign = makeInquiry($this->client, attributes: ['reference' => 'INQ-G3', 'lead' => makeLead(inquiryClient())]);

        $this->actingAs($this->marketing)
            ->postJson(route('inbox.groups.store'), groupInput($this, ['inquiryId' => $foreign->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('inquiryId');

        expect(Conversation::query()->where('type', ConversationType::Group)->exists())->toBeFalse()
            ->and(InquiryHandler::query()->exists())->toBeFalse();
    });

    it('refuses an inquiry that is not the chosen client\'s', function (): void {
        $other = makeInquiry(inquiryClient(), attributes: ['reference' => 'INQ-G2', 'created_by' => $this->marketing]);

        $this->actingAs($this->marketing)
            ->postJson(route('inbox.groups.store'), groupInput($this, ['inquiryId' => $other->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('inquiryId');

        expect(Conversation::query()->where('type', ConversationType::Group)->exists())->toBeFalse();
    });

    it('gives an inquiry one group chat at most', function (): void {
        $this->actingAs($this->marketing)->postJson(route('inbox.groups.store'), groupInput($this))->assertCreated();

        $this->actingAs($this->marketing)
            ->postJson(route('inbox.groups.store'), groupInput($this, ['name' => 'Again']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['inquiryId' => 'INQ-G1 already has a group chat.']);

        expect(Conversation::query()->where('type', ConversationType::Group)->count())->toBe(1);
    });

    it('refuses an inquiry marketing has not won yet', function (LeadStatus $decision): void {
        $waiting = makeInquiry($this->client, attributes: [
            'reference' => 'INQ-G5',
            'lead' => $this->inquiry->lead,
            'marketing_status' => $decision === LeadStatus::Idle ? null : $decision,
        ]);

        $this->actingAs($this->marketing)
            ->postJson(route('inbox.groups.store'), groupInput($this, ['inquiryId' => $waiting->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['inquiryId' => 'INQ-G5 is not won yet. Mark it won before making its group chat.']);

        expect(Conversation::query()->where('inquiry_id', $waiting->id)->exists())->toBeFalse()
            ->and($this->marketing->can('createGroup', $waiting))->toBeFalse()
            ->and($this->marketing->can('createGroup', $this->inquiry))->toBeTrue();
    })->with([LeadStatus::Idle, LeadStatus::Lost]);

    it('refuses the admin too, who passes every permission', function (): void {
        $waiting = makeInquiry($this->client, attributes: ['reference' => 'INQ-G6', 'lead' => $this->inquiry->lead]);

        $this->actingAs(messagingStaff(Role::Admin))
            ->postJson(route('inbox.groups.store'), groupInput($this, ['inquiryId' => $waiting->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['inquiryId']);
    });
});

describe('the people to make it with', function (): void {
    it('offers sales and technical with their active inquiry counts, no owners, and only the inquiries the marketer reads', function (): void {
        messagingStaff(Role::Accounting, 'Accounting');
        messagingStaff(Role::Logistics, 'Logistics');
        makeInquiry($this->client, attributes: ['reference' => 'INQ-G4', 'lead' => makeLead(inquiryClient())]);
        assignInquiry(makeInquiry(inquiryClient()), $this->sales);
        assignInquiry(makeInquiry(inquiryClient()), $this->sales);

        $people = $this->actingAs($this->marketing)->getJson(route('inbox.people', ['clientId' => $this->client->id]))->assertOk();

        expect($people->json('sales'))->toBe([['id' => $this->sales->id, 'name' => 'Sales', 'activeInquiryCount' => 2]])
            ->and($people->json('technical'))->toBe([['id' => $this->technical->id, 'name' => 'Technical', 'activeInquiryCount' => 0]])
            ->and($people->json())->not->toHaveKey('owners')
            ->and($people->json('inquiries'))->toBe([['id' => $this->inquiry->id, 'reference' => 'INQ-G1', 'name' => $this->inquiry->name, 'hasGroup' => false, 'isWon' => true]]);
    });

    it('counts only the assigned inquiries that are not closed or cancelled', function (): void {
        foreach ([InquiryStatus::Pending, InquiryStatus::Negotiation, InquiryStatus::Production, InquiryStatus::ForRefund, InquiryStatus::Closed, InquiryStatus::Cancelled] as $status) {
            assignInquiry(makeInquiry(inquiryClient(), attributes: ['status' => $status]), $this->technical);
        }

        // Marketing's lost decision cancels an inquiry too, whatever sales says.
        assignInquiry(makeInquiry(inquiryClient(), attributes: ['lead' => makeLead(inquiryClient()), 'marketing_status' => LeadStatus::Lost]), $this->technical);

        $people = $this->actingAs($this->marketing)->getJson(route('inbox.people'))->assertOk();

        expect($people->json('technical.0.activeInquiryCount'))->toBe(4);
    });
});

describe('adding accounting and logistics later', function (): void {
    beforeEach(function (): void {
        $this->group = groupConversation([$this->marketing, $this->sales, $this->technical, $this->client], [$this->marketing->id => ConversationMemberRole::Admin], ['inquiry_id' => $this->inquiry->id]);
        assignInquiry($this->inquiry, $this->sales, $this->technical);
        // Accounting and logistics join once sales has won the inquiry.
        $this->inquiry->forceFill(['sales_status' => SalesStatus::Won, 'sales_status_changed_at' => now()])->save();
        $this->accounting = messagingStaff(Role::Accounting, 'Accounting');
        $this->logistics = messagingStaff(Role::Logistics, 'Logistics');
    });

    it('offers one picker each, with their active inquiry counts', function (): void {
        assignInquiry(makeInquiry(inquiryClient(), attributes: ['sales_status' => SalesStatus::Won]), $this->logistics);

        $this->actingAs($this->sales)->getJson(route('inbox.members.index', $this->group))
            ->assertOk()
            ->assertExactJson([
                'accounting' => [['id' => $this->accounting->id, 'name' => 'Accounting', 'activeInquiryCount' => 0]],
                'logistics' => [['id' => $this->logistics->id, 'name' => 'Logistics', 'activeInquiryCount' => 1]],
            ]);
    });

    it('lets a sales member add one of each, and assigns both to the inquiry by that member', function (): void {
        $this->actingAs($this->sales)
            ->postJson(route('inbox.members.store', $this->group), ['accountingId' => $this->accounting->id, 'logisticsId' => $this->logistics->id])
            ->assertOk()
            ->assertJsonPath('conversation.memberCount', 6);

        expect(ConversationMember::query()->where('conversation_id', $this->group->id)->pluck('user_id')->all())
            ->toContain($this->accounting->id, $this->logistics->id)
            ->and(handlersOf($this->inquiry->id))->toMatchArray([
                $this->accounting->id => $this->sales->id,
                $this->logistics->id => $this->sales->id,
            ]);
    });

    it('refuses a request without exactly one accounting and one logistics user', function (Closure $payload, string $field, string $message): void {
        $this->actingAs($this->sales)
            ->postJson(route('inbox.members.store', $this->group), $payload($this))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field => $message]);

        expect(ConversationMember::query()->where('conversation_id', $this->group->id)->count())->toBe(4)
            ->and(InquiryHandler::query()->whereIn('user_id', [$this->accounting->id, $this->logistics->id])->exists())->toBeFalse();
    })->with([
        'no logistics user' => [fn (object $test): array => ['accountingId' => $test->accounting->id], 'logisticsId', 'Choose one person from logistics.'],
        'no accounting user' => [fn (object $test): array => ['logisticsId' => $test->logistics->id], 'accountingId', 'Choose one person from accounting.'],
        'two accounting users' => [fn (object $test): array => ['accountingId' => $test->accounting->id, 'logisticsId' => messagingStaff(Role::Accounting)->id], 'logisticsId', 'Choose one person from logistics.'],
        'a technical user for accounting' => [fn (object $test): array => ['accountingId' => messagingStaff(Role::Technical)->id, 'logisticsId' => $test->logistics->id], 'accountingId', 'Choose one person from accounting.'],
        'an owner for logistics' => [fn (object $test): array => ['accountingId' => $test->accounting->id, 'logisticsId' => $test->owner->id], 'logisticsId', 'Choose one person from logistics.'],
        'a list of people' => [fn (object $test): array => ['userIds' => [$test->accounting->id, $test->logistics->id]], 'accountingId', 'Choose one person from accounting.'],
    ]);

    it('refuses them before sales has won the inquiry', function (): void {
        $this->inquiry->forceFill(['sales_status' => SalesStatus::Negotiation])->save();

        $this->actingAs($this->sales)
            ->postJson(route('inbox.members.store', $this->group), ['accountingId' => $this->accounting->id, 'logisticsId' => $this->logistics->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['accountingId' => 'INQ-G1 is not won yet. Accounting and logistics join once sales has won it.']);

        expect(InquiryHandler::query()->whereIn('user_id', [$this->accounting->id, $this->logistics->id])->exists())->toBeFalse();
    });

    it('refuses the request once the group has someone from accounting or logistics', function (Role $role, string $field): void {
        $taken = messagingStaff($role);
        app(ConversationMemberRepositoryInterface::class)->upsert($this->group->id, $taken->id, ConversationMemberRole::Member);

        $this->actingAs($this->sales)
            ->postJson(route('inbox.members.store', $this->group), ['accountingId' => $this->accounting->id, 'logisticsId' => $this->logistics->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field => "This group already has someone from {$role->value}."]);

        expect(ConversationMember::query()->where('conversation_id', $this->group->id)->pluck('user_id')->all())
            ->not->toContain($this->accounting->id, $this->logistics->id);

        $this->actingAs($this->sales)->getJson(route('inbox.members.index', $this->group))
            ->assertOk()
            ->assertJsonPath(strtolower($role->value), []);
    })->with([
        'accounting' => [Role::Accounting, 'accountingId'],
        'logistics' => [Role::Logistics, 'logisticsId'],
    ]);

    it('lets nobody but a sales member of the group add people', function (): void {
        $outsider = messagingStaff(Role::Sales, 'Outsider');
        assignInquiry($this->inquiry, $outsider);

        foreach ([$this->marketing, $this->technical, $this->client, $this->owner, $outsider] as $user) {
            $this->actingAs($user)
                ->postJson(route('inbox.members.store', $this->group), ['accountingId' => $this->accounting->id, 'logisticsId' => $this->logistics->id])
                ->assertForbidden();
        }

        expect(ConversationMember::query()->where('conversation_id', $this->group->id)->count())->toBe(4);
    });

    it('refuses a sales member of a group whose inquiry they do not read', function (): void {
        $member = messagingStaff(Role::Sales, 'Unassigned');
        app(ConversationMemberRepositoryInterface::class)->upsert($this->group->id, $member->id, ConversationMemberRole::Member);

        $this->actingAs($member)
            ->postJson(route('inbox.members.store', $this->group), ['accountingId' => $this->accounting->id, 'logisticsId' => $this->logistics->id])
            ->assertForbidden();

        $this->actingAs($member)->getJson(route('inbox.members.index', $this->group))->assertForbidden();
    });

    it('offers the control to a sales member of an inquiry\'s group only', function (): void {
        $summary = fn ($user) => $this->actingAs($user)->getJson(route('inbox.thread', $this->group))->json('conversation.canAddMembers');

        expect($summary($this->sales))->toBeTrue()
            ->and($summary($this->marketing))->toBeFalse()
            ->and($summary($this->client))->toBeFalse();
    });
});
