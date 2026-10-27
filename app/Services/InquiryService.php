<?php

namespace App\Services;

use App\Data\WrittenFiles;
use App\Enums\AuditEvent;
use App\Enums\DocumentType;
use App\Enums\InquiryStatusTrack;
use App\Enums\MarketingStatus;
use App\Events\InquiryStatusChanged;
use App\Models\Inquiry;
use App\Models\User;
use App\Policies\InquiryActions;
use App\Policies\InquiryFileUploads;
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
        public readonly InquiryFileUploads $fileUploads,
        public readonly AuditLogRepositoryInterface $auditLogRepository,
    ) {}

    /**
     * Write one inquiry per project sent: an inquiry is one project, so a
     * client who sends three projects opens three inquiries.
     *
     * Every file that reaches the disk is recorded on `$written` the moment it
     * lands, so the caller can remove them if anything after this point fails.
     *
     * @param  array{projects: list<ProjectInput>}  $input
     * @return list<Inquiry> In the order the projects were sent.
     */
    public function create(User $client, array $input, WrittenFiles $written): array
    {
        return array_map(
            fn (array $project): Inquiry => $this->createOne($client, $project, $written),
            $input['projects'],
        );
    }

    /**
     * Change the client's own inquiry.
     *
     * Has to run inside a transaction: the inquiry row is locked until it ends,
     * and whether it may still be changed is asked again here, at write time,
     * because it may have moved on since the page was opened.
     *
     * The edit form still sends a list of projects; it must be exactly this
     * inquiry, by id. Its fields, services, applications and stones are
     * replaced, its visit is moved when the deadline changed (refused once the
     * visit is no longer pending), its attachments are kept by id and new
     * uploads added. No status changes and no history row is written; an audit
     * row records the change instead.
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

        $refusal = $this->inquiryActions->updateRefusal($inquiry);

        if ($refusal !== null) {
            throw ValidationException::withMessages(['status' => $refusal]);
        }

        if (count($input['projects']) !== 1 || $input['projects'][0]['id'] !== $inquiry->id) {
            throw ValidationException::withMessages([
                'projects' => 'This inquiry has changed. Reload the page and try again.',
            ]);
        }

        $project = $input['projects'][0];
        $inquiry->load(['schedule', 'files']);
        $before = $this->auditSnapshot($inquiry);

        $this->inquiryRepository->updateDetails($inquiry, [
            'name' => $project['name'],
            'location' => $project['location'],
            'landmark' => $project['landmark'],
            'area_sqm' => $project['area_sqm'],
            'notes' => $project['notes'],
        ]);

        $this->moveVisit($inquiry, $client, $project);
        $this->inquiryRepository->syncServices($inquiry, $project['service_ids']);
        $this->inquiryRepository->syncApplications($inquiry, $project['application_ids']);
        $this->inquiryRepository->replaceStones($inquiry, $project['stones']);

        $removedFiles = $this->inquiryRepository->deleteClientAttachmentsExcept($inquiry, $project['keep_file_ids']);

        foreach ($project['files'] as $file) {
            $this->addFile($inquiry, $client, $file, $written);
        }

        $this->auditLogRepository->record(
            $client->id,
            AuditEvent::Updated,
            $inquiry,
            $before,
            $this->auditSnapshot($inquiry->refresh()),
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
     * One inquiry for one project. Only marketing's stage has started: it is
     * pending, with its history row from nothing, by the client who sent it.
     * Sales, payment and logistics stay NULL until their stages start. Its visit is booked first: the deadline the client gave is that
     * schedule's arrival. All of it is written in the caller's transaction, so
     * a failed inquiry leaves no schedule.
     *
     * The new inquiry is announced with InquiryStatusChanged, which posts the
     * "New inquiry" card. The event waits for the caller's transaction to
     * commit, so an inquiry that is rolled back is never announced.
     *
     * @param  ProjectInput  $project
     */
    private function createOne(User $client, array $project, WrittenFiles $written): Inquiry
    {
        $now = Date::now();

        $inquiry = $this->inquiryRepository->create([
            'reference' => $this->nextReference(),
            'client_id' => $client->id,
            'name' => $project['name'],
            'location' => $project['location'],
            'landmark' => $project['landmark'],
            'area_sqm' => $project['area_sqm'],
            'notes' => $project['notes'],
            'marketing_status' => MarketingStatus::Pending,
            'marketing_status_changed_at' => $now,
        ]);

        $this->inquiryRepository->createStatusHistory($inquiry, InquiryStatusTrack::Marketing, null, MarketingStatus::Pending, $client->id, $now);

        $this->scheduleService->createForInquiry(
            $inquiry,
            $client,
            $project['deadline_date'],
            $project['deadline_time'],
        );

        $this->inquiryRepository->attachServices($inquiry, $project['service_ids']);
        $this->inquiryRepository->attachApplications($inquiry, $project['application_ids']);
        $this->inquiryRepository->addStones($inquiry, $project['stones']);

        foreach ($project['files'] as $file) {
            $this->addFile($inquiry, $client, $file, $written);
        }

        event(new InquiryStatusChanged($inquiry, InquiryStatusTrack::Marketing, null, MarketingStatus::Pending, $client->id));

        return $inquiry;
    }

    /**
     * Move the inquiry's visit to its edited deadline, under its edited name and
     * location, or book one if an older
     * inquiry never had a schedule.
     *
     * A visit that is no longer pending keeps its date and time: changing them
     * is refused against the project's date field. Sending the same deadline
     * back is not a change, so a locked inquiry can still be edited otherwise.
     *
     * @param  ProjectUpdateInput  $project
     */
    private function moveVisit(Inquiry $inquiry, User $client, array $project): void
    {
        $schedule = $inquiry->schedule;

        if ($schedule === null) {
            $this->scheduleService->createForInquiry(
                $inquiry,
                $client,
                $project['deadline_date'],
                $project['deadline_time'],
            );

            return;
        }

        $arrivalAt = $this->scheduleService->arrivalForInquiry($schedule, $project['deadline_date'], $project['deadline_time']);
        $locked = $this->scheduleService->deadlineLockedReason($schedule);

        if ($locked !== null && ! $arrivalAt->equalTo($schedule->arrival_at)) {
            throw ValidationException::withMessages(['projects.0.neededBy' => $locked]);
        }

        $this->scheduleService->updateForInquiry(
            $schedule,
            $inquiry,
            $locked === null ? $arrivalAt : CarbonImmutable::instance($schedule->arrival_at),
        );
    }

    /**
     * What an audit row keeps of an inquiry: the fields a client can edit, by
     * value, so a later reader can see what changed.
     *
     * @return array<string, mixed>
     */
    private function auditSnapshot(Inquiry $inquiry): array
    {
        return [
            'name' => $inquiry->name,
            'location' => $inquiry->location,
            'landmark' => $inquiry->landmark,
            'area_sqm' => $inquiry->area_sqm === null ? null : (float) $inquiry->area_sqm,
            'notes' => $inquiry->notes,
            'service_ids' => $inquiry->services()->pluck('services.id')->all(),
            'application_ids' => $inquiry->categories()->pluck('project_categories.id')->all(),
            'stones' => $inquiry->stones()->orderBy('sort_order')->get(['stone_id', 'edge_profile_id', 'finish_id', 'quantity'])->toArray(),
            'file_ids' => $inquiry->files()->where('document_type', DocumentType::ClientAttachment)->orderBy('id')->pluck('id')->all(),
            'arrival_at' => $inquiry->schedule()->value('arrival_at'),
        ];
    }

    /**
     * Put one of the client's uploads on the private disk under
     * inquiries/{reference}/ and record it as a client attachment. The stored
     * name is generated: the client's own file name is kept only as
     * `original_name`, never as a path.
     */
    private function addFile(Inquiry $inquiry, User $client, UploadedFile $file, WrittenFiles $written): void
    {
        $this->fileUploads->authorize($client, DocumentType::ClientAttachment, $inquiry);

        $disk = config('inquiry.disk');
        $extension = strtolower($file->getClientOriginalExtension());
        $name = Str::uuid()->toString().($extension === '' ? '' : '.'.$extension);

        $path = Storage::disk($disk)->putFileAs("inquiries/{$inquiry->reference}", $file, $name);

        if ($path === false) {
            throw new RuntimeException("Could not store {$file->getClientOriginalName()}.");
        }

        $written->record($path);

        $this->inquiryRepository->addFile($inquiry, [
            'document_type' => DocumentType::ClientAttachment,
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
