<?php

namespace App\Repositories\Eloquent;

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
        InquiryStatusTrack $track,
        MarketingStatus|SalesStatus|PaymentStatus|ProcessStatus|null $from,
        MarketingStatus|SalesStatus|PaymentStatus|ProcessStatus $to,
        ?int $changedBy,
        CarbonInterface $changedAt,
    ): InquiryStatusHistory {
        return $inquiry->statusHistory()->create([
            'track' => $track,
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'changed_by' => $changedBy,
            'changed_at' => $changedAt,
        ]);
    }

    public function attachServices(Inquiry $inquiry, array $serviceIds): void
    {
        $inquiry->services()->attach($serviceIds);
    }

    public function attachApplications(Inquiry $inquiry, array $applicationIds): void
    {
        $rows = [];

        foreach ($applicationIds as $position => $applicationId) {
            $rows[$applicationId] = ['sort_order' => $position];
        }

        $inquiry->categories()->attach($rows);
    }

    public function addStones(Inquiry $inquiry, array $stones): void
    {
        foreach ($stones as $position => $stone) {
            $inquiry->stones()->create([...$stone, 'sort_order' => $position]);
        }
    }

    public function addFile(Inquiry $inquiry, array $attributes): InquiryFile
    {
        return $inquiry->files()->create($attributes);
    }

    public function updateDetails(Inquiry $inquiry, array $attributes): void
    {
        $inquiry->forceFill($attributes)->save();
    }

    public function syncServices(Inquiry $inquiry, array $serviceIds): void
    {
        $inquiry->services()->sync($serviceIds);
    }

    public function syncApplications(Inquiry $inquiry, array $applicationIds): void
    {
        $inquiry->categories()->detach();
        $this->attachApplications($inquiry, $applicationIds);
    }

    public function replaceStones(Inquiry $inquiry, array $stones): void
    {
        $inquiry->stones()->delete();
        $this->addStones($inquiry, $stones);
    }

    public function deleteClientAttachmentsExcept(Inquiry $inquiry, array $keepIds): array
    {
        $attachments = fn () => $inquiry->files()->where('document_type', DocumentType::ClientAttachment);

        $removed = $attachments()->whereNotIn('id', $keepIds)->get(['id', 'disk', 'path']);

        $attachments()->whereIn('id', $removed->pluck('id'))->delete();

        return array_values($removed->map(fn (InquiryFile $file): array => ['disk' => $file->disk, 'path' => $file->path])->all());
    }

    public function paginateForClient(User $client, InquiryListFilters $filters, ?string $escapedSearch, int $perPage): LengthAwarePaginator
    {
        $direction = $filters->sort === InquiryListFilters::SORT_OLDEST ? 'asc' : 'desc';

        return Inquiry::query()
            ->whereBelongsTo($client, 'client')
            ->when($filters->status !== null, fn (Builder $query) => $query->inStatusGroup($filters->status))
            ->when($escapedSearch !== null, function (Builder $query) use ($escapedSearch): void {
                $pattern = "%{$escapedSearch}%";

                $query->where(function (Builder $query) use ($pattern): void {
                    $query->where('reference', 'ILIKE', $pattern)
                        ->orWhere('name', 'ILIKE', $pattern)
                        ->orWhereHas('stones.stone', fn (Builder $stone) => $stone->where('name', 'ILIKE', $pattern));
                });
            })
            ->withJobOrderFlag()
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
            ->join('inquiry_stones as ist', 'ist.stone_id', '=', 'stones.id')
            ->whereIn('ist.inquiry_id', $inquiryIds)
            ->groupBy('stones.id', 'ist.inquiry_id')
            ->select(['stones.id', 'stones.slug', 'stones.name', 'stones.thumbnail_path', 'ist.inquiry_id'])
            ->selectRaw('MIN(ist.sort_order) as first_position')
            ->orderBy('ist.inquiry_id')
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
            ->join('inquiry_services as isv', 'isv.service_id', '=', 'services.id')
            ->whereIn('isv.inquiry_id', $inquiryIds)
            ->select(['services.id', 'services.slug', 'services.name', 'isv.inquiry_id'])
            ->orderBy('isv.inquiry_id')
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

    public function updateStatus(
        Inquiry $inquiry,
        InquiryStatusTrack $track,
        MarketingStatus|SalesStatus|PaymentStatus|ProcessStatus $status,
        CarbonInterface $changedAt,
    ): void {
        $column = $track->column();

        $inquiry->forceFill([$column => $status, "{$column}_changed_at" => $changedAt])->save();
    }

    public function findForClientByReferenceWithDetails(User $client, string $reference): ?Inquiry
    {
        return Inquiry::query()
            ->whereBelongsTo($client, 'client')
            ->where('reference', $reference)
            ->with([
                'services' => fn ($services) => $services->withTrashed()->orderBy('services.sort_order'),
                'categories',
                'schedule:id,inquiry_id,arrival_at,status',
                'stones' => fn ($stones) => $stones->orderBy('sort_order'),
                'stones.stone' => fn ($stone) => $stone->withTrashed(),
                'stones.edgeProfile',
                'stones.finish',
                'files' => fn ($files) => $files->orderBy('id'),
                'statusHistory',
            ])
            ->first();
    }
}
