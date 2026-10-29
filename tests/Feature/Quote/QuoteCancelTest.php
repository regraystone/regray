<?php

use App\Enums\InquiryStatus;
use App\Enums\InquiryStatusTrack;
use App\Enums\LeadStatus;
use App\Enums\SalesStatus;
use App\Models\Inquiry;
use App\Models\InquiryStatusHistory;
use App\Policies\InquiryActions;
use Illuminate\Support\Facades\DB;
use Inertia\Support\SessionKey;

beforeEach(function (): void {
    seedInquiryCatalog();
    $this->client = inquiryClient();
});

it('cancels a started sales stage as rejected, the sales history says so, and the won lead stays won', function (InquiryStatus $from): void {
    $lead = makeLead($this->client, LeadStatus::Won);
    $inquiry = makeInquiry($this->client, attributes: ['reference' => 'INQ-C1', 'status' => $from, 'lead' => $lead]);

    $this->actingAs($this->client)
        ->patch(route('quote.cancel', 'INQ-C1'))
        ->assertRedirect()
        ->assertSessionHas(SessionKey::FLASH_DATA.'.toast.type', 'success');

    $inquiry->refresh();
    $history = InquiryStatusHistory::query()->sole();

    expect($inquiry->status)->toBe(InquiryStatus::Cancelled)
        ->and($inquiry->sales_status)->toBe(SalesStatus::Rejected)
        ->and($history->inquiry_id)->toBe($inquiry->id)
        ->and($history->track)->toBe(InquiryStatusTrack::Sales)
        ->and($history->from_status)->toBe(roleStatusesShowing($from)['sales_status']->value)
        ->and($history->to_status)->toBe(SalesStatus::Rejected->value)
        ->and($history->changed_by)->toBe($this->client->id)
        ->and($lead->refresh()->status)->toBe(LeadStatus::Won);
})->with([
    'negotiation' => [InquiryStatus::Negotiation],
    'site visited' => [InquiryStatus::SiteVisited],
    'sales on hold' => [InquiryStatus::OnHold],
    // A quotation sent but not approved: cancelling declines it.
    'quoted' => [InquiryStatus::Quoted],
    'revision' => [InquiryStatus::Revision],
]);

it('refuses the client once they have approved the quotation: only sales can cancel then, by rejecting', function (InquiryStatus $status): void {
    $inquiry = makeInquiry($this->client, attributes: ['reference' => 'INQ-C8', 'status' => $status]);

    $this->actingAs($this->client)
        ->patchJson(route('quote.cancel', 'INQ-C8'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status' => InquiryActions::CANCEL_LOCKED_WHEN_QUOTED]);

    expect($inquiry->refresh()->status)->toBe($status)
        ->and($inquiry->sales_status)->toBe(SalesStatus::Won)
        ->and(InquiryStatusHistory::query()->count())->toBe(0);
})->with([
    'won' => [InquiryStatus::Won],
    'production' => [InquiryStatus::Production],
    'delayed' => [InquiryStatus::Delayed],
]);

it('cancels an inquiry whose sales stage has not started by marking its lead lost, leaving sales unset', function (): void {
    $lead = makeLead($this->client);
    $inquiry = makeInquiry($this->client, attributes: ['reference' => 'INQ-C7', 'lead' => $lead]);

    $this->actingAs($this->client)->patch(route('quote.cancel', 'INQ-C7'))->assertRedirect();

    $inquiry->refresh();
    $leadHistory = $lead->history()->get()->last();

    expect($inquiry->status)->toBe(InquiryStatus::Cancelled)
        ->and($lead->refresh()->status)->toBe(LeadStatus::Lost)
        ->and($inquiry->sales_status)->toBeNull()
        ->and(InquiryStatusHistory::query()->count())->toBe(0)
        ->and($leadHistory->from_status)->toBe(LeadStatus::Idle)
        ->and($leadHistory->to_status)->toBe(LeadStatus::Lost)
        ->and($leadHistory->changed_by)->toBe($this->client->id);
});

it('cancels an inquiry with no lead yet by rejecting sales from nothing', function (): void {
    $inquiry = makeInquiry($this->client, attributes: ['reference' => 'INQ-C6']);

    $this->actingAs($this->client)->patch(route('quote.cancel', 'INQ-C6'))->assertRedirect();

    $history = InquiryStatusHistory::query()->sole();

    expect($inquiry->refresh()->status)->toBe(InquiryStatus::Cancelled)
        ->and($inquiry->sales_status)->toBe(SalesStatus::Rejected)
        ->and($history->track)->toBe(InquiryStatusTrack::Sales)
        ->and($history->from_status)->toBeNull()
        ->and($history->to_status)->toBe(SalesStatus::Rejected->value);
});

it('refuses an inquiry that is no longer open', function (InquiryStatus $status): void {
    $inquiry = makeInquiry($this->client, attributes: ['reference' => 'INQ-C2', 'status' => $status]);

    $this->actingAs($this->client)
        ->patchJson(route('quote.cancel', 'INQ-C2'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');

    expect($inquiry->refresh()->status)->toBe($status)
        ->and(InquiryStatusHistory::query()->count())->toBe(0);
})->with([
    'closed' => [InquiryStatus::Closed],
    'cancelled' => [InquiryStatus::Cancelled],
]);

it('gives another client a 404 and changes nothing', function (): void {
    $inquiry = makeInquiry($this->client, attributes: ['reference' => 'INQ-C3']);

    $this->actingAs(inquiryClient())->patch(route('quote.cancel', 'INQ-C3'))->assertNotFound();

    expect($inquiry->refresh()->status)->toBe(InquiryStatus::Pending)
        ->and(InquiryStatusHistory::query()->count())->toBe(0);
});

it('writes one history row when cancelled twice', function (): void {
    makeInquiry($this->client, attributes: ['reference' => 'INQ-C4']);

    $this->actingAs($this->client)->patchJson(route('quote.cancel', 'INQ-C4'))->assertRedirect();
    $this->actingAs($this->client)->patchJson(route('quote.cancel', 'INQ-C4'))->assertUnprocessable();

    expect(InquiryStatusHistory::query()->count())->toBe(1)
        ->and(Inquiry::query()->where('reference', 'INQ-C4')->sole()->status)->toBe(InquiryStatus::Cancelled);
});

it('takes the row lock before reading the status', function (): void {
    makeInquiry($this->client, attributes: ['reference' => 'INQ-C5']);
    $queries = [];

    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    $this->actingAs($this->client)->patch(route('quote.cancel', 'INQ-C5'));

    expect(collect($queries)->contains(fn (string $sql): bool => str_contains($sql, 'for update')))->toBeTrue();
});
