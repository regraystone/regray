<?php

namespace App\Services;

use App\Data\WrittenFiles;
use App\Enums\AuditEvent;
use App\Enums\ClientKind;
use App\Enums\DeliveryMethod;
use App\Enums\DocumentType;
use App\Enums\InquiryStatusTrack;
use App\Enums\LeadStatus;
use App\Enums\Role;
use App\Enums\SalesStatus;
use App\Enums\SizeUnit;
use App\Enums\ThicknessUnit;
use App\Events\InquiryChangedByClient;
use App\Events\InquiryStatusChanged;
use App\Events\InquirySubmitted;
use App\Models\Client;
use App\Models\Inquiry;
use App\Models\InquiryFile;
use App\Models\Lead;
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
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * @phpstan-type StoneInput array{stone_id: int, edge_profile_id: ?int, finish_id: ?int, quantity: ?int, slab_width: ?string, slab_length: ?string, slab_unit: ?SizeUnit, thickness: ?string, thickness_unit: ?ThicknessUnit}
 * @phpstan-type AnswerInput array{detail_field_id: int, text_value: ?string, number_value: ?string, option_id: ?int, boolean_value: ?bool}
 * @phpstan-type ProjectInput array{name: string, service_id: int, stones: list<StoneInput>, answers: list<AnswerInput>, delivery_method: DeliveryMethod, location: ?string, deadline_date: string, notes: ?string, stone_advice_requested?: bool, files: list<UploadedFile>}
 * @phpstan-type ProjectUpdateInput array{id: int, name: string, service_id: int, stones: list<StoneInput>, answers: list<AnswerInput>, delivery_method: DeliveryMethod, location: ?string, deadline_date: string, notes: ?string, stone_advice_requested?: bool, keep_file_ids: list<int>, files: list<UploadedFile>}
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
        public readonly LeadService $leadService,
        public readonly InquiryStatusService $inquiryStatusService,
        public readonly ClientService $clientService,
    ) {}

    /**
     * Write one inquiry per project sent: an inquiry is one project, so a
     * client who sends three projects opens three inquiries.
     *
     * The client sends their own (`$by` is null). Staff open one for the client
     * of `$lead` (`$by` is the staff member); the lead is checked to be the
     * client's here, under its row lock, and who may use which lead is the
     * caller's question (LeadPolicy). A client's own inquiry links to their
     * lead, or to none yet (LeadService::leadForNewInquiry()). Either way the
     * inquiry is filed under the account's client record, made the first time
     * it is needed (ClientService::findOrCreateForUser()), and waits for
     * marketing's decision, unless sales opened it.
     *
     * Every file that reaches the disk is recorded on `$written` the moment it
     * lands, so the caller can remove them if anything after this point fails.
     *
     * @param  array{projects: list<ProjectInput>}  $input
     * @return list<Inquiry> In the order the projects were sent.
     *
     * @throws ValidationException When `$lead` is not the client's.
     */
    public function create(User $client, array $input, WrittenFiles $written, ?Lead $lead = null, ?User $by = null): array
    {
        if ($lead !== null) {
            $lead = $this->leadService->leads->lockForUpdate($lead->id);

            if ($lead->client_id !== $client->id) {
                throw ValidationException::withMessages(['lead' => 'This lead is not this client\'s. Choose another lead.']);
            }
        }

        $record = $this->clientService->findOrCreateForUser($client);

        return array_map(
            fn (array $project): Inquiry => $this->createOne($client, $record, $project, $written, $lead ?? $this->leadService->leadForNewInquiry($client), $by),
            $input['projects'],
        );
    }

    /**
     * Staff open inquiries for a direct (walk-in) client, who has no account:
     * no lead is created or linked, and sales starts at negotiation at once.
     * No attachments (the caller's request takes none), and nothing reaches a
     * client account or a Regray Team thread.
     *
     * @param  array{projects: list<ProjectInput>}  $input
     * @return list<Inquiry> In the order the projects were sent.
     *
     * @throws ValidationException When the record is not a direct client.
     */
    public function createForDirectClient(Client $record, array $input, WrittenFiles $written, User $by): array
    {
        if ($record->kind !== ClientKind::Direct) {
            throw ValidationException::withMessages(['client' => 'This client has a Regray account. Open the inquiry from their lead instead.']);
        }

        return array_map(
            fn (array $project): Inquiry => $this->createOne(null, $record, $project, $written, null, $by),
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
     * inquiry, by id. Its fields, service and stones are replaced, its visit is moved when the deadline changed (refused once the
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

        $this->applyProject($inquiry, $client, null, $project);

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

        event(new InquiryChangedByClient($inquiry, InquiryChangedByClient::UPDATED));

        return ['inquiry' => $inquiry, 'removedFiles' => $removedFiles];
    }

    /**
     * Staff change an inquiry's project from the Inquiry list (Edit): its
     * name, service, delivery method, location, notes, stones, the service's
     * answers and deadline. Never its client, its statuses or the client's files.
     * Refused once the inquiry is closed or cancelled, or the client has
     * approved it. An audit row records the change under the staff member.
     *
     * Has to run inside a transaction: the inquiry row is locked until it ends.
     *
     * @param  array{projects: list<ProjectUpdateInput>}  $input
     *
     * @throws ModelNotFoundException When there is no such inquiry, or the staff member does not read it.
     * @throws ValidationException When the inquiry may no longer be changed, or the input does not fit it.
     */
    public function updateByStaff(User $staff, string $reference, array $input, ?string $ipAddress, ?string $userAgent): Inquiry
    {
        $inquiry = $this->inquiryRepository->findVisibleByReferenceForUpdate($staff, $reference)
            ?? throw (new ModelNotFoundException)->setModel(Inquiry::class, [$reference]);

        if (! InquiryStatusRules::isOpen($inquiry)) {
            throw ValidationException::withMessages(['status' => 'This inquiry is closed or cancelled, so it can no longer be changed.']);
        }

        if (! InquiryStatusRules::isEditable($inquiry)) {
            throw ValidationException::withMessages(['status' => InquiryStatusRules::LOCKED_AFTER_APPROVAL]);
        }

        if (count($input['projects']) !== 1 || $input['projects'][0]['id'] !== $inquiry->id) {
            throw ValidationException::withMessages([
                'projects' => 'This inquiry has changed. Reload the page and try again.',
            ]);
        }

        $inquiry->load(['schedule', 'files']);
        $before = $this->auditSnapshot($inquiry);
        $client = $inquiry->client_id === null ? null : User::query()->findOrFail($inquiry->client_id);

        $this->applyProject($inquiry, $client, $staff, $input['projects'][0]);

        $this->auditLogRepository->record(
            $staff->id,
            AuditEvent::Updated,
            $inquiry,
            $before,
            $this->auditSnapshot($inquiry->refresh()),
            $ipAddress,
            $userAgent,
        );

        return $inquiry;
    }

    public function nextReference(): string
    {
        $number = $this->inquiryRepository->nextReferenceNumber();

        return self::REFERENCE_PREFIX.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }

    /**
     * One inquiry for one project, for `$record` (and its account `$client`,
     * which a direct client has none of), linked to `$lead`. No role status
     * has started: sales, payment and logistics stay NULL until their stages
     * do, and an inquiry on a lead waits for marketing to decide it. For a
     * direct client sales starts at once, at negotiation; one sales opens on a
     * lead is decided won as it is sent, which starts sales the same way. Its visit is booked first: the deadline given is
     * that schedule's arrival.
     * All of it is written in the caller's transaction, so a failed inquiry
     * leaves no schedule.
     *
     * The new inquiry is announced with InquiryStatusChanged, which posts the
     * "New inquiry" card. The event waits for the caller's transaction to
     * commit, so an inquiry that is rolled back is never announced.
     *
     * @param  ProjectInput  $project
     */
    private function createOne(?User $client, Client $record, array $project, WrittenFiles $written, ?Lead $lead, ?User $by): Inquiry
    {
        $actor = $by ?? $client ?? throw new RuntimeException('An inquiry is sent by its client or by staff.');

        $inquiry = $this->inquiryRepository->create([
            'reference' => $this->nextReference(),
            'client_id' => $client?->id,
            'client_record_id' => $record->id,
            'lead_id' => $lead?->id,
            'created_by' => $by?->id,
            'name' => $project['name'],
            'service_id' => $project['service_id'],
            'delivery_method' => $project['delivery_method'],
            'location' => $project['location'],
            'notes' => $project['notes'],
            'stone_advice_requested' => $project['stone_advice_requested'] ?? false,
        ]);

        $this->scheduleService->createForInquiry(
            $inquiry,
            $client,
            $project['deadline_date'],
            null,
            $by,
        );

        $this->inquiryRepository->addStones($inquiry, $project['stones']);
        $this->inquiryRepository->replaceDetailAnswers($inquiry, $project['answers']);

        if ($client !== null) {
            foreach ($project['files'] as $file) {
                $this->addFile($inquiry, $client, $file, $written);
            }
        }

        event(new InquiryStatusChanged($inquiry, null, null, null, $actor->id, opening: true));

        if ($client === null) {
            $this->inquiryStatusService->transition($inquiry, InquiryStatusTrack::Sales, SalesStatus::Negotiation, $actor, opening: true);
        } elseif ($lead !== null && $by?->hasRole(Role::Sales->value)) {
            $this->inquiryStatusService->decide($inquiry, LeadStatus::Won, $by, 'Opened by sales.', opening: true);
        }

        event(new InquirySubmitted($inquiry, $by?->id));

        return $inquiry;
    }

    /**
     * The project's fields, service, stones and answers, and its visit
     * moved to the edited deadline. `$bookedBy` is the staff member when staff
     * edit, for a visit booked afresh.
     *
     * @param  ProjectUpdateInput  $project
     */
    private function applyProject(Inquiry $inquiry, ?User $client, ?User $bookedBy, array $project): void
    {
        $this->inquiryRepository->updateDetails($inquiry, [
            'name' => $project['name'],
            'service_id' => $project['service_id'],
            'delivery_method' => $project['delivery_method'],
            'location' => $project['location'],
            'notes' => $project['notes'],
            'stone_advice_requested' => $project['stone_advice_requested'] ?? false,
        ]);

        $this->moveVisit($inquiry, $client, $project, $bookedBy);
        $this->inquiryRepository->replaceStones($inquiry, $project['stones']);
        $this->inquiryRepository->replaceDetailAnswers($inquiry, $project['answers']);
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
    private function moveVisit(Inquiry $inquiry, ?User $client, array $project, ?User $bookedBy = null): void
    {
        $schedule = $inquiry->schedule;

        if ($schedule === null) {
            $this->scheduleService->createForInquiry(
                $inquiry,
                $client,
                $project['deadline_date'],
                null,
                $bookedBy,
            );

            return;
        }

        $arrivalAt = $this->scheduleService->arrivalForInquiry($schedule, $project['deadline_date'], null);
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
            'service_id' => $inquiry->service_id,
            'delivery_method' => $inquiry->delivery_method->value,
            'location' => $inquiry->location,
            'notes' => $inquiry->notes,
            'stone_advice_requested' => $inquiry->stone_advice_requested,
            'stones' => $inquiry->stones()->orderBy('sort_order')->get(['stone_id', 'edge_profile_id', 'finish_id', 'quantity', 'slab_width', 'slab_length', 'slab_unit', 'thickness', 'thickness_unit'])->toArray(),
            'answers' => $inquiry->detailAnswers()->orderBy('detail_field_id')->get(['detail_field_id', 'text_value', 'number_value', 'option_id', 'boolean_value'])->toArray(),
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
        $name = InquiryFile::storedNameFor($file);

        $path = Storage::disk($disk)->putFileAs("inquiries/{$inquiry->reference}", $file, $name);

        if ($path === false) {
            throw new RuntimeException("Could not store {$file->getClientOriginalName()}.");
        }

        $written->record($path);

        $this->inquiryRepository->addFile($inquiry, [
            'document_type' => DocumentType::ClientAttachment,
            'disk' => $disk,
            'path' => $path,
            'stored_name' => $name,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size_bytes' => $file->getSize(),
            'sha256' => hash_file('sha256', $file->getRealPath()) ?: null,
            'uploaded_by' => $client->id,
        ]);
    }
}
