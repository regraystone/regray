<?php

use App\Enums\DocumentType;
use App\Enums\InquiryStatus;
use App\Enums\InquiryStatusTrack;
use App\Enums\MarketingStatus;
use App\Enums\ProcessStatus;
use App\Enums\SalesStatus;
use App\Models\EdgeProfile;
use App\Models\Finish;
use App\Models\InquiryFile;
use App\Models\InquiryStatusHistory;
use App\Models\InquiryStone;
use App\Models\ProjectCategory;
use App\Models\Service;
use App\Models\Stone;
use App\Repositories\Eloquent\EloquentInquiryRepository;
use Illuminate\Support\Facades\Date;

beforeEach(function (): void {
    seedInquiryCatalog();
    $this->repository = new EloquentInquiryRepository;
    $this->client = inquiryClient();
    $this->inquiry = $this->repository->create([
        'reference' => 'INQ-9001',
        'client_id' => $this->client->id,
        'name' => 'Counters',
        'location' => 'Makati',
        'landmark' => 'Church',
        'area_sqm' => 12.5,
        'notes' => 'Notes',
        'marketing_status' => MarketingStatus::Pending,
        'marketing_status_changed_at' => Date::now(),
    ]);
});

it('increments the reference sequence', function (): void {
    $first = $this->repository->nextReferenceNumber();

    expect($this->repository->nextReferenceNumber())->toBe($first + 1);
});

it('creates the inquiry row with its project', function (): void {
    expect($this->inquiry->fresh())
        ->reference->toBe('INQ-9001')
        ->client_id->toBe($this->client->id)
        ->name->toBe('Counters')
        ->location->toBe('Makati')
        ->landmark->toBe('Church')
        ->status->toBe(InquiryStatus::Pending);
});

it('creates a status history row on a track', function (): void {
    $this->repository->createStatusHistory($this->inquiry, InquiryStatusTrack::Sales, SalesStatus::Negotiation, SalesStatus::SiteVisited, $this->client->id, Date::now());

    expect(InquiryStatusHistory::query()->sole())
        ->inquiry_id->toBe($this->inquiry->id)
        ->track->toBe(InquiryStatusTrack::Sales)
        ->from_status->toBe('negotiation')
        ->to_status->toBe('site_visited')
        ->changed_by->toBe($this->client->id);
});

it('updates one role status and when it changed', function (): void {
    $at = Date::now()->addHour();

    $this->repository->updateStatus($this->inquiry, InquiryStatusTrack::Process, ProcessStatus::Measure, $at);

    expect($this->inquiry->fresh())
        ->process_status->toBe(ProcessStatus::Measure)
        ->sales_status->toBeNull()
        ->and($this->inquiry->fresh()->process_status_changed_at->toDateTimeString())->toBe($at->toDateTimeString());
});

it('attaches services', function (): void {
    $ids = Service::query()->limit(2)->pluck('id')->all();

    $this->repository->attachServices($this->inquiry, $ids);

    expect($this->inquiry->services()->pluck('services.id')->all())->toEqualCanonicalizing($ids);
});

it('attaches applications in the order given', function (): void {
    $ids = ProjectCategory::query()->orderBy('id')->limit(3)->pluck('id')->reverse()->values()->all();

    $this->repository->attachApplications($this->inquiry, $ids);

    expect($this->inquiry->categories()->pluck('project_categories.id')->all())->toBe($ids);
});

it('adds stones in the order given', function (): void {
    $stones = Stone::query()->limit(2)->pluck('id');
    $edge = EdgeProfile::query()->value('id');
    $finish = Finish::query()->value('id');

    $this->repository->addStones($this->inquiry, [
        ['stone_id' => $stones[1], 'edge_profile_id' => $edge, 'finish_id' => $finish, 'quantity' => 3],
        ['stone_id' => $stones[0], 'edge_profile_id' => $edge, 'finish_id' => $finish, 'quantity' => 5],
    ]);

    $rows = InquiryStone::query()->orderBy('sort_order')->get();

    expect($rows->pluck('stone_id')->all())->toBe([$stones[1], $stones[0]])
        ->and($rows->pluck('quantity')->all())->toBe([3, 5])
        ->and($rows->pluck('sort_order')->all())->toBe([0, 1]);
});

it('adds a file row', function (): void {
    $this->repository->addFile($this->inquiry, [
        'document_type' => DocumentType::ClientAttachment,
        'disk' => 'local',
        'path' => 'inquiries/INQ-9001/a.pdf',
        'original_name' => 'a.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 1234,
        'sha256' => str_repeat('a', 64),
        'uploaded_by' => $this->client->id,
    ]);

    expect(InquiryFile::query()->sole())
        ->inquiry_id->toBe($this->inquiry->id)
        ->document_type->toBe(DocumentType::ClientAttachment)
        ->path->toBe('inquiries/INQ-9001/a.pdf')
        ->size_bytes->toBe(1234)
        ->uploaded_by->toBe($this->client->id);
});

it('deletes only the client attachments it is not told to keep', function (): void {
    addInquiryFile($this->inquiry, DocumentType::ClientAttachment);
    addInquiryFile($this->inquiry, DocumentType::Receipt);

    $removed = $this->repository->deleteClientAttachmentsExcept($this->inquiry, []);

    expect($removed)->toHaveCount(1)
        ->and($this->inquiry->files()->pluck('document_type')->all())->toBe([DocumentType::Receipt]);
});
