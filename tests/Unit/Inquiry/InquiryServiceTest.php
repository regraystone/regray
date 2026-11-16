<?php

use App\Data\WrittenFiles;
use App\Enums\DeliveryMethod;
use App\Enums\InquiryStatus;
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
        'service_id' => Service::query()->value('id'),
        'stones' => [],
        'delivery_method' => DeliveryMethod::Delivery,
        'location' => 'Makati',
        'answers' => [],
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
