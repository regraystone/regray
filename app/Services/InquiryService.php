<?php

namespace App\Services;

use App\Data\WrittenFiles;
use App\Enums\AuditEvent;
use App\Enums\InquiryStatus;
use App\Events\InquiryStatusChanged;
use App\Models\Inquiry;
use App\Models\InquiryProject;
use App\Models\User;
use App\Policies\InquiryActions;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use App\Repositories\Contracts\InquiryRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * @phpstan-type ProjectInput array{name: string, service_ids: list<int>, application_ids: list<int>, stones: list<array{stone_id: int, edge_profile_id: int, finish_id: int, quantity: int}>, location: string, landmark: ?string, area_sqm: ?float, deadline_date: string, deadline_time: ?string, notes: ?string, files: list<UploadedFile>}
 * @phpstan-type ProjectUpdateInput array{id: int, name: string, service_ids: list<int>, application_ids: list<int>, stones: list<array{stone_id: int, edge_profile_id: int, finish_id: int, quantity: int}>, location: string, landmark: ?string, area_sqm: ?float, deadline_date: string, deadline_time: ?string, notes: ?string, keep_file_ids: list<int>, files: list<UploadedFile>}
 * @phpstan-type RemovedFile array{disk: string, path: string}
 */
class InquiryService
{
    public const REFERENCE_PREFIX = 'INQ-';

    public function __construct(
        public readonly InquiryRepositoryInterface $inquiryRepository,
        public readonly ScheduleService $scheduleService,
        public readonly InquiryActions $inquiryActions,
        public readonly AuditLogRepositoryInterface $auditLogRepository,
    ) {}

    /**
     * Write one inquiry and everything under it.
     *
     * Every file that reaches the disk is recorded on `$written` the moment it
     * lands, so the caller can remove them if anything after this point fails.
     * The inquiry starts as pending, with the history row that says so: from
     * nothing, to pending, by the client who sent it.
     *
     * The same change is announced with InquiryStatusChanged, which posts the
     * "New inquiry" card. The event waits for the caller's transaction to
     * commit, so an inquiry that is rolled back is never announced.
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

        event(new InquiryStatusChanged($inquiry, null, InquiryStatus::Pending, $client->id));

        return $inquiry;
    }

    /**
     * Change the client's own inquiry, project by project.
     *
     * Has to run inside a transaction: the inquiry row is locked until it ends,
     * and whether it may still be changed is asked again here, at write time,
     * because it may have been quoted since the page was opened.
     *
     * Only edits: the projects sent must be exactly the inquiry's projects, by
     * id. Each one's fields, services, applications and stones are replaced,
     * its visit is moved when the deadline changed (refused once the visit is
     * no longer pending), its files are kept by id and new uploads added. The
     * status does not change and no history row is written; an audit row
     * records the change instead.
     *
     * New files are recorded on `$written` for the caller to remove if the
     * transaction fails. Removed files are returned rather than deleted, so the
     * caller deletes them only once the change has committed.
     *
     * @param  array{projects: list<ProjectUpdateInput>}  $input
     * @return array{inquiry: Inquiry, removedFiles: list<RemovedFile>}
     *
     * @throws ModelNotFoundException When the reference is not this client's.
     * @throws ValidationException When the inquiry may no longer be changed, or the input does not fit it.
     */
    public function update(User $client, string $reference, array $input, WrittenFiles $written, ?string $ipAddress, ?string $userAgent): array
    {
        $inquiry = $this->inquiryRepository->findForClientByReferenceForUpdate($client, $reference)
            ?? throw (new ModelNotFoundException)->setModel(Inquiry::class, [$reference]);

        $refusal = $this->inquiryActions->updateRefusal($inquiry->status);

        if ($refusal !== null) {
            throw ValidationException::withMessages(['status' => $refusal]);
        }

        $projects = $this->inquiryRepository->projectsForUpdate($inquiry)->keyBy('id');
        $sentIds = array_map(fn (array $project): int => $project['id'], $input['projects']);

        if (count($sentIds) !== $projects->count() || array_diff($sentIds, $projects->keys()->all()) !== []) {
            throw ValidationException::withMessages([
                'projects' => "This inquiry's projects have changed. Reload the page and try again.",
            ]);
        }

        $before = [];
        $after = [];
        $removedFiles = [];

        foreach ($input['projects'] as $index => $project) {
            /** @var InquiryProject $row */
            $row = $projects->get($project['id']);
            $before[$row->id] = $this->auditSnapshot($row);

            $this->moveVisit($inquiry, $client, $row, $index, $project);

            $this->inquiryRepository->updateProject($row, [
                'name' => $project['name'],
                'location' => $project['location'],
                'landmark' => $project['landmark'],
                'area_sqm' => $project['area_sqm'],
                'notes' => $project['notes'],
            ]);
            $this->inquiryRepository->syncServices($row, $project['service_ids']);
            $this->inquiryRepository->syncApplications($row, $project['application_ids']);
            $this->inquiryRepository->replaceStones($row, $project['stones']);

            array_push($removedFiles, ...$this->inquiryRepository->deleteFilesExcept($row, $project['keep_file_ids']));

            foreach ($project['files'] as $file) {
                $this->addFile($inquiry, $row, $client, $file, $written);
            }

            $after[$row->id] = $this->auditSnapshot($row->refresh());
        }

        $this->auditLogRepository->record(
            $client->id,
            AuditEvent::Updated,
            $inquiry,
            ['projects' => $before],
            ['projects' => $after],
            $ipAddress,
            $userAgent,
        );

        return ['inquiry' => $inquiry, 'removedFiles' => $removedFiles];
    }

    public function nextReference(): string
    {
        $number = $this->inquiryRepository->nextReferenceNumber();

        return self::REFERENCE_PREFIX.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Each project is booked as a visit first, and the project points at it:
     * the deadline the client gave is that schedule's arrival. Both are written
     * in the caller's transaction, so a failed inquiry leaves no schedule.
     *
     * @param  ProjectInput  $project
     */
    private function addProject(Inquiry $inquiry, User $client, int $position, array $project, WrittenFiles $written): void
    {
        $schedule = $this->scheduleService->createForInquiryProject(
            $inquiry,
            $client,
            $project['name'],
            $project['location'],
            $project['deadline_date'],
            $project['deadline_time'],
        );

        $row = $this->inquiryRepository->addProject($inquiry, [
            'name' => $project['name'],
            'sort_order' => $position,
            'location' => $project['location'],
            'landmark' => $project['landmark'],
            'area_sqm' => $project['area_sqm'],
            'schedule_id' => $schedule->id,
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
     * Move a project's visit to its edited deadline, or book one if an older
     * project never had a schedule.
     *
     * A visit that is no longer pending keeps its date and time: changing them
     * is refused against this project's date field. Sending the same deadline
     * back is not a change, so a locked project can still be edited otherwise.
     *
     * @param  ProjectUpdateInput  $project
     */
    private function moveVisit(Inquiry $inquiry, User $client, InquiryProject $row, int $index, array $project): void
    {
        $schedule = $row->schedule;

        if ($schedule === null) {
            $schedule = $this->scheduleService->createForInquiryProject(
                $inquiry,
                $client,
                $project['name'],
                $project['location'],
                $project['deadline_date'],
                $project['deadline_time'],
            );
            $this->inquiryRepository->updateProject($row, ['schedule_id' => $schedule->id]);

            return;
        }

        $arrivalAt = $this->scheduleService->arrivalForInquiryProject($schedule, $project['deadline_date'], $project['deadline_time']);
        $locked = $this->scheduleService->deadlineLockedReason($schedule);

        if ($locked !== null && ! $arrivalAt->equalTo($schedule->arrival_at)) {
            throw ValidationException::withMessages(["projects.{$index}.neededBy" => $locked]);
        }

        $this->scheduleService->updateForInquiryProject(
            $schedule,
            $inquiry,
            $project['name'],
            $project['location'],
            $locked === null ? $arrivalAt : CarbonImmutable::instance($schedule->arrival_at),
        );
    }

    /**
     * What an audit row keeps of a project: the fields a client can edit, by
     * value, so a later reader can see what changed.
     *
     * @return array<string, mixed>
     */
    private function auditSnapshot(InquiryProject $project): array
    {
        return [
            'name' => $project->name,
            'location' => $project->location,
            'landmark' => $project->landmark,
            'area_sqm' => $project->area_sqm === null ? null : (float) $project->area_sqm,
            'notes' => $project->notes,
            'service_ids' => $project->services()->pluck('services.id')->all(),
            'application_ids' => $project->categories()->pluck('project_categories.id')->all(),
            'stones' => $project->stones()->orderBy('sort_order')->get(['stone_id', 'edge_profile_id', 'finish_id', 'quantity'])->toArray(),
            'file_ids' => $project->files()->orderBy('id')->pluck('id')->all(),
            'arrival_at' => $project->schedule()->value('arrival_at'),
        ];
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
