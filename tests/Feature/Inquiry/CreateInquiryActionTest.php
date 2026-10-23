<?php

use App\Actions\Inquiry\CreateInquiryAction;
use App\Models\Inquiry;
use App\Models\InquiryFile;
use App\Models\InquiryProject;
use App\Models\InquiryStatusHistory;
use App\Models\Schedule;
use App\Models\Service;
use App\Repositories\Contracts\InquiryRepositoryInterface;
use App\Repositories\Eloquent\EloquentInquiryRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake(config('inquiry.disk'));
    seedInquiryCatalog();
});

/**
 * A repository that fails on the second file row, which is after the first file
 * has been written to the disk and after every other row has been inserted.
 */
class FailsOnSecondFileRepository extends EloquentInquiryRepository
{
    private int $files = 0;

    public function addFile(InquiryProject $project, array $attributes): InquiryFile
    {
        if (++$this->files === 2) {
            throw new RuntimeException('forced failure');
        }

        return parent::addFile($project, $attributes);
    }
}

it('writes the inquiry', function (): void {
    $client = inquiryClient();
    $input = [
        'projects' => [[
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
            'files' => [UploadedFile::fake()->create('a.pdf', 5, 'application/pdf')],
        ]],
    ];

    $inquiry = app(CreateInquiryAction::class)->execute($client, $input);

    expect($inquiry->exists)->toBeTrue()
        ->and(InquiryFile::query()->count())->toBe(1);
    Storage::disk(config('inquiry.disk'))->assertExists(InquiryFile::query()->value('path'));
});

it('leaves no rows and no files when a write fails after files are on disk', function (): void {
    $this->app->bind(InquiryRepositoryInterface::class, FailsOnSecondFileRepository::class);

    $input = [
        'projects' => [[
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
            'files' => [
                UploadedFile::fake()->create('a.pdf', 5, 'application/pdf'),
                UploadedFile::fake()->create('b.pdf', 5, 'application/pdf'),
            ],
        ]],
    ];

    expect(fn () => app(CreateInquiryAction::class)->execute(inquiryClient(), $input))
        ->toThrow(RuntimeException::class, 'forced failure');

    expect(Inquiry::query()->count())->toBe(0)
        ->and(InquiryProject::query()->count())->toBe(0)
        ->and(Schedule::query()->count())->toBe(0)
        ->and(InquiryFile::query()->count())->toBe(0)
        ->and(InquiryStatusHistory::query()->count())->toBe(0)
        ->and(Storage::disk(config('inquiry.disk'))->allFiles())->toBe([]);
});
