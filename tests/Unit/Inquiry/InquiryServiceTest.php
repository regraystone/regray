<?php

use App\Data\WrittenFiles;
use App\Enums\InquiryStatus;
use App\Enums\InquiryStatusTrack;
use App\Enums\LeadStatus;
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
        'service_ids' => [Service::query()->value('id')],
        'application_ids' => [],
        'stones' => [],
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

it('starts sales at negotiation at once on a lead that is already won', function (): void {
    $client = inquiryClient();
    $lead = makeLead($client, LeadStatus::Won);

    [$inquiry] = app(InquiryService::class)->create($client, serviceInput(), new WrittenFiles);

    $history = InquiryStatusHistory::query()->sole();

    expect($inquiry->lead_id)->toBe($lead->id)
        ->and($inquiry->fresh()->status)->toBe(InquiryStatus::Negotiation)
        ->and([$history->track, $history->from_status, $history->to_status, $history->changed_by])->toBe([InquiryStatusTrack::Sales, null, 'negotiation', $client->id]);
});
