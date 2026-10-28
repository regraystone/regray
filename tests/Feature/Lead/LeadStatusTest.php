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
 * Leads: marketing's record of a client, created when a marketing user is
 * given the client's Regray Team thread. Won starts sales on its inquiries;
 * lost shows them cancelled. Won and lost are final.
 */

beforeEach(function (): void {
    seedInquiryCatalog();
    $this->marketing = messagingStaff(Role::Marketing, 'Marketing');
    $this->client = inquiryClient();
    $this->thread = supportThreadFor($this->client);
});

it('creates an idle lead for the client when marketing claims their thread, and links their inquiries to it', function (): void {
    $first = makeInquiry($this->client, attributes: ['reference' => 'INQ-L1']);
    $second = makeInquiry($this->client, attributes: ['reference' => 'INQ-L2']);

    $this->actingAs($this->marketing)->postJson(route('message.claim', $this->thread))->assertOk();

    $lead = Lead::query()->sole();

    expect($lead->client_id)->toBe($this->client->id)
        ->and($lead->marketing_user_id)->toBe($this->marketing->id)
        ->and($lead->status)->toBe(LeadStatus::Idle)
        ->and($lead->reference)->toMatch('/^LEAD-\d{4,}$/')
        ->and($lead->history()->sole()->only(['from_status', 'to_status']))->toBe(['from_status' => null, 'to_status' => LeadStatus::Idle])
        ->and($first->refresh()->lead_id)->toBe($lead->id)
        ->and($second->refresh()->lead_id)->toBe($lead->id)
        ->and($first->status)->toBe(InquiryStatus::Pending);
});

it('moves the idle lead to whoever the thread is reassigned to, without a second lead', function (): void {
    $this->actingAs($this->marketing)->postJson(route('message.claim', $this->thread))->assertOk();
    $other = messagingStaff(Role::Marketing, 'Other');

    $this->actingAs(messagingStaff(Role::Admin))->postJson(route('message.reassign', $this->thread), ['userId' => $other->id])->assertOk();

    expect(Lead::query()->sole()->marketing_user_id)->toBe($other->id);
});

it('keeps a won lead with whoever won it when the thread is claimed again', function (): void {
    $lead = makeLead($this->client, LeadStatus::Won, $this->marketing);
    $other = messagingStaff(Role::Marketing, 'Other');

    $this->actingAs($other)->postJson(route('message.claim', $this->thread))->assertOk();

    expect(Lead::query()->sole()->is($lead))->toBeTrue()
        ->and($lead->refresh()->marketing_user_id)->toBe($this->marketing->id);
});

it('starts sales at negotiation on every linked inquiry whose sales has not started when the lead is won, with a history row and a card', function (): void {
    $lead = makeLead($this->client, marketing: $this->marketing);
    $waiting = makeInquiry($this->client, attributes: ['reference' => 'INQ-W1', 'lead' => $lead]);
    $rejected = makeInquiry($this->client, attributes: ['reference' => 'INQ-W2', 'lead' => $lead, 'status' => InquiryStatus::Cancelled]);

    $this->actingAs($this->marketing)->post(route('leads.status', $lead->reference), ['to' => 'won', 'note' => 'Decided on the call.'])->assertRedirect();

    $history = InquiryStatusHistory::query()->sole();

    expect($lead->refresh()->status)->toBe(LeadStatus::Won)
        ->and($waiting->refresh()->sales_status)->toBe(SalesStatus::Negotiation)
        ->and($waiting->status)->toBe(InquiryStatus::Negotiation)
        ->and($rejected->refresh()->sales_status)->toBe(SalesStatus::Rejected)
        ->and([$history->inquiry_id, $history->track, $history->from_status, $history->to_status, $history->changed_by])
        ->toBe([$waiting->id, InquiryStatusTrack::Sales, null, 'negotiation', $this->marketing->id])
        ->and(Message::query()->where('conversation_id', $this->thread->id)->where('inquiry_id', $waiting->id)->pluck('announced_status')->all())->toBe(['negotiation'])
        // The cancelled one showed cancelled before and still does: no card.
        ->and(Message::query()->where('inquiry_id', $rejected->id)->exists())->toBeFalse()
        ->and($lead->history()->get()->last()->to_status)->toBe(LeadStatus::Won)
        ->and($lead->history()->get()->last()->note)->toBe('Decided on the call.');
});

it('shows every linked inquiry cancelled and cancels their open visits when the lead is lost', function (): void {
    $lead = makeLead($this->client, marketing: $this->marketing);
    $inquiry = makeInquiry($this->client, attributes: ['reference' => 'INQ-X1', 'lead' => $lead]);
    $visit = app(ScheduleService::class)->createForInquiry($inquiry, $this->client, now()->addWeek()->toDateString(), null);

    $this->actingAs($this->marketing)->post(route('leads.status', $lead->reference), ['to' => 'lost', 'note' => 'Decided on the call.'])->assertRedirect();

    expect($inquiry->refresh()->status)->toBe(InquiryStatus::Cancelled)
        ->and($inquiry->sales_status)->toBeNull()
        ->and($visit->refresh()->status)->toBe(ScheduleStatus::Cancelled)
        ->and(InquiryStatusHistory::query()->count())->toBe(0)
        ->and(Message::query()->where('inquiry_id', $inquiry->id)->pluck('announced_status')->all())->toBe(['cancelled']);
});

it('treats won and lost as final', function (LeadStatus $from, string $to): void {
    $lead = makeLead($this->client, $from, $this->marketing);

    $this->actingAs($this->marketing)
        ->postJson(route('leads.status', $lead->reference), ['to' => $to, 'note' => 'Decided on the call.'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status' => "This lead is already {$from->value}."]);

    expect($lead->refresh()->status)->toBe($from);
})->with([
    'won, then lost' => [LeadStatus::Won, 'lost'],
    'lost, then won' => [LeadStatus::Lost, 'won'],
]);

it('lets only the lead\'s marketing user mark it, and only with a note', function (): void {
    $lead = makeLead($this->client, marketing: $this->marketing);

    $this->actingAs(messagingStaff(Role::Marketing, 'Other'))->postJson(route('leads.status', $lead->reference), ['to' => 'won', 'note' => 'Decided on the call.'])->assertForbidden();
    $this->actingAs(messagingStaff(Role::Sales))->postJson(route('leads.status', $lead->reference), ['to' => 'won', 'note' => 'Decided on the call.'])->assertForbidden();
    $this->actingAs($this->marketing)->postJson(route('leads.status', $lead->reference), ['to' => 'won', 'note' => ''])->assertJsonValidationErrors('note');

    expect($lead->refresh()->status)->toBe(LeadStatus::Idle);
});

it('opens a new idle lead for the thread\'s holder when the client sends an inquiry after their lead was lost', function (): void {
    $this->actingAs($this->marketing)->postJson(route('message.claim', $this->thread))->assertOk();
    $lost = Lead::query()->sole();
    $lost->forceFill(['status' => LeadStatus::Lost])->save();

    $this->actingAs($this->client)->post(route('quote.inquire.store'), ['projects' => [inquiryProjectInput()]])->assertRedirect();

    $inquiry = Inquiry::query()->sole();
    $fresh = Lead::query()->whereKeyNot($lost->id)->sole();

    expect($fresh->status)->toBe(LeadStatus::Idle)
        ->and($fresh->marketing_user_id)->toBe($this->marketing->id)
        ->and($fresh->client_id)->toBe($this->client->id)
        ->and($inquiry->lead_id)->toBe($fresh->id)
        ->and($inquiry->status)->toBe(InquiryStatus::Pending);
});

it('leaves a new inquiry unlinked while nobody holds the client\'s thread, and the claim links it', function (): void {
    $this->actingAs($this->client)->post(route('quote.inquire.store'), ['projects' => [inquiryProjectInput()]])->assertRedirect();

    expect(Inquiry::query()->sole()->lead_id)->toBeNull()
        ->and(Lead::query()->exists())->toBeFalse();

    $this->actingAs($this->marketing)->postJson(route('message.claim', $this->thread))->assertOk();

    expect(Inquiry::query()->sole()->lead_id)->toBe(Lead::query()->sole()->id);
});
