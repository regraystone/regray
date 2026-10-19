<?php

namespace App\Repositories\Eloquent;

use App\Enums\InquiryStatus;
use App\Models\Inquiry;
use App\Models\InquiryFile;
use App\Models\InquiryProject;
use App\Models\InquiryStatusHistory;
use App\Repositories\Contracts\InquiryRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class EloquentInquiryRepository implements InquiryRepositoryInterface
{
    public function nextReferenceNumber(): int
    {
        return (int) DB::selectOne("SELECT nextval('inquiry_reference_seq') AS number")->number;
    }

    public function create(array $attributes): Inquiry
    {
        return Inquiry::query()->create($attributes);
    }

    public function createStatusHistory(
        Inquiry $inquiry,
        ?InquiryStatus $from,
        InquiryStatus $to,
        ?int $changedBy,
        CarbonInterface $changedAt,
    ): InquiryStatusHistory {
        return $inquiry->statusHistory()->create([
            'from_status' => $from,
            'to_status' => $to,
            'changed_by' => $changedBy,
            'changed_at' => $changedAt,
        ]);
    }

    public function addProject(Inquiry $inquiry, array $attributes): InquiryProject
    {
        return $inquiry->projects()->create($attributes);
    }

    public function attachServices(InquiryProject $project, array $serviceIds): void
    {
        $project->services()->attach($serviceIds);
    }

    public function attachApplications(InquiryProject $project, array $applicationIds): void
    {
        $rows = [];

        foreach ($applicationIds as $position => $applicationId) {
            $rows[$applicationId] = ['sort_order' => $position];
        }

        $project->categories()->attach($rows);
    }

    public function addStones(InquiryProject $project, array $stones): void
    {
        foreach ($stones as $position => $stone) {
            $project->stones()->create([...$stone, 'sort_order' => $position]);
        }
    }

    public function addFile(InquiryProject $project, array $attributes): InquiryFile
    {
        return $project->files()->create($attributes);
    }
}
