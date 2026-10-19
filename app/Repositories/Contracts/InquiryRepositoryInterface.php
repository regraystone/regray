<?php

namespace App\Repositories\Contracts;

use App\Enums\InquiryStatus;
use App\Models\Inquiry;
use App\Models\InquiryFile;
use App\Models\InquiryProject;
use App\Models\InquiryStatusHistory;
use Carbon\CarbonInterface;

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
}
