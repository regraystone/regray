<?php

namespace App\Repositories\Eloquent;

use App\Data\InquiryListFilters;
use App\Enums\InquiryStatus;
use App\Models\Inquiry;
use App\Models\InquiryFile;
use App\Models\InquiryProject;
use App\Models\InquiryStatusHistory;
use App\Models\Service;
use App\Models\Stone;
use App\Models\User;
use App\Repositories\Contracts\InquiryRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
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

    public function paginateForClient(User $client, InquiryListFilters $filters, ?string $escapedSearch, int $perPage): LengthAwarePaginator
    {
        $direction = $filters->sort === InquiryListFilters::SORT_OLDEST ? 'asc' : 'desc';

        return Inquiry::query()
            ->whereBelongsTo($client, 'client')
            ->when($filters->status !== null, fn (Builder $query) => $query->where('status', $filters->status))
            ->when($escapedSearch !== null, function (Builder $query) use ($escapedSearch): void {
                $pattern = "%{$escapedSearch}%";

                $query->where(function (Builder $query) use ($pattern): void {
                    $query->where('reference', 'ILIKE', $pattern)
                        ->orWhereHas('projects', function (Builder $project) use ($pattern): void {
                            $project->where('name', 'ILIKE', $pattern)
                                ->orWhereHas('stones.stone', fn (Builder $stone) => $stone->where('name', 'ILIKE', $pattern));
                        });
                });
            })
            ->with(['projects' => fn ($projects) => $projects->select(['id', 'inquiry_id', 'name', 'sort_order'])->orderBy('sort_order')])
            ->orderBy('created_at', $direction)
            ->orderBy('id', $direction)
            ->paginate($perPage, page: $filters->page);
    }

    public function distinctStonesFor(array $inquiryIds): Collection
    {
        if ($inquiryIds === []) {
            return new Collection;
        }

        return Stone::query()
            ->join('inquiry_project_stones as ips', 'ips.stone_id', '=', 'stones.id')
            ->join('inquiry_projects as ip', 'ip.id', '=', 'ips.inquiry_project_id')
            ->whereIn('ip.inquiry_id', $inquiryIds)
            ->groupBy('stones.id', 'ip.inquiry_id')
            ->select(['stones.id', 'stones.slug', 'stones.name', 'stones.thumbnail_path', 'ip.inquiry_id'])
            ->selectRaw('MIN(ip.sort_order * 1000 + ips.sort_order) as first_position')
            ->orderBy('ip.inquiry_id')
            ->orderBy('first_position')
            ->orderBy('stones.id')
            ->get();
    }

    public function distinctServicesFor(array $inquiryIds): Collection
    {
        if ($inquiryIds === []) {
            return new Collection;
        }

        return Service::query()
            ->join('inquiry_project_services as ipsv', 'ipsv.service_id', '=', 'services.id')
            ->join('inquiry_projects as ip', 'ip.id', '=', 'ipsv.inquiry_project_id')
            ->whereIn('ip.inquiry_id', $inquiryIds)
            ->groupBy('services.id', 'ip.inquiry_id')
            ->select(['services.id', 'services.slug', 'services.name', 'ip.inquiry_id'])
            ->selectRaw('MIN(ip.sort_order) as first_position')
            ->orderBy('ip.inquiry_id')
            ->orderBy('first_position')
            ->orderBy('services.sort_order')
            ->orderBy('services.id')
            ->get();
    }

    public function findForClientByReferenceForUpdate(User $client, string $reference): ?Inquiry
    {
        return Inquiry::query()
            ->whereBelongsTo($client, 'client')
            ->where('reference', $reference)
            ->lockForUpdate()
            ->first();
    }

    public function updateStatus(Inquiry $inquiry, InquiryStatus $status, CarbonInterface $changedAt): void
    {
        $inquiry->forceFill(['status' => $status, 'status_changed_at' => $changedAt])->save();
    }

    public function findForClientByReferenceWithDetails(User $client, string $reference): ?Inquiry
    {
        return Inquiry::query()
            ->whereBelongsTo($client, 'client')
            ->where('reference', $reference)
            ->with([
                'statusHistory',
                'projects' => fn ($projects) => $projects->orderBy('sort_order'),
                'projects.services' => fn ($services) => $services->withTrashed()->orderBy('services.sort_order'),
                'projects.categories',
                'projects.schedule:id,arrival_at',
                'projects.stones' => fn ($stones) => $stones->orderBy('sort_order'),
                'projects.stones.stone' => fn ($stone) => $stone->withTrashed(),
                'projects.stones.edgeProfile',
                'projects.stones.finish',
                'projects.files' => fn ($files) => $files->orderBy('id'),
            ])
            ->first();
    }
}
