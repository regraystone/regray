<?php

use App\Enums\InquiryStatus;
use App\Enums\LeadStatus;
use App\Enums\Role;
use App\Enums\SalesStatus;
use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Http\UploadedFile;

/*
 * The Leads page is marketing's, and staff open inquiries for a lead's client:
 * marketing from its own idle or won leads, sales from won leads only.
 */

beforeEach(function (): void {
    seedInquiryCatalog();
    $this->marketing = messagingStaff(Role::Marketing, 'Marketing');
    $this->client = inquiryClient();
});

it('opens for marketing with its own leads, newest first, each with its phone, email and actions', function (): void {
    $idle = makeLead($this->client, marketing: $this->marketing);
    makeInquiry($this->client, attributes: ['reference' => 'INQ-P1', 'lead' => $idle]);
    $this->client->profile()->create(['phone' => '+63 917 000 0001', 'address' => 'Makati']);
    $won = makeLead(inquiryClient(), LeadStatus::Won, $this->marketing);
    // Someone else's lead is not on this page.
    makeLead(inquiryClient());

    $props = $this->actingAs($this->marketing)->get(route('leads.index'))->assertOk()->viewData('page')['props'];
    $rows = collect($props['leads']['data'])->keyBy('reference');

    expect($props['leads']['meta'])->toMatchArray(['currentPage' => 1, 'perPage' => 10, 'total' => 2])
        ->and($props['filters'])->toBe(['search' => '', 'page' => 1, 'perPage' => 10, 'status' => 'all'])
        ->and($rows->keys()->sort()->values()->all())->toBe(collect([$idle->reference, $won->reference])->sort()->values()->all())
        ->and($rows[$idle->reference])->toMatchArray([
            'client' => ['id' => $this->client->id, 'name' => $this->client->name],
            'contact' => '+63 917 000 0001',
            'email' => $this->client->email,
            'actions' => ['view', 'edit', 'won', 'lost'],
            'canCreateInquiry' => true,
        ])
        ->and($rows[$idle->reference]['inquiries'][0]['reference'])->toBe('INQ-P1')
        // Won and Lost only while the lead is idle.
        ->and($rows[$won->reference]['actions'])->toBe(['view', 'edit']);

    $searched = $this->actingAs($this->marketing)->get(route('leads.index', ['search' => $this->client->name, 'perPage' => 25]))->viewData('page')['props'];

    expect(collect($searched['leads']['data'])->pluck('reference')->all())->toBe([$idle->reference])
        ->and($searched['filters']['perPage'])->toBe(25);
});

it('lets the admin in, with every lead', function (): void {
    makeLead($this->client, marketing: $this->marketing);
    makeLead(inquiryClient());

    $this->actingAs(User::factory()->role(Role::Admin)->create())->get(route('leads.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('leads', 2));
});

describe('opening an inquiry for a lead', function (): void {
    beforeEach(function (): void {
        $this->sales = messagingStaff(Role::Sales, 'Sales');
        $this->idle = makeLead($this->client, marketing: $this->marketing);
        $this->won = makeLead(inquiryClient(), LeadStatus::Won, $this->marketing);
        $this->lost = makeLead(inquiryClient(), LeadStatus::Lost, $this->marketing);
    });

    it('offers sales only won leads, and marketing its own idle and won leads', function (): void {
        $offered = fn (User $user) => collect($this->actingAs($user)->get(route('inquiry.inquire'))->viewData('page')['props']['leads'])->pluck('reference')->sort()->values()->all();

        expect($offered($this->sales))->toBe([$this->won->reference])
            ->and($offered($this->marketing))->toBe(collect([$this->idle->reference, $this->won->reference])->sort()->values()->all())
            ->and($offered(messagingStaff(Role::Marketing, 'Other')))->toBe([]);

        // A client sends their own; they are never given leads.
        expect($this->actingAs($this->client)->get(route('quote.inquire'))->viewData('page')['props']['leads'])->toBeNull();
    });

    it('lets sales open one only from a won lead, starting sales at negotiation', function (): void {
        $this->actingAs($this->sales)->post(route('leads.inquiries.store', $this->idle->reference), ['projects' => [inquiryProjectInput()]])->assertForbidden();
        $this->actingAs($this->sales)->post(route('leads.inquiries.store', $this->lost->reference), ['projects' => [inquiryProjectInput()]])->assertForbidden();

        $this->actingAs($this->sales)->post(route('leads.inquiries.store', $this->won->reference), ['projects' => [inquiryProjectInput()]])->assertRedirect();

        $inquiry = Inquiry::query()->sole();

        expect($inquiry->lead_id)->toBe($this->won->id)
            ->and($inquiry->client_id)->toBe($this->won->client_id)
            ->and($inquiry->sales_status)->toBe(SalesStatus::Negotiation)
            ->and($inquiry->status)->toBe(InquiryStatus::Negotiation);
    });

    it('lets marketing open one from its idle or won lead, and not from a lost one or someone else\'s', function (): void {
        $this->actingAs($this->marketing)->post(route('leads.inquiries.store', $this->lost->reference), ['projects' => [inquiryProjectInput()]])->assertForbidden();
        $this->actingAs($this->marketing)->post(route('leads.inquiries.store', makeLead(inquiryClient())->reference), ['projects' => [inquiryProjectInput()]])->assertForbidden();

        $this->actingAs($this->marketing)->post(route('leads.inquiries.store', $this->idle->reference), ['projects' => [inquiryProjectInput()]])->assertRedirect();
        $this->actingAs($this->marketing)->post(route('leads.inquiries.store', $this->won->reference), ['projects' => [inquiryProjectInput(['name' => 'Bath'])]])->assertRedirect();

        $byLead = Inquiry::query()->get()->keyBy('lead_id');

        expect($byLead)->toHaveCount(2)
            ->and($byLead[$this->idle->id]->sales_status)->toBeNull()
            ->and($byLead[$this->idle->id]->client_id)->toBe($this->client->id)
            ->and($byLead[$this->won->id]->sales_status)->toBe(SalesStatus::Negotiation);
    });

    it('takes no attachments from staff: they are the client\'s to upload', function (): void {
        $this->actingAs($this->marketing)
            ->post(route('leads.inquiries.store', $this->idle->reference), ['projects' => [inquiryProjectInput(['attachments' => [UploadedFile::fake()->create('plan.pdf', 5, 'application/pdf')]])]])
            ->assertSessionHasErrors('projects.0.attachments');

        expect(Inquiry::query()->exists())->toBeFalse();
    });
});

it('lets the lead\'s marketing user correct the client\'s phone, and never their name or email', function (): void {
    $lead = makeLead($this->client, marketing: $this->marketing);
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
    $lead = makeLead($this->client, marketing: $this->marketing);

    $this->actingAs($this->marketing)->post(route('leads.update', $lead->reference), ['phone' => '']);

    expect($this->client->refresh()->profile)->toBeNull();

    $this->actingAs(messagingStaff(Role::Marketing, 'Other'))->postJson(route('leads.update', $lead->reference), ['phone' => '+63 917 000 0002'])->assertForbidden();
    $this->actingAs($this->marketing)->postJson(route('leads.update', $lead->reference), ['phone' => str_repeat('9', 31)])->assertJsonValidationErrors('phone');

    expect($this->client->refresh()->profile)->toBeNull();
});
