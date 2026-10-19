<?php

use App\Data\WrittenFiles;
use App\Enums\InquiryStatus;
use App\Models\InquiryStatusHistory;
use App\Models\Service;
use App\Services\InquiryService;
use Illuminate\Http\UploadedFile;
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
        'deadline_date' => null,
        'deadline_time' => null,
        'notes' => null,
        'files' => $files,
    ]]];
}

it('generates a reference from the sequence', function (): void {
    $service = app(InquiryService::class);

    $first = $service->nextReference();
    $second = $service->nextReference();

    expect($first)->toMatch('/^INQ-\d{4,}$/')
        ->and((int) substr($second, 4))->toBe((int) substr($first, 4) + 1);
});

it('creates a pending inquiry with its status history', function (): void {
    $client = inquiryClient();

    $inquiry = app(InquiryService::class)->create($client, serviceInput(), new WrittenFiles);

    expect($inquiry->status)->toBe(InquiryStatus::Pending)
        ->and($inquiry->status_changed_at)->not->toBeNull()
        ->and($inquiry->reference)->toStartWith('INQ-');

    $history = InquiryStatusHistory::query()->sole();

    expect($history->inquiry_id)->toBe($inquiry->id)
        ->and($history->from_status)->toBeNull()
        ->and($history->to_status)->toBe(InquiryStatus::Pending)
        ->and($history->changed_by)->toBe($client->id);
});

it('stores files under the reference and project on the private disk and records them', function (): void {
    $written = new WrittenFiles;

    $inquiry = app(InquiryService::class)->create(
        inquiryClient(),
        serviceInput([UploadedFile::fake()->create('plan.pdf', 5, 'application/pdf')]),
        $written,
    );

    $project = $inquiry->projects()->sole();

    expect($written->paths())->toHaveCount(1)
        ->and($written->paths()[0])->toStartWith("inquiries/{$inquiry->reference}/{$project->id}/")
        ->and($written->paths()[0])->toEndWith('.pdf');
    Storage::disk(config('inquiry.disk'))->assertExists($written->paths()[0]);
});
