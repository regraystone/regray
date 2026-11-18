<?php

use App\Enums\ClientKind;
use App\Enums\InquiryStatus;
use App\Enums\InquiryStatusTrack;
use App\Enums\MessageType;
use App\Enums\Role;
use App\Enums\SalesStatus;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Inquiry;
use App\Models\InquiryStatusHistory;
use App\Models\Lead;
use App\Models\Message;
use App\Models\User;

/*
 * The staff Inquire flow's two branches. Platform: the client has a Regray
 * account; pick it, then one of its leads. Direct (walk-in): take the client
 * down, with a duplicate check, and open the inquiry with no lead.
 */

beforeEach(function (): void {
    seedInquiryCatalog();
    $this->marketing = messagingStaff(Role::Marketing, 'Marketing');
    $this->sales = messagingStaff(Role::Sales, 'Sales');
});

/** @param  array<string, mixed>  $overrides */
function walkInInput(array $overrides = []): array
{
    return [
        'fullName' => 'Carmela Reyes',
        'company' => 'Reyes Bakeshop',
        'address' => 'Pasig',
        'contacts' => [
            ['kind' => 'phone', 'value' => '+63 917 555-0142'],
            ['kind' => 'email', 'value' => 'Carmela@ReyesBakeshop.example'],
        ],
        ...$overrides,
    ];
}

describe('platform', function (): void {
    it('finds the client accounts the user is authorized for by name or email, for staff only', function (): void {
        $grace = inquiryClient();
        $grace->forceFill(['name' => 'Grace Lim', 'email' => 'grace@maplelane.example'])->save();
        makeLead($grace, $this->marketing);
        // Another marketer's client: out of scope, however it is searched.
        $hidden = tap(inquiryClient(), fn (User $user) => $user->forceFill(['name' => 'Grace Hidden'])->save());
        makeLead($hidden);
        inquiryClient()->forceFill(['name' => 'Ivan Mercado'])->save();
        User::factory()->role(Role::Sales)->create(['name' => 'Grace Staff']);

        $this->actingAs($this->marketing)->getJson(route('inquiry.inquire.accounts', ['search' => 'grace']))
            ->assertOk()
            ->assertExactJson(['accounts' => [['id' => $grace->id, 'name' => 'Grace Lim', 'email' => 'grace@maplelane.example', 'label' => 'Grace Lim']]]);

        $this->actingAs($this->marketing)->getJson(route('inquiry.inquire.accounts', ['search' => 'grace@maple']))
            ->assertOk()
            ->assertJsonPath('accounts.0.label', 'grace@maplelane.example');

        $this->actingAs($grace)->getJson(route('inquiry.inquire.accounts'))->assertForbidden();
    });

    it('files the inquiry under the account\'s platform record, made once, and links it to the lead', function (): void {
        $client = inquiryClient();
        $lead = makeLead($client, $this->marketing);

        expect(Client::query()->exists())->toBeFalse();

        foreach (['Kitchen', 'Bath'] as $name) {
            $this->actingAs($this->marketing)->post(route('leads.inquiries.store', $lead->reference), ['projects' => [inquiryProjectInput(['name' => $name])]])->assertRedirect();
        }

        $record = Client::query()->sole();

        expect($record->kind)->toBe(ClientKind::Platform)
            ->and($record->user_id)->toBe($client->id)
            ->and(Inquiry::query()->pluck('client_record_id')->unique()->all())->toBe([$record->id])
            ->and(Inquiry::query()->pluck('lead_id')->unique()->all())->toBe([$lead->id]);
    });

    it('files a client\'s own inquiry under their platform record too', function (): void {
        $client = inquiryClient();

        $this->actingAs($client)->post(route('quote.inquire.store'), clientInquiryInput([inquiryProjectInput()]))->assertRedirect();

        expect(Inquiry::query()->sole()->clientRecord->user_id)->toBe($client->id);
    });
});

describe('direct (walk-in)', function (): void {
    it('takes the client down with their contacts, normalised', function (): void {
        $this->actingAs($this->sales)->postJson(route('inquiry.direct-clients.store'), walkInInput())
            ->assertCreated()
            ->assertJsonPath('existing', false)
            ->assertJsonPath('client.fullName', 'Carmela Reyes')
            ->assertJsonPath('client.kind', 'direct');

        $client = Client::query()->with('contacts')->sole();

        expect($client->kind)->toBe(ClientKind::Direct)
            ->and($client->user_id)->toBeNull()
            ->and($client->created_by)->toBe($this->sales->id)
            ->and($client->contacts->map(fn (ClientContact $contact): string => "{$contact->kind->value}:{$contact->value}")->all())
            ->toBe(['phone:+639175550142', 'email:carmela@reyesbakeshop.example']);
    });

    it('offers the client who already has one of the contacts instead of creating another', function (): void {
        $this->actingAs($this->marketing)->postJson(route('inquiry.direct-clients.store'), walkInInput())->assertCreated();
        $first = Client::query()->sole();

        $this->actingAs($this->sales)
            ->postJson(route('inquiry.direct-clients.store'), walkInInput([
                'fullName' => 'C. Reyes',
                'contacts' => [['kind' => 'phone', 'value' => '+63 918 000 0000'], ['kind' => 'email', 'value' => ' carmela@reyesbakeshop.EXAMPLE ']],
            ]))
            ->assertOk()
            ->assertJsonPath('existing', true)
            ->assertJsonPath('client.id', $first->id)
            ->assertJsonPath('client.fullName', 'Carmela Reyes')
            ->assertJsonPath('matched', ['kind' => 'email', 'value' => 'carmela@reyesbakeshop.example']);

        expect(Client::query()->count())->toBe(1)
            ->and(ClientContact::query()->count())->toBe(2);
    });

    it('needs the client\'s phone number', function (): void {
        $this->actingAs($this->sales)
            ->postJson(route('inquiry.direct-clients.store'), walkInInput(['contacts' => [['kind' => 'email', 'value' => 'carmela@reyesbakeshop.example']]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['contacts.0.value' => "Enter the client's phone number."]);

        expect(Client::query()->exists())->toBeFalse();
    });

    it('needs a name and at least one valid contact', function (): void {
        $this->actingAs($this->sales)
            ->postJson(route('inquiry.direct-clients.store'), ['fullName' => '', 'contacts' => [['kind' => 'email', 'value' => 'not-an-email'], ['kind' => 'walk_in', 'value' => 'x']]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fullName', 'contacts.0.value', 'contacts.1.kind']);

        expect(Client::query()->exists())->toBeFalse();
    });

    it('opens the inquiry with no lead and sales at negotiation straight away, posting no card', function (): void {
        $this->actingAs($this->marketing)->postJson(route('inquiry.direct-clients.store'), walkInInput())->assertCreated();
        $record = Client::query()->sole();

        $this->actingAs($this->sales)
            ->post(route('inquiry.direct-clients.inquiries.store', $record->id), ['projects' => [inquiryProjectInput()]])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $inquiry = Inquiry::query()->with('schedule')->sole();
        $history = InquiryStatusHistory::query()->sole();

        expect($inquiry->client_id)->toBeNull()
            ->and($inquiry->client_record_id)->toBe($record->id)
            ->and($inquiry->lead_id)->toBeNull()
            ->and(Lead::query()->exists())->toBeFalse()
            ->and($inquiry->sales_status)->toBe(SalesStatus::Negotiation)
            ->and($inquiry->status)->toBe(InquiryStatus::Negotiation)
            ->and([$history->track, $history->from_status, $history->to_status, $history->changed_by])->toBe([InquiryStatusTrack::Sales, null, 'negotiation', $this->sales->id])
            ->and($inquiry->schedule->client_id)->toBeNull()
            ->and($inquiry->schedule->booked_by)->toBe($this->sales->id)
            ->and(Message::query()->where('type', MessageType::InquiryCard)->exists())->toBeFalse();
    });

    it('refuses a platform record on the direct route: an account\'s inquiry goes through its lead', function (): void {
        $record = Client::query()->create(['kind' => ClientKind::Platform, 'user_id' => inquiryClient()->id, 'full_name' => 'Grace']);

        $this->actingAs($this->sales)
            ->post(route('inquiry.direct-clients.inquiries.store', $record->id), ['projects' => [inquiryProjectInput()]])
            ->assertNotFound();

        expect(Inquiry::query()->exists())->toBeFalse();
    });
});
