<?php

namespace App\Repositories\Contracts;

use App\Data\InquiryListFilters;
use App\Enums\DocumentType;
use App\Enums\InquiryStatusTrack;
use App\Enums\PaymentStatus;
use App\Enums\ProcessStatus;
use App\Enums\SalesStatus;
use App\Models\Inquiry;
use App\Models\InquiryFile;
use App\Models\InquiryStatusHistory;
use App\Models\Lead;
use App\Models\PaymentTransaction;
use App\Models\Service;
use App\Models\Stone;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface InquiryRepositoryInterface
{
    /** The next value of the `inquiry_reference_seq` sequence. */
    public function nextReferenceNumber(): int;

    /**
     * The inquiry has no deadline of its own: its schedule holds the date and
     * time. No role status has started: each stays NULL until its stage does.
     * `lead_id` is the client's open lead, or NULL when they have none yet.
     *
     * @param  array{reference: string, client_id: ?int, client_record_id: int, lead_id: ?int, name: string, location: string, landmark: ?string, area_sqm: ?float, notes: ?string}  $attributes
     */
    public function create(array $attributes): Inquiry;

    public function createStatusHistory(
        Inquiry $inquiry,
        InquiryStatusTrack $track,
        SalesStatus|PaymentStatus|ProcessStatus|null $from,
        SalesStatus|PaymentStatus|ProcessStatus $to,
        ?int $changedBy,
        CarbonInterface $changedAt,
        ?string $note = null,
    ): InquiryStatusHistory;

    /** @param  list<int>  $serviceIds */
    public function attachServices(Inquiry $inquiry, array $serviceIds): void;

    /** @param  list<int>  $applicationIds  Stored in the order given. */
    public function attachApplications(Inquiry $inquiry, array $applicationIds): void;

    /**
     * @param  list<array{stone_id: int, edge_profile_id: int, finish_id: int, quantity: int}>  $stones  Stored in the order given.
     */
    public function addStones(Inquiry $inquiry, array $stones): void;

    /**
     * @param  array{document_type: DocumentType, status_history_id?: ?int, revision?: ?int, disk: string, path: string, original_name: string, mime_type: string, size_bytes: int, sha256: ?string, uploaded_by: ?int}  $attributes
     */
    public function addFile(Inquiry $inquiry, array $attributes): InquiryFile;

    /** The inquiry by its reference, whoever's it is, locked until the transaction ends. */
    public function findByReferenceForUpdate(string $reference): ?Inquiry;

    /** A payment received, as accounting recorded it. */
    public function addPayment(Inquiry $inquiry, string $amount, ?int $recordedBy, CarbonInterface $receivedAt): PaymentTransaction;

    /** What has been paid so far, refunds taken off, in centavos. */
    public function paidCentavos(Inquiry $inquiry): int;

    /** The quote revision and quoted total of a new quotation. */
    public function recordQuote(Inquiry $inquiry, int $revision, string $quotedTotal): void;

    /**
     * One page of this client's inquiries. The scope is the `$client` argument
     * and nothing else: it is never read from the filters.
     *
     * @param  string|null  $escapedSearch  Already escaped for a LIKE pattern.
     * @return LengthAwarePaginator<int, Inquiry>
     */
    public function paginateForClient(User $client, InquiryListFilters $filters, ?string $escapedSearch, int $perPage): LengthAwarePaginator;

    /**
     * The distinct stones of each inquiry, in the order each was first picked, in
     * one query. Each stone carries the `inquiry_id` it belongs to.
     *
     * @param  list<int>  $inquiryIds
     * @return Collection<int, Stone>
     */
    public function distinctStonesFor(array $inquiryIds): Collection;

    /**
     * The services of each inquiry, in one query. Each service carries the
     * `inquiry_id` it belongs to.
     *
     * @param  list<int>  $inquiryIds
     * @return Collection<int, Service>
     */
    public function distinctServicesFor(array $inquiryIds): Collection;

    /**
     * This client's inquiry by reference, locked until the transaction ends.
     * Null for a reference that is not theirs, so the two cases look the same.
     */
    public function findForClientByReferenceForUpdate(User $client, string $reference): ?Inquiry;

    /** Set one role status and when it changed. */
    /**
     * The lead's inquiries, oldest first, locked until the transaction ends.
     *
     * @return Collection<int, Inquiry>
     */
    public function lockForLead(Lead $lead): Collection;

    /** Link every one of the lead's client's inquiries that has no lead yet to it. Returns how many. */
    public function linkUnlinkedToLead(Lead $lead): int;

    public function updateStatus(
        Inquiry $inquiry,
        InquiryStatusTrack $track,
        SalesStatus|PaymentStatus|ProcessStatus $status,
        CarbonInterface $changedAt,
    ): void;

    /**
     * @param  array{name?: string, location?: string, landmark?: ?string, area_sqm?: ?float, notes?: ?string}  $attributes
     */
    public function updateDetails(Inquiry $inquiry, array $attributes): void;

    /** @param  list<int>  $serviceIds */
    public function syncServices(Inquiry $inquiry, array $serviceIds): void;

    /** @param  list<int>  $applicationIds  Stored in the order given. */
    public function syncApplications(Inquiry $inquiry, array $applicationIds): void;

    /**
     * Replace the inquiry's stone rows with these, in the order given.
     *
     * @param  list<array{stone_id: int, edge_profile_id: int, finish_id: int, quantity: int}>  $stones
     */
    public function replaceStones(Inquiry $inquiry, array $stones): void;

    /**
     * Delete the client's attachments other than `$keepIds`. Quotations, job
     * orders and receipts are staff's and are never touched.
     *
     * @param  list<int>  $keepIds
     * @return list<array{disk: string, path: string}> The deleted files, to remove from their disk.
     */
    public function deleteClientAttachmentsExcept(Inquiry $inquiry, array $keepIds): array;

    /**
     * This client's inquiry by reference with everything the details page shows:
     * services, categories, stones (with the edge and finish), files, the
     * schedule and the status history. Null for a reference that is not theirs.
     */
    public function findForClientByReferenceWithDetails(User $client, string $reference): ?Inquiry;

    /** Any inquiry by its reference, with what the details page reads: for staff. */
    public function findByReferenceWithDetails(string $reference): ?Inquiry;

    /**
     * One page of every inquiry, newest first, for the Inquiry list, with
     * each one's client record, contacts and account. `$escapedSearch`
     * matches the reference, the project name, the client's name and their
     * contacts.
     *
     * A pending inquiry (sales not started) is marketing's, seen from its lead
     * on the Leads page, so only the admin and the owner find it here.
     * Accounting and logistics find only a won inquiry (the client approved
     * the quotation): their work starts there.
     *
     * @return LengthAwarePaginator<int, Inquiry>
     */
    public function paginateForStaff(?string $escapedSearch, ?User $user, ?string $statusFilter, int $perPage, int $page): LengthAwarePaginator;
}
