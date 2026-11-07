<?php

use App\Data\WrittenFiles;
use App\Enums\DeliveryMethod;
use App\Enums\InquiryStatus;
use App\Enums\InquiryStatusTrack;
use App\Enums\LeadStatus;
use App\Enums\Role;
use App\Models\InquiryStatusHistory;
use App\Models\Service;
use App\Services\InquiryService;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake(config('inquiry.disk'));
    seedInquiryCatalog();
});

/** @return array{projects: list<array<string, mixed>>} */
function serviceInput(array $files = []): array
{
    return ['projects' => [[
        'name' => 'Counters',
        'service_id' => Service::query()->value('id'),
        'stones' => [],
        'delivery_method' => DeliveryMethod::Delivery,
        'location' => 'Makati',
        'landmark' => null,
        'area_sqm' => null,
        'deadline_date' => now()->addWeek()->toDateString(),
        'deadline_time' => '10:00',
        'notes' => null,
        'files' => $files,
    ]]];
}

it('links a new inquiry to the client\'s idle lead, leaving sales unset', function (): void {
    $client = inquiryClient();
    $lead = makeLead($client);

    [$inquiry] = app(InquiryService::class)->create($client, serviceInput(), new WrittenFiles);

    expect($inquiry->lead_id)->toBe($lead->id)
        ->and($inquiry->sales_status)->toBeNull()
        ->and($inquiry->status)->toBe(InquiryStatus::Pending);
});

it('leaves a new inquiry waiting for marketing even when the client already has a won one', function (): void {
    $client = inquiryClient();
    $lead = makeLead($client);
    makeInquiry($client, attributes: ['lead' => $lead, 'marketing_status' => LeadStatus::Won, 'status' => InquiryStatus::Negotiation]);

    [$inquiry] = app(InquiryService::class)->create($client, serviceInput(), new WrittenFiles);

    expect($inquiry->lead_id)->toBe($lead->id)
        ->and($inquiry->fresh()->marketing_status)->toBeNull()
        ->and($inquiry->fresh()->status)->toBe(InquiryStatus::Pending)
        ->and(InquiryStatusHistory::query()->exists())->toBeFalse();
});

it('decides an inquiry sales opens on a lead won at once, which starts its sales at negotiation', function (): void {
    $client = inquiryClient();
    $lead = makeLead($client);
    $sales = inquiryClient(Role::Sales);

    [$inquiry] = app(InquiryService::class)->create($client, serviceInput(), new WrittenFiles, $lead, $sales);

    $history = InquiryStatusHistory::query()->sole();

    expect($inquiry->fresh()->marketing_status)->toBe(LeadStatus::Won)
        ->and($inquiry->fresh()->status)->toBe(InquiryStatus::Negotiation)
        ->and([$history->track, $history->from_status, $history->to_status, $history->changed_by])->toBe([InquiryStatusTrack::Sales, null, 'negotiation', $sales->id])
        ->and($lead->history()->get()->last()->only(['inquiry_id', 'to_status', 'changed_by']))->toBe(['inquiry_id' => $inquiry->id, 'to_status' => LeadStatus::Won, 'changed_by' => $sales->id]);
});
