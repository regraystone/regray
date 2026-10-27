<?php

namespace App\Repositories\Contracts;

use App\Data\InquiryListFilters;
use App\Enums\DocumentType;
use App\Enums\InquiryStatusTrack;
use App\Enums\MarketingStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProcessStatus;
use App\Enums\SalesStatus;
use App\Models\Inquiry;
use App\Models\InquiryFile;
use App\Models\InquiryStatusHistory;
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
     * time. Only marketing's stage has started; the other role statuses stay
     * NULL until theirs do.
     *
     * @param  array{reference: string, client_id: int, name: string, location: string, landmark: ?string, area_sqm: ?float, notes: ?string, marketing_status: MarketingStatus, marketing_status_changed_at: CarbonInterface}  $attributes
     */
    public function create(array $attributes): Inquiry;

    public function createStatusHistory(
        Inquiry $inquiry,
        InquiryStatusTrack $track,
        MarketingStatus|SalesStatus|PaymentStatus|ProcessStatus|null $from,
        MarketingStatus|SalesStatus|PaymentStatus|ProcessStatus $to,
        ?int $changedBy,
        CarbonInterface $changedAt,
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
     * @param  array{document_type: DocumentType, revision?: ?int, disk: string, path: string, original_name: string, mime_type: string, size_bytes: int, sha256: ?string, uploaded_by: ?int}  $attributes
     */
    public function addFile(Inquiry $inquiry, array $attributes): InquiryFile;

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
    public function updateStatus(
        Inquiry $inquiry,
        InquiryStatusTrack $track,
        MarketingStatus|SalesStatus|PaymentStatus|ProcessStatus $status,
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
}
