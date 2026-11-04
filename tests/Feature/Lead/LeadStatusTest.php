<?php

use App\Enums\InquiryStatus;
use App\Enums\InquiryStatusTrack;
use App\Enums\LeadStatus;
use App\Enums\Role;
use App\Enums\SalesStatus;
use App\Enums\ScheduleStatus;
use App\Models\Inquiry;
use App\Models\InquiryStatusHistory;
use App\Models\Lead;
use App\Models\Message;
use App\Services\ScheduleService;

/*
 * Leads: marketing's record of a client, one per client, created when a
 * marketing user is given the client's Regray Team thread. Marketing decides
 * each of its inquiries: won starts that inquiry's sales, lost shows it
 * cancelled, and both are final. The lead shows the tally.
 */

beforeEach(function (): void {
    seedInquiryCatalog();
    $this->marketing = messagingStaff(Role::Marketing, 'Marketing');
    $this->client = inquiryClient();
    $this->thread = supportThreadFor($this->client);
});

function decideInquiry(object $test, Lead $lead, Inquiry $inquiry, string $to, string $note = 'Decided on the call.')
{
    return $test->actingAs($test->marketing)->postJson(route('leads.inquiries.status', [$lead->reference, $inquiry->reference]), ['to' => $to, 'note' => $note]);
}

it('creates the client\'s lead when marketing claims their thread, and links their inquiries to it, waiting', function (): void {
    $first = makeInquiry($this->client, attributes: ['reference' => 'INQ-L1']);
    $second = makeInquiry($this->client, attributes: ['reference' => 'INQ-L2']);

    $this->actingAs($this->marketing)->postJson(route('inbox.claim', $this->thread))->assertOk();

    $lead = Lead::query()->with('inquiries')->sole();

    expect($lead->client_id)->toBe($this->client->id)
        ->and($lead->marketing_user_id)->toBe($this->marketing->id)
        ->and($lead->reference)->toMatch('/^LEAD-\d{4,}$/')
        ->and($lead->history()->sole()->only(['from_status', 'to_status', 'inquiry_id']))->toBe(['from_status' => null, 'to_status' => LeadStatus::Idle, 'inquiry_id' => null])
        ->and($first->refresh()->lead_id)->toBe($lead->id)
        ->and($second->refresh()->lead_id)->toBe($lead->id)
        ->and($first->marketing_status)->toBeNull()
        ->and($first->status)->toBe(InquiryStatus::Pending)
        ->and($lead->tally())->toBe(['won' => 0, 'lost' => 0, 'idle' => 2]);
});

it('moves the lead with the thread on every reassignment, whatever its inquiries\' decisions', function (): void {
    $this->actingAs($this->marketing)->postJson(route('inbox.claim', $this->thread))->assertOk();
    $lead = Lead::query()->sole();
    makeInquiry($this->client, attributes: ['lead' => $lead, 'marketing_status' => LeadStatus::Won, 'status' => InquiryStatus::Negotiation]);
    $other = messagingStaff(Role::Marketing, 'Other');

    $this->actingAs(messagingStaff(Role::Admin))->postJson(route('inbox.reassign', $this->thread), ['userId' => $other->id])->assertOk();

    expect(Lead::query()->sole()->marketing_user_id)->toBe($other->id);
});

it('wins one inquiry: its sales starts at negotiation with a history row and a card, and the others keep waiting', function (): void {
    $lead = makeLead($this->client, $this->marketing);
    $won = makeInquiry($this->client, attributes: ['reference' => 'INQ-W1', 'lead' => $lead]);
    $waiting = makeInquiry($this->client, attributes: ['reference' => 'INQ-W2', 'lead' => $lead]);

    decideInquiry($this, $lead, $won, 'won')->assertRedirect();

    $history = InquiryStatusHistory::query()->sole();
    $decision = $lead->history()->get()->last();

    expect($won->refresh()->marketing_status)->toBe(LeadStatus::Won)
        ->and($won->sales_status)->toBe(SalesStatus::Negotiation)
        ->and($won->status)->toBe(InquiryStatus::Negotiation)
        ->and($waiting->refresh()->marketing_status)->toBeNull()
        ->and($waiting->status)->toBe(InquiryStatus::Pending)
        ->and([$history->inquiry_id, $history->track, $history->from_status, $history->to_status, $history->changed_by])
        ->toBe([$won->id, InquiryStatusTrack::Sales, null, 'negotiation', $this->marketing->id])
        ->and(Message::query()->where('conversation_id', $this->thread->id)->where('inquiry_id', $won->id)->pluck('announced_status')->all())->toBe(['negotiation'])
        ->and(Message::query()->where('inquiry_id', $waiting->id)->exists())->toBeFalse()
        ->and([$decision->inquiry_id, $decision->to_status, $decision->note, $decision->changed_by])->toBe([$won->id, LeadStatus::Won, 'Decided on the call.', $this->marketing->id])
        ->and($lead->load('inquiries')->tally())->toBe(['won' => 1, 'lost' => 0, 'idle' => 1]);
});

it('loses one inquiry: it shows cancelled and its open visit is cancelled, and the others keep waiting', function (): void {
    $lead = makeLead($this->client, $this->marketing);
    $lost = makeInquiry($this->client, attributes: ['reference' => 'INQ-X1', 'lead' => $lead]);
    $waiting = makeInquiry($this->client, attributes: ['reference' => 'INQ-X2', 'lead' => $lead]);
    $visit = app(ScheduleService::class)->createForInquiry($lost, $this->client, now()->addWeek()->toDateString(), null);

    decideInquiry($this, $lead, $lost, 'lost')->assertRedirect();

    expect($lost->refresh()->status)->toBe(InquiryStatus::Cancelled)
        ->and($lost->marketing_status)->toBe(LeadStatus::Lost)
        ->and($lost->sales_status)->toBeNull()
        ->and($visit->refresh()->status)->toBe(ScheduleStatus::Cancelled)
        ->and(InquiryStatusHistory::query()->count())->toBe(0)
        ->and(Message::query()->where('inquiry_id', $lost->id)->pluck('announced_status')->all())->toBe(['cancelled'])
        ->and($waiting->refresh()->status)->toBe(InquiryStatus::Pending)
        ->and($lead->load('inquiries')->tally())->toBe(['won' => 0, 'lost' => 1, 'idle' => 1]);
});

it('treats an inquiry\'s won and lost as final', function (LeadStatus $from, string $to): void {
    $lead = makeLead($this->client, $this->marketing);
    $inquiry = makeInquiry($this->client, attributes: ['lead' => $lead, 'marketing_status' => $from]);

    decideInquiry($this, $lead, $inquiry, $to)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['to' => "{$inquiry->reference} is already {$from->value}."]);

    expect($inquiry->refresh()->marketing_status)->toBe($from);
})->with([
    'won, then lost' => [LeadStatus::Won, 'lost'],
    'lost, then won' => [LeadStatus::Lost, 'won'],
]);

it('lets only the lead\'s marketing user decide its inquiries, only with a note, and only its own inquiries', function (): void {
    $lead = makeLead($this->client, $this->marketing);
    $inquiry = makeInquiry($this->client, attributes: ['lead' => $lead]);
    $elsewhere = makeInquiry(inquiryClient(), attributes: ['lead' => makeLead(inquiryClient(), $this->marketing)]);
    $route = fn (Inquiry $on) => route('leads.inquiries.status', [$lead->reference, $on->reference]);

    $this->actingAs(messagingStaff(Role::Marketing, 'Other'))->postJson($route($inquiry), ['to' => 'won', 'note' => 'Decided.'])->assertForbidden();
    $this->actingAs(messagingStaff(Role::Sales))->postJson($route($inquiry), ['to' => 'won', 'note' => 'Decided.'])->assertForbidden();
    $this->actingAs($this->marketing)->postJson($route($inquiry), ['to' => 'won', 'note' => ''])->assertJsonValidationErrors('note');
    $this->actingAs($this->marketing)->postJson($route($elsewhere), ['to' => 'won', 'note' => 'Decided.'])->assertNotFound();

    expect($inquiry->refresh()->marketing_status)->toBeNull()
        ->and($elsewhere->refresh()->marketing_status)->toBeNull();
});

it('keeps the client on one lead: an inquiry sent after another was lost or won waits on it for marketing', function (): void {
    $this->actingAs($this->marketing)->postJson(route('inbox.claim', $this->thread))->assertOk();
    $lead = Lead::query()->sole();
    makeInquiry($this->client, attributes: ['lead' => $lead, 'marketing_status' => LeadStatus::Lost]);
    makeInquiry($this->client, attributes: ['lead' => $lead, 'marketing_status' => LeadStatus::Won, 'status' => InquiryStatus::Negotiation]);

    $this->actingAs($this->client)->post(route('quote.inquire.store'), ['projects' => [inquiryProjectInput(['name' => 'Patio'])]])->assertRedirect();

    $inquiry = Inquiry::query()->where('name', 'Patio')->sole();

    expect(Lead::query()->sole()->is($lead))->toBeTrue()
        ->and($inquiry->lead_id)->toBe($lead->id)
        ->and($inquiry->marketing_status)->toBeNull()
        ->and($inquiry->sales_status)->toBeNull()
        ->and($inquiry->status)->toBe(InquiryStatus::Pending);
});

it('leaves a new inquiry unlinked while nobody holds the client\'s thread, and the claim links it', function (): void {
    $this->actingAs($this->client)->post(route('quote.inquire.store'), ['projects' => [inquiryProjectInput()]])->assertRedirect();

    expect(Inquiry::query()->sole()->lead_id)->toBeNull()
        ->and(Lead::query()->exists())->toBeFalse();

    $this->actingAs($this->marketing)->postJson(route('inbox.claim', $this->thread))->assertOk();

    expect(Inquiry::query()->sole()->lead_id)->toBe(Lead::query()->sole()->id);
});
