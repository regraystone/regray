<?php

use App\Enums\ConversationType;
use App\Enums\InquiryStatus;
use App\Enums\LeadStatus;
use App\Enums\Role;
use App\Models\Conversation;
use App\Models\User;

/*
 * Which clients staff find when they search for one (User::scopeClientsVisibleTo()):
 * the Inbox people search, the group it makes, and the Inquire and Book an
 * appointment pickers. Anyone but the admin and the owner finds only the
 * clients they are authorized for: whose lead they hold, whose inquiries they
 * read, whose Regray Team thread is theirs or still in the pool. A result is
 * shown by name, or by the email an email search matched.
 */

beforeEach(function (): void {
    seedInquiryCatalog();
    $this->marketing = messagingStaff(Role::Marketing, 'Marketing A');
    $this->otherMarketing = messagingStaff(Role::Marketing, 'Marketing B');
    $this->sales = messagingStaff(Role::Sales, 'Sales');
    $this->technical = messagingStaff(Role::Technical, 'Technical');

    $client = fn (string $name, string $email): User => tap(inquiryClient(), fn (User $user) => $user->forceFill(['name' => $name, 'email' => $email])->save());

    // Marketing A holds the lead.
    $this->leadClient = $client('Lena Lead', 'lena@lead.example');
    $this->inquiry = makeInquiry($this->leadClient, attributes: ['lead' => makeLead($this->leadClient, $this->marketing), 'marketing_status' => LeadStatus::Won, 'status' => InquiryStatus::Negotiation]);

    // Nobody has claimed the thread: it is in the support pool.
    $this->poolClient = $client('Paolo Pool', 'paolo@pool.example');
    supportThreadFor($this->poolClient);

    // Marketing A claimed the thread.
    $this->claimedClient = $client('Clara Claimed', 'clara@claimed.example');
    supportThreadFor($this->claimedClient)->forceFill(['assigned_to' => $this->marketing->id, 'assigned_at' => now()])->save();

    // Marketing B claimed the thread and holds the lead.
    $this->foreignClient = $client('Fiona Foreign', 'fiona@foreign.example');
    supportThreadFor($this->foreignClient)->forceFill(['assigned_to' => $this->otherMarketing->id, 'assigned_at' => now()])->save();
    makeInquiry($this->foreignClient, attributes: ['lead' => makeLead($this->foreignClient, $this->otherMarketing), 'marketing_status' => LeadStatus::Won]);
});

/** @return list<int> The ids of the clients the Inbox people search returns. */
function peopleClientIds(object $test, User $as, ?string $search = null): array
{
    return array_column($test->actingAs($as)->getJson(route('inbox.people', $search === null ? [] : ['search' => $search]))->assertOk()->json('clients'), 'id');
}

describe('the Inbox people search', function (): void {
    it('shows marketing only the clients it holds, has claimed, or are still in the pool', function (): void {
        expect(peopleClientIds($this, $this->marketing))->toEqualCanonicalizing([
            $this->leadClient->id,
            $this->poolClient->id,
            $this->claimedClient->id,
        ]);
    });

    it('hides a client whose thread another marketer has claimed, even by an exact email search', function (): void {
        expect(peopleClientIds($this, $this->marketing, 'Fiona'))->toBe([])
            ->and(peopleClientIds($this, $this->marketing, 'fiona@foreign.example'))->toBe([])
            ->and(peopleClientIds($this, $this->otherMarketing, 'fiona@foreign.example'))->toBe([$this->foreignClient->id]);
    });

    it('applies the scope before the limit, so hidden clients never crowd out the ones in scope', function (): void {
        foreach (range(1, 25) as $n) {
            tap(inquiryClient(), fn (User $user) => $user->forceFill(['name' => "Aaron Hidden {$n}"])->save());
        }

        expect(peopleClientIds($this, $this->marketing))->toHaveCount(3);
    });

    it('shows a name search by name', function (): void {
        $this->actingAs($this->marketing)->getJson(route('inbox.people', ['search' => 'lena']))
            ->assertOk()
            ->assertJsonPath('clients', [['id' => $this->leadClient->id, 'name' => 'Lena Lead', 'label' => 'Lena Lead']]);
    });

    it('shows a client found by email by the email it matched', function (): void {
        $this->actingAs($this->marketing)->getJson(route('inbox.people', ['search' => 'Paolo@Pool']))
            ->assertOk()
            ->assertJsonPath('clients', [['id' => $this->poolClient->id, 'name' => 'Paolo Pool', 'label' => 'paolo@pool.example']]);
    });

    it('shows a client by name when an "@" search matched the name and not the email', function (): void {
        $this->leadClient->forceFill(['name' => 'Lena @ Studio'])->save();

        $this->actingAs($this->marketing)->getJson(route('inbox.people', ['search' => '@ studio']))
            ->assertOk()
            ->assertJsonPath('clients.0.label', 'Lena @ Studio');
    });

    it('sends no inquiries for a client outside the scope', function (): void {
        $this->actingAs($this->marketing)->getJson(route('inbox.people', ['clientId' => $this->foreignClient->id]))
            ->assertOk()
            ->assertJsonPath('inquiries', []);
    });

    it('keeps the inquiries scoped to those the marketer reads, though the client is in the pool', function (): void {
        // Paolo is in scope through the pool, but his lead is Marketing B's.
        makeInquiry($this->poolClient, attributes: ['reference' => 'INQ-POOL', 'lead' => makeLead($this->poolClient, $this->otherMarketing)]);
        $own = makeInquiry($this->leadClient, attributes: ['reference' => 'INQ-OWN', 'lead' => $this->inquiry->lead]);

        expect(peopleClientIds($this, $this->marketing, 'Paolo'))->toBe([$this->poolClient->id]);

        $this->actingAs($this->marketing)->getJson(route('inbox.people', ['clientId' => $this->poolClient->id]))
            ->assertOk()
            ->assertJsonPath('inquiries', []);

        expect(array_column($this->actingAs($this->marketing)->getJson(route('inbox.people', ['clientId' => $this->leadClient->id]))->json('inquiries'), 'id'))
            ->toEqualCanonicalizing([$this->inquiry->id, $own->id]);
    });

    it('shows the admin every client', function (): void {
        expect(peopleClientIds($this, messagingStaff(Role::Admin, 'Admin')))->toEqualCanonicalizing(
            User::role(Role::Client->value)->pluck('id')->all(),
        );
    });
});

describe('making a group', function (): void {
    it('refuses a client outside the scope, and writes nothing', function (): void {
        $foreignInquiry = $this->foreignClient->inquiries()->sole();

        $this->actingAs($this->marketing)->postJson(route('inbox.groups.store'), [
            'name' => 'Not mine',
            'staffIds' => [$this->sales->id, $this->technical->id],
            'clientId' => $this->foreignClient->id,
            'inquiryId' => $foreignInquiry->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['clientId' => 'Choose one client.']);

        expect(Conversation::query()->where('type', ConversationType::Group)->exists())->toBeFalse();
    });

    it('makes one with a client in scope', function (): void {
        $this->actingAs($this->marketing)->postJson(route('inbox.groups.store'), [
            'name' => 'Mine',
            'staffIds' => [$this->sales->id, $this->technical->id],
            'clientId' => $this->leadClient->id,
            'inquiryId' => $this->inquiry->id,
        ])->assertCreated();
    });
});

describe('the Inquire and Book an appointment pickers', function (): void {
    it('find only the clients in scope, with the same label', function (string $route): void {
        $this->actingAs($this->marketing)->getJson(route($route))
            ->assertOk()
            ->assertJsonCount(3, 'accounts');

        $this->actingAs($this->marketing)->getJson(route($route, ['search' => 'fiona@foreign.example']))
            ->assertOk()
            ->assertExactJson(['accounts' => []]);

        $this->actingAs($this->marketing)->getJson(route($route, ['search' => 'clara@']))
            ->assertOk()
            ->assertExactJson(['accounts' => [['id' => $this->claimedClient->id, 'name' => 'Clara Claimed', 'email' => 'clara@claimed.example', 'label' => 'clara@claimed.example']]]);

        $this->actingAs($this->marketing)->getJson(route($route, ['search' => 'clara']))
            ->assertOk()
            ->assertJsonPath('accounts.0.label', 'Clara Claimed');
    })->with(['inquiry.inquire.accounts', 'schedule.book.accounts']);

    it('find for sales only the clients of the inquiries it reads', function (string $route): void {
        $this->actingAs($this->sales)->getJson(route($route))
            ->assertOk()
            ->assertExactJson(['accounts' => []]);

        assignInquiry($this->inquiry, $this->sales);

        $this->actingAs($this->sales)->getJson(route($route))
            ->assertOk()
            ->assertJsonPath('accounts.*.id', [$this->leadClient->id]);
    })->with(['inquiry.inquire.accounts', 'schedule.book.accounts']);

    it('find every client for the admin', function (): void {
        $this->actingAs(messagingStaff(Role::Admin, 'Admin'))->getJson(route('inquiry.inquire.accounts'))
            ->assertOk()
            ->assertJsonCount(User::role(Role::Client->value)->count(), 'accounts');
    });
});
