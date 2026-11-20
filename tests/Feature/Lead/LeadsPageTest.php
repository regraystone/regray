<?php

use App\Enums\InquiryStatus;
use App\Enums\LeadStatus;
use App\Enums\Role;
use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Http\UploadedFile;

/*
 * The Leads page is marketing's: each lead with the tally of its inquiries'
 * decisions, and Won or Lost on each inquiry still waiting. Staff open
 * inquiries for a lead's client: marketing from its own leads, sales only from
 * a lead with a won inquiry.
 */

beforeEach(function (): void {
    seedInquiryCatalog();
    $this->marketing = messagingStaff(Role::Marketing, 'Marketing');
    $this->client = inquiryClient();
});

it('opens for marketing with its own leads, newest first, each with its phone, email, tally, inquiries and actions', function (): void {
    $idle = makeLead($this->client, $this->marketing);
    makeInquiry($this->client, attributes: ['reference' => 'INQ-P1', 'lead' => $idle]);
    $this->client->profile()->create(['phone' => '+63 917 000 0001', 'address' => 'Makati']);
    $wonClient = inquiryClient();
    $won = makeLead($wonClient, $this->marketing);
    makeInquiry($wonClient, attributes: ['reference' => 'INQ-P2', 'lead' => $won, 'marketing_status' => LeadStatus::Won, 'status' => InquiryStatus::Negotiation]);
    makeInquiry($wonClient, attributes: ['reference' => 'INQ-P3', 'lead' => $won, 'marketing_status' => LeadStatus::Lost]);
    // Someone else's lead is not on this page.
    makeLead(inquiryClient());

    $props = $this->actingAs($this->marketing)->get(route('leads.index'))->assertOk()->viewData('page')['props'];
    $rows = collect($props['leads']['data'])->keyBy('reference');

    expect($props['leads']['meta'])->toMatchArray(['currentPage' => 1, 'perPage' => 10, 'total' => 2])
        ->and($props['filters'])->toBe(['search' => '', 'page' => 1, 'perPage' => 10, 'status' => 'all'])
        ->and($rows->keys()->sort()->values()->all())->toBe(collect([$idle->reference, $won->reference])->sort()->values()->all())
        ->and($rows[$idle->reference])->toMatchArray([
            'tally' => ['won' => 0, 'lost' => 0, 'idle' => 1],
            'client' => ['id' => $this->client->id, 'name' => $this->client->name],
            'contact' => '+63 917 000 0001',
            'email' => $this->client->email,
            'actions' => ['view', 'edit'],
            'canCreateInquiry' => true,
        ])
        ->and($rows[$idle->reference]['inquiries'][0])->toMatchArray(['reference' => 'INQ-P1', 'marketingStatus' => 'idle', 'canDecide' => true, 'canCreateGroup' => false])
        // Won and Lost only on an inquiry still waiting; a group chat only on a won one.
        ->and($rows[$won->reference]['tally'])->toBe(['won' => 1, 'lost' => 1, 'idle' => 0])
        ->and(collect($rows[$won->reference]['inquiries'])->map(fn (array $inquiry): array => array_intersect_key($inquiry, array_flip(['reference', 'marketingStatus', 'canDecide', 'canCreateGroup'])))->all())->toBe([
            ['reference' => 'INQ-P2', 'marketingStatus' => 'won', 'canDecide' => false, 'canCreateGroup' => true],
            ['reference' => 'INQ-P3', 'marketingStatus' => 'lost', 'canDecide' => false, 'canCreateGroup' => false],
        ]);

    $searched = $this->actingAs($this->marketing)->get(route('leads.index', ['search' => $this->client->name, 'perPage' => 25]))->viewData('page')['props'];

    expect(collect($searched['leads']['data'])->pluck('reference')->all())->toBe([$idle->reference])
        ->and($searched['filters']['perPage'])->toBe(10); // Ten a page, whatever is asked.
});

it('lets the admin in, with every lead', function (): void {
    makeLead($this->client, $this->marketing);
    makeLead(inquiryClient());

    $this->actingAs(User::factory()->role(Role::Admin)->create())->get(route('leads.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('leads', 2));
});

describe('opening an inquiry for a lead', function (): void {
    beforeEach(function (): void {
        $this->sales = messagingStaff(Role::Sales, 'Sales');
        // Nothing decided yet; one inquiry won; one inquiry lost.
        $this->idle = makeLead($this->client, $this->marketing);
        $wonClient = inquiryClient();
        $this->won = makeLead($wonClient, $this->marketing);
        makeInquiry($wonClient, attributes: ['lead' => $this->won, 'marketing_status' => LeadStatus::Won, 'status' => InquiryStatus::Negotiation]);
        $lostClient = inquiryClient();
        $this->lost = makeLead($lostClient, $this->marketing);
        makeInquiry($lostClient, attributes: ['lead' => $this->lost, 'marketing_status' => LeadStatus::Lost]);
    });

    it('offers sales no lead, as it opens inquiries only for walk-in clients, and marketing all its own leads', function (): void {
        $offered = fn (User $user) => collect($this->actingAs($user)->get(route('inquiry.inquire'))->viewData('page')['props']['leads'])->pluck('reference')->sort()->values()->all();

        expect($offered($this->sales))->toBe([])
            ->and($offered($this->marketing))->toBe(collect([$this->idle->reference, $this->won->reference, $this->lost->reference])->sort()->values()->all())
            ->and($offered(messagingStaff(Role::Marketing, 'Other')))->toBe([]);

        // A client sends their own; they are never given leads.
        expect($this->actingAs($this->client)->get(route('quote.inquire'))->viewData('page')['props']['leads'])->toBeNull();
    });

    it('never lets sales open one under a lead, not even a returning customer\'s won lead', function (): void {
        foreach ([$this->idle, $this->won, $this->lost] as $lead) {
            $this->actingAs($this->sales)->post(route('leads.inquiries.store', $lead->reference), ['projects' => [inquiryProjectInput(['name' => 'Sales-opened'])]])->assertForbidden();
        }

        expect(Inquiry::query()->where('name', 'Sales-opened')->exists())->toBeFalse();
    });

    it('lets marketing open one from any of its own leads, waiting for its decision, and not from someone else\'s', function (): void {
        $this->actingAs($this->marketing)->post(route('leads.inquiries.store', makeLead(inquiryClient())->reference), ['projects' => [inquiryProjectInput()]])->assertForbidden();

        $this->actingAs($this->marketing)->post(route('leads.inquiries.store', $this->idle->reference), ['projects' => [inquiryProjectInput(['name' => 'From idle'])]])->assertRedirect();
        $this->actingAs($this->marketing)->post(route('leads.inquiries.store', $this->won->reference), ['projects' => [inquiryProjectInput(['name' => 'From won'])]])->assertRedirect();
        $this->actingAs($this->marketing)->post(route('leads.inquiries.store', $this->lost->reference), ['projects' => [inquiryProjectInput(['name' => 'From lost'])]])->assertRedirect();

        $opened = Inquiry::query()->whereIn('name', ['From idle', 'From won', 'From lost'])->get()->keyBy('name');

        expect($opened)->toHaveCount(3)
            ->and($opened['From idle']->client_id)->toBe($this->client->id)
            ->and($opened->every(fn (Inquiry $inquiry): bool => $inquiry->marketing_status === null && $inquiry->sales_status === null))->toBeTrue();
    });

    it('takes no attachments from staff: they are the client\'s to upload', function (): void {
        $this->actingAs($this->marketing)
            ->post(route('leads.inquiries.store', $this->idle->reference), ['projects' => [inquiryProjectInput(['attachments' => [UploadedFile::fake()->create('plan.pdf', 5, 'application/pdf')]])]])
            ->assertSessionHasErrors('projects.0.attachments');

        expect(Inquiry::query()->where('lead_id', $this->idle->id)->exists())->toBeFalse();
    });
});

it('lets the lead\'s marketing user correct the client\'s phone, and never their name or email', function (): void {
    $lead = makeLead($this->client, $this->marketing);
    ['name' => $name, 'email' => $email] = $this->client->only('name', 'email');

    $this->actingAs($this->marketing)->post(route('leads.update', $lead->reference), ['name' => 'Maria Santos', 'phone' => '+63 917 000 0002', 'email' => 'taken@example.com'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->client->refresh();

    expect($this->client->profile->phone)->toBe('+63 917 000 0002')
        ->and($this->client->name)->toBe($name)
        ->and($this->client->email)->toBe($email);

    $this->actingAs($this->marketing)->post(route('leads.update', $lead->reference), ['phone' => '']);

    expect($this->client->profile->refresh()->phone)->toBe('');
});

it('opens no profile for a blank phone, and lets no one else edit the lead', function (): void {
    $lead = makeLead($this->client, $this->marketing);

    $this->actingAs($this->marketing)->post(route('leads.update', $lead->reference), ['phone' => '']);

    expect($this->client->refresh()->profile)->toBeNull();

    $this->actingAs(messagingStaff(Role::Marketing, 'Other'))->postJson(route('leads.update', $lead->reference), ['phone' => '+63 917 000 0002'])->assertForbidden();
    $this->actingAs($this->marketing)->postJson(route('leads.update', $lead->reference), ['phone' => str_repeat('9', 31)])->assertJsonValidationErrors('phone');

    expect($this->client->refresh()->profile)->toBeNull();
});

it('finds a lead by its client\'s name or email, or by one of its inquiry numbers, within the status filter', function (): void {
    $idle = makeLead($this->client, $this->marketing);
    makeInquiry($this->client, attributes: ['reference' => 'INQ-S77', 'lead' => $idle]);
    $other = inquiryClient();
    $won = makeLead($other, $this->marketing);
    makeInquiry($other, attributes: ['lead' => $won, 'marketing_status' => LeadStatus::Won, 'status' => InquiryStatus::Negotiation]);
    $found = fn (array $query) => collect($this->actingAs($this->marketing)->get(route('leads.index', $query))->viewData('page')['props']['leads']['data'])->pluck('reference')->all();

    expect($found(['search' => $this->client->name]))->toBe([$idle->reference])
        ->and($found(['search' => strtoupper($other->email)]))->toBe([$won->reference])
        ->and($found(['search' => 'inq-s77']))->toBe([$idle->reference])
        ->and($found(['search' => 'INQ-S77', 'status' => 'won']))->toBe([])
        ->and($found(['search' => $other->name, 'status' => 'won']))->toBe([$won->reference])
        // The filter keeps leads with an inquiry at that decision.
        ->and($found(['status' => 'idle']))->toBe([$idle->reference])
        // An unknown status is no filter.
        ->and($found(['search' => 'INQ-S77', 'status' => 'nope']))->toBe([$idle->reference]);
});
