<?php

use App\Enums\InquiryProjectStatus;
use App\Enums\InquiryStatus;
use App\Models\EdgeProfile;
use App\Models\Finish;
use App\Models\InquiryFile;
use App\Models\InquiryProjectStone;
use App\Models\InquiryStatusHistory;
use App\Models\ProjectCategory;
use App\Models\Service;
use App\Models\Stone;
use App\Repositories\Eloquent\EloquentInquiryRepository;
use App\Services\ScheduleService;
use Illuminate\Support\Facades\Date;

beforeEach(function (): void {
    seedInquiryCatalog();
    $this->repository = new EloquentInquiryRepository;
    $this->client = inquiryClient();
    $this->inquiry = $this->repository->create([
        'reference' => 'INQ-9001',
        'client_id' => $this->client->id,
        'status' => InquiryStatus::Pending,
        'status_changed_at' => Date::now(),
    ]);
    $this->schedule = app(ScheduleService::class)->createForInquiryProject($this->inquiry, $this->client, 'Counters', 'Makati', '2030-01-02', '09:30');
    $this->project = $this->repository->addProject($this->inquiry, [
        'name' => 'Counters',
        'sort_order' => 0,
        'location' => 'Makati',
        'landmark' => 'Church',
        'area_sqm' => 12.5,
        'schedule_id' => $this->schedule->id,
        'notes' => 'Notes',
        'status' => InquiryProjectStatus::Pending,
        'status_changed_at' => Date::now(),
    ]);
});

it('increments the reference sequence', function (): void {
    $first = $this->repository->nextReferenceNumber();

    expect($this->repository->nextReferenceNumber())->toBe($first + 1);
});

it('creates the inquiry row', function (): void {
    expect($this->inquiry->fresh())
        ->reference->toBe('INQ-9001')
        ->client_id->toBe($this->client->id)
        ->status->toBe(InquiryStatus::Pending);
});

it('creates a status history row', function (): void {
    $this->repository->createStatusHistory($this->inquiry, null, InquiryStatus::Pending, $this->client->id, Date::now());

    expect(InquiryStatusHistory::query()->sole())
        ->inquiry_id->toBe($this->inquiry->id)
        ->from_status->toBeNull()
        ->to_status->toBe(InquiryStatus::Pending)
        ->changed_by->toBe($this->client->id);
});

it('adds a project', function (): void {
    expect($this->project->fresh())
        ->inquiry_id->toBe($this->inquiry->id)
        ->name->toBe('Counters')
        ->location->toBe('Makati')
        ->landmark->toBe('Church')
        ->schedule_id->toBe($this->schedule->id)
        ->status->toBe(InquiryProjectStatus::Pending);
});

it('attaches services', function (): void {
    $ids = Service::query()->limit(2)->pluck('id')->all();

    $this->repository->attachServices($this->project, $ids);

    expect($this->project->services()->pluck('services.id')->all())->toEqualCanonicalizing($ids);
});

it('attaches applications in the order given', function (): void {
    $ids = ProjectCategory::query()->orderBy('id')->limit(3)->pluck('id')->reverse()->values()->all();

    $this->repository->attachApplications($this->project, $ids);

    expect($this->project->categories()->pluck('project_categories.id')->all())->toBe($ids);
});

it('adds stones in the order given', function (): void {
    $stones = Stone::query()->limit(2)->pluck('id');
    $edge = EdgeProfile::query()->value('id');
    $finish = Finish::query()->value('id');

    $this->repository->addStones($this->project, [
        ['stone_id' => $stones[1], 'edge_profile_id' => $edge, 'finish_id' => $finish, 'quantity' => 3],
        ['stone_id' => $stones[0], 'edge_profile_id' => $edge, 'finish_id' => $finish, 'quantity' => 5],
    ]);

    $rows = InquiryProjectStone::query()->orderBy('sort_order')->get();

    expect($rows->pluck('stone_id')->all())->toBe([$stones[1], $stones[0]])
        ->and($rows->pluck('quantity')->all())->toBe([3, 5])
        ->and($rows->pluck('sort_order')->all())->toBe([0, 1]);
});

it('adds a file row', function (): void {
    $this->repository->addFile($this->project, [
        'disk' => 'local',
        'path' => 'inquiries/INQ-9001/1/a.pdf',
        'original_name' => 'a.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 1234,
        'sha256' => str_repeat('a', 64),
        'uploaded_by' => $this->client->id,
    ]);

    expect(InquiryFile::query()->sole())
        ->inquiry_project_id->toBe($this->project->id)
        ->path->toBe('inquiries/INQ-9001/1/a.pdf')
        ->size_bytes->toBe(1234)
        ->uploaded_by->toBe($this->client->id);
});
