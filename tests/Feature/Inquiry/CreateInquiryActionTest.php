<?php

use App\Actions\Inquiry\CreateInquiryAction;
use App\Enums\DeliveryMethod;
use App\Models\Inquiry;
use App\Models\InquiryFile;
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

    public function addFile(Inquiry $inquiry, array $attributes): InquiryFile
    {
        if (++$this->files === 2) {
            throw new RuntimeException('forced failure');
        }

        return parent::addFile($inquiry, $attributes);
    }
}

it('leaves no rows and no files when a write fails after files are on disk', function (): void {
    $this->app->bind(InquiryRepositoryInterface::class, FailsOnSecondFileRepository::class);

    $input = [
        'projects' => [[
            'name' => 'Counters',
            'service_id' => Service::query()->value('id'),
            'stones' => [],
            'brand_ids' => [],
            'brand_no_preference' => true,
            'delivery_method' => DeliveryMethod::Delivery,
            'address' => testAddress(),
            'answers' => [],
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
        ->and(Schedule::query()->count())->toBe(0)
        ->and(InquiryFile::query()->count())->toBe(0)
        ->and(InquiryStatusHistory::query()->count())->toBe(0)
        ->and(Storage::disk(config('inquiry.disk'))->allFiles())->toBe([]);
});
