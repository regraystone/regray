<?php

namespace App\Services;

use App\Data\WrittenFiles;
use App\Enums\InquiryStatus;
use App\Models\Inquiry;
use App\Models\InquiryProject;
use App\Models\User;
use App\Repositories\Contracts\InquiryRepositoryInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * @phpstan-type ProjectInput array{name: string, service_ids: list<int>, application_ids: list<int>, stones: list<array{stone_id: int, edge_profile_id: int, finish_id: int, quantity: int}>, location: string, landmark: ?string, area_sqm: ?float, deadline_date: ?string, deadline_time: ?string, notes: ?string, files: list<UploadedFile>}
 */
class InquiryService
{
    public const REFERENCE_PREFIX = 'INQ-';

    public function __construct(
        public readonly InquiryRepositoryInterface $inquiryRepository,
    ) {}

    /**
     * Write one inquiry and everything under it.
     *
     * Every file that reaches the disk is recorded on `$written` the moment it
     * lands, so the caller can remove them if anything after this point fails.
     * The inquiry starts as pending, with the history row that says so: from
     * nothing, to pending, by the client who sent it.
     *
     * @param  array{projects: list<ProjectInput>}  $input
     */
    public function create(User $client, array $input, WrittenFiles $written): Inquiry
    {
        $now = Date::now();

        $inquiry = $this->inquiryRepository->create([
            'reference' => $this->nextReference(),
            'client_id' => $client->id,
            'status' => InquiryStatus::Pending,
            'status_changed_at' => $now,
        ]);

        $this->inquiryRepository->createStatusHistory(
            $inquiry,
            null,
            InquiryStatus::Pending,
            $client->id,
            $now,
        );

        foreach ($input['projects'] as $position => $project) {
            $this->addProject($inquiry, $client, $position, $project, $written);
        }

        return $inquiry;
    }

    public function nextReference(): string
    {
        $number = $this->inquiryRepository->nextReferenceNumber();

        return self::REFERENCE_PREFIX.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }

    /**
     * @param  ProjectInput  $project
     */
    private function addProject(Inquiry $inquiry, User $client, int $position, array $project, WrittenFiles $written): void
    {
        $row = $this->inquiryRepository->addProject($inquiry, [
            'name' => $project['name'],
            'sort_order' => $position,
            'location' => $project['location'],
            'landmark' => $project['landmark'],
            'area_sqm' => $project['area_sqm'],
            'deadline_date' => $project['deadline_date'],
            'deadline_time' => $project['deadline_time'],
            'notes' => $project['notes'],
        ]);

        $this->inquiryRepository->attachServices($row, $project['service_ids']);
        $this->inquiryRepository->attachApplications($row, $project['application_ids']);
        $this->inquiryRepository->addStones($row, $project['stones']);

        foreach ($project['files'] as $file) {
            $this->addFile($inquiry, $row, $client, $file, $written);
        }
    }

    /**
     * Put one upload on the private disk under inquiries/{reference}/{project id}/
     * and record it. The stored name is generated: the client's own file name is
     * kept only as `original_name`, never as a path.
     */
    private function addFile(Inquiry $inquiry, InquiryProject $project, User $client, UploadedFile $file, WrittenFiles $written): void
    {
        $disk = config('inquiry.disk');
        $extension = strtolower($file->getClientOriginalExtension());
        $name = Str::uuid()->toString().($extension === '' ? '' : '.'.$extension);

        $path = Storage::disk($disk)->putFileAs("inquiries/{$inquiry->reference}/{$project->id}", $file, $name);

        if ($path === false) {
            throw new RuntimeException("Could not store {$file->getClientOriginalName()}.");
        }

        $written->record($path);

        $this->inquiryRepository->addFile($project, [
            'disk' => $disk,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size_bytes' => $file->getSize(),
            'sha256' => hash_file('sha256', $file->getRealPath()) ?: null,
            'uploaded_by' => $client->id,
        ]);
    }
}
