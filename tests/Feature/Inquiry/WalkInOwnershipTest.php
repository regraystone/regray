<?php

use App\Enums\ConversationMemberRole;
use App\Enums\LeadStatus;
use App\Enums\Role;
use App\Enums\SalesStatus;
use App\Models\Client;
use App\Models\Conversation;
use App\Models\Inquiry;
use App\Models\InquiryHandler;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Notification\InquiryNotificationService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

/*
 * Walk-in inquiries are owned: each by one sales user (`owner_user_id`), and
 * read only by its owner, its handlers, the marketing member who opened it,
 * the admin and the owner role. Staff are added to it, never moved or
 * removed, under the gates WalkInInquiryService and the `inquiry_handlers`
 * trigger hold together.
 */

beforeEach(function (): void {
    seedInquiryCatalog();
    $this->marketing = messagingStaff(Role::Marketing, 'Marketing');
    $this->otherMarketing = messagingStaff(Role::Marketing, 'Other Marketing');
    $this->sales = messagingStaff(Role::Sales, 'Sales');
    $this->otherSales = messagingStaff(Role::Sales, 'Other Sales');
    $this->technical = messagingStaff(Role::Technical, 'Technical');
    $this->otherTechnical = messagingStaff(Role::Technical, 'Other Technical');
    $this->accounting = messagingStaff(Role::Accounting, 'Accounting');
    $this->otherAccounting = messagingStaff(Role::Accounting, 'Other Accounting');
    $this->logistics = messagingStaff(Role::Logistics, 'Logistics');
    $this->otherLogistics = messagingStaff(Role::Logistics, 'Other Logistics');
    $this->admin = messagingStaff(Role::Admin, 'Admin');
    $this->ownerRole = messagingStaff(Role::Owner, 'Owner');
});

/** Open a walk-in inquiry over HTTP, as the Inquire page does, and return it. */
function openWalkIn(User $by, ?Client $record = null, ?User $owner = null): Inquiry
{
    $record ??= walkInClient();
    $before = Inquiry::query()->max('id') ?? 0;

    test()->actingAs($by)
        ->post(route('inquiry.direct-clients.inquiries.store', ['client' => $record->id, ...($owner === null ? [] : ['ownerId' => $owner->id])]), ['projects' => [inquiryProjectInput()]])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    return Inquiry::query()->where('id', '>', $before)->sole();
}

/**
 * Run `$write` in a savepoint, so a statement the database refuses fails
 * alone and the test's own transaction carries on.
 */
function refused(Closure $write): Closure
{
    return fn () => DB::transaction($write);
}

/** @param  array<string, mixed>  $overrides */
function walkInDetails(array $overrides = []): array
{
    return ['fullName' => 'Carmela Reyes', 'phone' => '+63 917 555-0142', 'email' => 'Carmela@ReyesBakeshop.example', ...$overrides];
}

/** Win the inquiry's sales track, as the client approving its quotation does. */
function winSales(Inquiry $inquiry): Inquiry
{
    $inquiry->forceFill(['sales_status' => SalesStatus::Won, 'sales_status_changed_at' => now()])->save();

    return $inquiry->refresh();
}

/** @return list<int> */
function handlerIds(Inquiry $inquiry): array
{
    return InquiryHandler::query()->where('inquiry_id', $inquiry->id)->orderBy('user_id')->pluck('user_id')->all();
}

describe('ownership', function (): void {
    it('makes the sales member who opens a walk-in inquiry its owner and its handler', function (): void {
        $inquiry = openWalkIn($this->sales);

        expect($inquiry->owner_user_id)->toBe($this->sales->id)
            ->and($inquiry->created_by)->toBe($this->sales->id)
            ->and($inquiry->sales_status)->toBe(SalesStatus::Negotiation)
            ->and(handlerIds($inquiry))->toBe([$this->sales->id]);
    });

    it('has marketing name a sales owner, who becomes its handler', function (): void {
        $record = walkInClient();

        $this->actingAs($this->marketing)
            ->post(route('inquiry.direct-clients.inquiries.store', $record->id), ['projects' => [inquiryProjectInput()]])
            ->assertSessionHasErrors(['ownerId' => 'Choose the sales member who will own this inquiry.']);

        $this->actingAs($this->marketing)
            ->post(route('inquiry.direct-clients.inquiries.store', ['client' => $record->id, 'ownerId' => $this->technical->id]), ['projects' => [inquiryProjectInput()]])
            ->assertSessionHasErrors('ownerId');

        expect(Inquiry::query()->exists())->toBeFalse();

        $inquiry = openWalkIn($this->marketing, $record, $this->sales);

        expect($inquiry->owner_user_id)->toBe($this->sales->id)
            ->and($inquiry->created_by)->toBe($this->marketing->id)
            ->and(handlerIds($inquiry))->toBe([$this->sales->id]);
    });

    it('shows the owner only its own walk-in inquiries: another sales member gets 404 for the same reference', function (): void {
        $mine = openWalkIn($this->sales);
        $theirs = openWalkIn($this->otherSales);

        $this->actingAs($this->sales)->get(route('inquiry.show', $mine->reference))->assertOk();
        $this->actingAs($this->sales)->get(route('inquiry.show', $theirs->reference))->assertNotFound();
        $this->actingAs($this->otherSales)->get(route('inquiry.show', $mine->reference))->assertNotFound();

        expect(Inquiry::query()->visibleTo($this->sales)->pluck('reference')->all())->toBe([$mine->reference])
            ->and($mine->isVisibleTo($this->sales))->toBeTrue()
            ->and($theirs->isVisibleTo($this->sales))->toBeFalse();
    });

    it('keeps the marketing creator\'s access after it names the owner and assigns technical; other marketing gets 404', function (): void {
        $inquiry = openWalkIn($this->marketing, null, $this->sales);

        $this->actingAs($this->marketing)
            ->post(route('inquiries.handlers.store', $inquiry->reference), ['userId' => $this->technical->id])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAs($this->marketing)->get(route('inquiry.show', $inquiry->reference))->assertOk();
        $this->actingAs($this->otherMarketing)->get(route('inquiry.show', $inquiry->reference))->assertNotFound();

        foreach ([$this->admin, $this->ownerRole, $this->sales, $this->technical] as $reader) {
            $this->actingAs($reader)->get(route('inquiry.show', $inquiry->reference))->assertOk();
        }

        $this->actingAs($this->otherTechnical)->get(route('inquiry.show', $inquiry->reference))->assertNotFound();
    });

    it('holds the owner to a sales user, set only on a walk-in, never changed', function (): void {
        $inquiry = openWalkIn($this->sales);

        expect(refused(fn () => $inquiry->forceFill(['owner_user_id' => $this->otherSales->id])->save()))->toThrow(QueryException::class, 'cannot change once set');

        $lead = makeInquiry(inquiryClient());

        expect(refused(fn () => $lead->forceFill(['owner_user_id' => $this->sales->id])->save()))->toThrow(QueryException::class, 'chk_inquiries_owner_walk_in');

        $other = makeInquiry(inquiryClient());
        DB::table('inquiries')->where('id', $other->id)->update(['client_id' => null, 'client_record_id' => walkInClient()->id]);

        expect(refused(fn () => DB::table('inquiries')->where('id', $other->id)->update(['owner_user_id' => $this->technical->id])))->toThrow(QueryException::class, 'must be a sales user');
    });
});

describe('assignment', function (): void {
    it('lets the owner and the marketing creator add technical staff, any number, and tells them', function (): void {
        $inquiry = openWalkIn($this->marketing, null, $this->sales);

        $this->actingAs($this->sales)->post(route('inquiries.handlers.store', $inquiry->reference), ['userId' => $this->technical->id])->assertSessionHasNoErrors();
        $this->actingAs($this->marketing)->post(route('inquiries.handlers.store', $inquiry->reference), ['userId' => $this->otherTechnical->id])->assertSessionHasNoErrors();

        expect(handlerIds($inquiry))->toEqualCanonicalizing([$this->sales->id, $this->technical->id, $this->otherTechnical->id])
            ->and(notificationBodies($this->technical))->toContain("Sales has assigned you to inquiry {$inquiry->reference}.");

        $this->actingAs($this->sales)
            ->post(route('inquiries.handlers.store', $inquiry->reference), ['userId' => $this->technical->id])
            ->assertSessionHasErrors(['userId' => "Technical is already assigned to {$inquiry->reference}."]);
    });

    it('refuses anyone else adding staff, and anyone who is not a walk-in\'s inquiry', function (): void {
        $inquiry = openWalkIn($this->sales);
        assignInquiry($inquiry, $this->technical);

        $this->actingAs($this->technical)->post(route('inquiries.handlers.store', $inquiry->reference), ['userId' => $this->otherTechnical->id])->assertForbidden();
        $this->actingAs($this->otherSales)->post(route('inquiries.handlers.store', $inquiry->reference), ['userId' => $this->otherTechnical->id])->assertNotFound();

        $this->actingAs($this->sales)
            ->post(route('inquiries.handlers.store', $inquiry->reference), ['userId' => $this->otherMarketing->id])
            ->assertSessionHasErrors(['userId' => 'Choose someone from technical, accounting or logistics.']);

        $lead = makeInquiry(inquiryClient(), [], ['sales_status' => SalesStatus::Negotiation, 'created_by' => $this->sales]);
        $this->actingAs($this->sales)->post(route('inquiries.handlers.store', $lead->reference), ['userId' => $this->technical->id])->assertNotFound();

        expect(handlerIds($inquiry))->toEqualCanonicalizing([$this->sales->id, $this->technical->id]);
    });

    it('refuses accounting and logistics before sales has won it', function (): void {
        $inquiry = openWalkIn($this->sales);

        foreach ([$this->accounting, $this->logistics] as $member) {
            $this->actingAs($this->sales)
                ->post(route('inquiries.handlers.store', $inquiry->reference), ['userId' => $member->id])
                ->assertSessionHasErrors(['userId' => "{$inquiry->reference} is not won yet. Someone from ".strtolower((string) $member->primaryRole()?->label()).' joins once sales has won it.']);
        }

        expect(handlerIds($inquiry))->toBe([$this->sales->id]);
    });

    it('lets the owner alone add one accounting and one logistics member once won', function (): void {
        $inquiry = winSales(openWalkIn($this->marketing, null, $this->sales));

        $this->actingAs($this->marketing)->post(route('inquiries.handlers.store', $inquiry->reference), ['userId' => $this->accounting->id])->assertForbidden();

        $this->actingAs($this->sales)->post(route('inquiries.handlers.store', $inquiry->reference), ['userId' => $this->accounting->id])->assertSessionHasNoErrors();
        $this->actingAs($this->sales)->post(route('inquiries.handlers.store', $inquiry->reference), ['userId' => $this->logistics->id])->assertSessionHasNoErrors();

        $this->actingAs($this->sales)
            ->post(route('inquiries.handlers.store', $inquiry->reference), ['userId' => $this->otherAccounting->id])
            ->assertSessionHasErrors(['userId' => "{$inquiry->reference} already has someone from accounting."]);
        $this->actingAs($this->sales)
            ->post(route('inquiries.handlers.store', $inquiry->reference), ['userId' => $this->otherLogistics->id])
            ->assertSessionHasErrors(['userId' => "{$inquiry->reference} already has someone from logistics."]);

        expect(handlerIds($inquiry))->toEqualCanonicalizing([$this->sales->id, $this->accounting->id, $this->logistics->id]);
    });

    it('shows each reader only the Assign controls they may use', function (): void {
        $inquiry = openWalkIn($this->marketing, null, $this->sales);
        assignInquiry($inquiry, $this->technical);

        $roles = fn (User $reader): array => array_keys($this->actingAs($reader)->get(route('inquiry.show', $inquiry->reference))
            ->assertOk()
            ->viewData('page')['props']['inquiry']['walkIn']['assignable']);

        expect($roles($this->sales))->toBe(['technical'])
            ->and($roles($this->marketing))->toBe(['technical'])
            ->and($roles($this->technical))->toBe([]);

        winSales($inquiry);

        expect($roles($this->sales))->toBe(['technical', 'accounting', 'logistics'])
            ->and($roles($this->marketing))->toBe(['technical']);
    });
});

describe('the inquiry_handlers trigger', function (): void {
    it('refuses a handler whose role plays no stage, and a sales handler other than a walk-in\'s owner', function (): void {
        $inquiry = openWalkIn($this->sales);

        expect(refused(fn () => assignInquiry($inquiry, $this->marketing)))->toThrow(QueryException::class, 'Only sales, technical, accounting or logistics staff')
            ->and(refused(fn () => assignInquiry($inquiry, $this->admin)))->toThrow(QueryException::class, 'Only sales, technical, accounting or logistics staff')
            ->and(refused(fn () => assignInquiry($inquiry, $this->otherSales)))->toThrow(QueryException::class, 'sales member is its owner');
    });

    it('refuses accounting or logistics before won, and a second of either', function (): void {
        $inquiry = openWalkIn($this->sales);

        expect(refused(fn () => assignInquiry($inquiry, $this->logistics)))->toThrow(QueryException::class, 'is not won yet')
            ->and(refused(fn () => assignInquiry($inquiry, $this->accounting)))->toThrow(QueryException::class, 'is not won yet');

        winSales($inquiry);
        assignInquiry($inquiry, $this->logistics, $this->accounting);

        expect(refused(fn () => assignInquiry($inquiry, $this->otherLogistics)))->toThrow(QueryException::class, 'already has someone from logistics')
            ->and(refused(fn () => assignInquiry($inquiry, $this->otherAccounting)))->toThrow(QueryException::class, 'already has someone from accounting')
            ->and(refused(fn () => InquiryHandler::query()->where('inquiry_id', $inquiry->id)->where('user_id', $this->logistics->id)->update(['user_id' => $this->otherLogistics->id])))->toThrow(QueryException::class, 'never moved');
    });
});

describe('one walk-in, several inquiries', function (): void {
    it('lets a second rep open an inquiry on a walk-in on file, see its details, and not the other inquiries', function (): void {
        $this->actingAs($this->marketing)->postJson(route('inquiry.direct-clients.store'), walkInDetails())->assertCreated();
        $record = Client::query()->sole();
        $first = openWalkIn($this->marketing, $record, $this->sales);

        $this->actingAs($this->otherSales)
            ->postJson(route('inquiry.direct-clients.store'), walkInDetails(['fullName' => 'Someone else']))
            ->assertOk()
            ->assertJsonPath('existing', true)
            ->assertJsonPath('client', ['id' => $record->id, 'name' => 'Carmela Reyes', 'fullName' => 'Carmela Reyes', 'company' => null, 'kind' => 'direct']);

        $second = openWalkIn($this->otherSales, $record);

        expect($second->client_record_id)->toBe($record->id)
            ->and($second->owner_user_id)->toBe($this->otherSales->id);

        $this->actingAs($this->otherSales)
            ->get(route('inquiry.show', $second->reference))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('inquiry.walkIn.client.name', 'Carmela Reyes')
                ->where('inquiry.walkIn.client.phone', '+639175550142')
                ->where('inquiry.walkIn.client.email', 'carmela@reyesbakeshop.example')
                ->where('inquiry.walkIn.owner', 'Other Sales')
                ->has('inquiry.project.requirements.address'));

        $this->actingAs($this->otherSales)->get(route('inquiry.show', $first->reference))->assertNotFound();

        expect(Inquiry::query()->visibleTo($this->otherSales)->pluck('reference')->all())->toBe([$second->reference]);
    });

    it('404s a record id that does not exist or is not a walk-in', function (): void {
        $platform = Client::query()->create(['kind' => 'platform', 'user_id' => inquiryClient()->id, 'full_name' => 'Grace']);

        $this->actingAs($this->sales)->post(route('inquiry.direct-clients.inquiries.store', 999999), ['projects' => [inquiryProjectInput()]])->assertNotFound();
        $this->actingAs($this->sales)->post(route('inquiry.direct-clients.inquiries.store', $platform->id), ['projects' => [inquiryProjectInput()]])->assertNotFound();
    });
});

describe('notifications', function (): void {
    it('tells only the walk-in\'s own people about it, never every marketing and sales user', function (): void {
        $inquiry = openWalkIn($this->marketing, null, $this->sales);

        expect(notificationBodies($this->marketing))->toBe(["You have successfully created inquiry {$inquiry->reference} for Wanda Walk-in."])
            ->and(notificationBodies($this->sales))->toBe(["Marketing has created inquiry {$inquiry->reference} for Wanda Walk-in and made you its owner."])
            ->and(notificationBodies($this->admin))->toHaveCount(1)
            ->and(notificationBodies($this->ownerRole))->toHaveCount(1);

        // Unassigned: nobody is a handler any more. Its own people still hear, and nobody else.
        InquiryHandler::query()->where('inquiry_id', $inquiry->id)->delete();
        UserNotification::query()->delete();

        app(InquiryNotificationService::class)->changedByClient($inquiry->refresh(), 'updated');

        $told = UserNotification::query()->pluck('user_id')->unique()->sort()->values()->all();

        expect($told)->toEqualCanonicalizing([$this->marketing->id, $this->sales->id, $this->admin->id, $this->ownerRole->id]);
    });

    it('tells its own people when its status moves', function (): void {
        $inquiry = openWalkIn($this->marketing, null, $this->sales);
        assignInquiry($inquiry, $this->technical);
        UserNotification::query()->delete();

        $this->actingAs($this->sales)
            ->post(route('inquiries.status', $inquiry->reference), ['track' => 'sales', 'to' => 'site_visited', 'note' => 'Visited.'])
            ->assertSessionHasNoErrors();

        $told = UserNotification::query()->pluck('user_id')->unique()->all();

        expect($told)->toEqualCanonicalizing([$this->marketing->id, $this->technical->id, $this->admin->id, $this->ownerRole->id]);
    });
});

describe('marketing\'s decision', function (): void {
    it('is the marketing creator\'s on a walk-in, and nobody else\'s', function (): void {
        $inquiry = openWalkIn($this->marketing, null, $this->sales);

        $this->actingAs($this->otherMarketing)->post(route('inquiries.decision', $inquiry->reference), ['to' => 'won', 'note' => 'Signed.'])->assertNotFound();
        $this->actingAs($this->sales)->post(route('inquiries.decision', $inquiry->reference), ['to' => 'won', 'note' => 'Signed.'])->assertForbidden();

        $this->actingAs($this->marketing)->post(route('inquiries.decision', $inquiry->reference), ['to' => 'won', 'note' => 'Signed.'])->assertSessionHasNoErrors();

        expect($inquiry->refresh()->marketing_status)->toBe(LeadStatus::Won)
            ->and($inquiry->sales_status)->toBe(SalesStatus::Negotiation);
    });
});

describe('walk-in group chat', function (): void {
    it('is made only once sales has won it and the walk-in has an account', function (): void {
        $this->actingAs($this->marketing)->postJson(route('inquiry.direct-clients.store'), walkInDetails())->assertCreated();
        $inquiry = openWalkIn($this->marketing, Client::query()->sole(), $this->sales);
        $payload = fn (array $staff): array => ['name' => 'Reyes Bakeshop', 'staffIds' => $staff, 'inquiryId' => $inquiry->id];

        $this->actingAs($this->marketing)
            ->postJson(route('inbox.groups.walk-in.store'), $payload([$this->sales->id, $this->technical->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['inquiryId' => "{$inquiry->reference} is not won yet. Its group chat waits for sales to win it."]);

        winSales($inquiry);

        $this->actingAs($this->marketing)
            ->postJson(route('inbox.groups.walk-in.store'), $payload([$this->sales->id, $this->technical->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['inquiryId' => 'Carmela Reyes has no Regray account yet. Their group chat waits for an account with their phone number or email.']);

        // The walk-in signs up, with their phone number written another way.
        $account = inquiryClient();
        $account->profile()->create(['phone' => '+63 (917) 555 0142', 'address' => 'Pasig']);

        $this->actingAs($this->marketing)
            ->getJson(route('inbox.walk-in-inquiries'))
            ->assertOk()
            ->assertJsonPath('inquiries.0.account.id', $account->id)
            ->assertJsonPath('inquiries.0.isWon', true)
            ->assertJsonPath('inquiries.0.ownerId', $this->sales->id);

        $this->actingAs($this->marketing)
            ->postJson(route('inbox.groups.walk-in.store'), $payload([$this->otherSales->id, $this->technical->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('staffIds');

        $this->actingAs($this->marketing)
            ->postJson(route('inbox.groups.walk-in.store'), $payload([$this->sales->id, $this->technical->id]))
            ->assertCreated();

        $group = Conversation::query()->where('inquiry_id', $inquiry->id)->sole();
        $members = DB::table('conversation_members')->where('conversation_id', $group->id)->pluck('role', 'user_id')->all();

        expect($members[$account->id] ?? null)->toBe(ConversationMemberRole::Member->value)
            ->and($members[$this->marketing->id] ?? null)->toBe(ConversationMemberRole::Admin->value)
            ->and($inquiry->refresh()->client_id)->toBeNull()
            ->and(handlerIds($inquiry))->toEqualCanonicalizing([$this->sales->id, $this->technical->id]);

        $this->actingAs($this->otherMarketing)
            ->postJson(route('inbox.groups.walk-in.store'), $payload([$this->sales->id, $this->technical->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['inquiryId' => 'Choose one of the walk-in inquiries you can see.']);
    });
});
