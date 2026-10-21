<?php

namespace App\Repositories\Contracts;

use App\Data\InquiryListFilters;
use App\Enums\InquiryStatus;
use App\Models\Inquiry;
use App\Models\InquiryFile;
use App\Models\InquiryProject;
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
     * @param  array{reference: string, client_id: int, status: InquiryStatus, status_changed_at: CarbonInterface}  $attributes
     */
    public function create(array $attributes): Inquiry;

    public function createStatusHistory(
        Inquiry $inquiry,
        ?InquiryStatus $from,
        InquiryStatus $to,
        ?int $changedBy,
        CarbonInterface $changedAt,
    ): InquiryStatusHistory;

    /**
     * @param  array{name: string, sort_order: int, location: string, landmark: ?string, area_sqm: ?float, deadline_date: ?string, deadline_time: ?string, notes: ?string}  $attributes
     */
    public function addProject(Inquiry $inquiry, array $attributes): InquiryProject;

    /** @param  list<int>  $serviceIds */
    public function attachServices(InquiryProject $project, array $serviceIds): void;

    /** @param  list<int>  $applicationIds  Stored in the order given. */
    public function attachApplications(InquiryProject $project, array $applicationIds): void;

    /**
     * @param  list<array{stone_id: int, edge_profile_id: int, finish_id: int, quantity: int}>  $stones  Stored in the order given.
     */
    public function addStones(InquiryProject $project, array $stones): void;

    /**
     * @param  array{disk: string, path: string, original_name: string, mime_type: string, size_bytes: int, sha256: ?string, uploaded_by: ?int}  $attributes
     */
    public function addFile(InquiryProject $project, array $attributes): InquiryFile;

    /**
     * One page of this client's inquiries, projects loaded in the order they were
     * added. The scope is the `$client` argument and nothing else: it is never
     * read from the filters.
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
     * The distinct services of each inquiry, in the order each was first picked,
     * in one query. Each service carries the `inquiry_id` it belongs to.
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

    public function updateStatus(Inquiry $inquiry, InquiryStatus $status, CarbonInterface $changedAt): void;

    /**
     * This client's inquiry by reference with everything the details page shows:
     * projects in the order added, their services, categories, stones (with the
     * edge and finish) and files, and the status history. Null for a reference
     * that is not theirs.
     */
    public function findForClientByReferenceWithDetails(User $client, string $reference): ?Inquiry;
}
