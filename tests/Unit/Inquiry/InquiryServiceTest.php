<?php

use App\Data\WrittenFiles;
use App\Enums\DocumentType;
use App\Enums\InquiryStatus;
use App\Enums\InquiryStatusTrack;
use App\Enums\MarketingStatus;
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
        'deadline_date' => now()->addWeek()->toDateString(),
        'deadline_time' => '10:00',
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

it('creates an inquiry with only marketing started, at pending, and its one history row', function (): void {
    $client = inquiryClient();

    [$inquiry] = app(InquiryService::class)->create($client, serviceInput(), new WrittenFiles);

    expect($inquiry->status)->toBe(InquiryStatus::Pending)
        ->and($inquiry->name)->toBe('Counters')
        ->and($inquiry->marketing_status)->toBe(MarketingStatus::Pending)
        ->and($inquiry->sales_status)->toBeNull()
        ->and($inquiry->payment_status)->toBeNull()
        ->and($inquiry->process_status)->toBeNull()
        ->and($inquiry->marketing_status_changed_at)->not->toBeNull()
        ->and($inquiry->fresh()->sales_status_changed_at)->toBeNull()
        ->and($inquiry->reference)->toStartWith('INQ-');

    $history = InquiryStatusHistory::query()->orderBy('id')->get();

    expect($history->map(fn (InquiryStatusHistory $row): array => [$row->track, $row->from_status, $row->to_status])->all())->toBe([
        [InquiryStatusTrack::Marketing, null, 'pending'],
    ])
        ->and($history->pluck('inquiry_id')->unique()->all())->toBe([$inquiry->id])
        ->and($history->pluck('changed_by')->unique()->all())->toBe([$client->id]);
});

it('stores the client\'s files under the reference on the private disk and records them as client attachments', function (): void {
    $written = new WrittenFiles;

    [$inquiry] = app(InquiryService::class)->create(
        inquiryClient(),
        serviceInput([UploadedFile::fake()->create('plan.pdf', 5, 'application/pdf')]),
        $written,
    );

    expect($written->paths())->toHaveCount(1)
        ->and($written->paths()[0])->toStartWith("inquiries/{$inquiry->reference}/")
        ->and($written->paths()[0])->toEndWith('.pdf')
        ->and($inquiry->files()->sole()->document_type)->toBe(DocumentType::ClientAttachment)
        ->and($inquiry->files()->sole()->revision)->toBeNull();
    Storage::disk(config('inquiry.disk'))->assertExists($written->paths()[0]);
});
