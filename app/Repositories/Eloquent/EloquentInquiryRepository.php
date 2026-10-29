<?php

namespace App\Repositories\Eloquent;

use App\Data\InquiryListFilters;
use App\Enums\DocumentType;
use App\Enums\InquiryStatus;
use App\Enums\InquiryStatusTrack;
use App\Enums\PaymentStatus;
use App\Enums\ProcessStatus;
use App\Enums\Role;
use App\Enums\SalesStatus;
use App\Models\Inquiry;
use App\Models\InquiryFile;
use App\Models\InquiryStatusHistory;
use App\Models\Lead;
use App\Models\PaymentTransaction;
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
        SalesStatus|PaymentStatus|ProcessStatus|null $from,
        SalesStatus|PaymentStatus|ProcessStatus $to,
        ?int $changedBy,
        CarbonInterface $changedAt,
        ?string $note = null,
    ): InquiryStatusHistory {
        return $inquiry->statusHistory()->create([
            'track' => $track,
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'changed_by' => $changedBy,
            'note' => $note,
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

    public function findByReferenceForUpdate(string $reference): ?Inquiry
    {
        return Inquiry::query()->where('reference', $reference)->lockForUpdate()->first();
    }

    public function addPayment(Inquiry $inquiry, string $amount, ?int $recordedBy, CarbonInterface $receivedAt): PaymentTransaction
    {
        return PaymentTransaction::query()->create([
            'inquiry_id' => $inquiry->id,
            'kind' => 'payment',
            'amount' => $amount,
            'recorded_by' => $recordedBy,
            'received_at' => $receivedAt,
            'created_at' => $receivedAt,
        ]);
    }

    public function paidCentavos(Inquiry $inquiry): int
    {
        $row = DB::selectOne(
            "SELECT COALESCE(SUM(CASE WHEN kind = 'refund' THEN -amount ELSE amount END), 0) * 100 AS centavos FROM payment_transactions WHERE inquiry_id = ?",
            [$inquiry->id],
        );

        return (int) round((float) $row->centavos);
    }

    public function recordQuote(Inquiry $inquiry, int $revision, string $quotedTotal): void
    {
        $inquiry->forceFill(['quote_revision' => $revision, 'quoted_total' => $quotedTotal])->save();
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
            ->withLeadStatus()
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

    public function lockForLead(Lead $lead): Collection
    {
        return Inquiry::query()
            ->whereBelongsTo($lead)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    public function linkUnlinkedToLead(Lead $lead): int
    {
        return Inquiry::query()
            ->where('client_id', $lead->client_id)
            ->whereNull('lead_id')
            ->update(['lead_id' => $lead->id, 'updated_at' => now()]);
    }

    public function updateStatus(
        Inquiry $inquiry,
        InquiryStatusTrack $track,
        SalesStatus|PaymentStatus|ProcessStatus $status,
        CarbonInterface $changedAt,
    ): void {
        $column = $track->column();

        $inquiry->forceFill([$column => $status, "{$column}_changed_at" => $changedAt])->save();
    }

    public function findForClientByReferenceWithDetails(User $client, string $reference): ?Inquiry
    {
        return $this->withDetails(Inquiry::query()->whereBelongsTo($client, 'client'))
            ->where('reference', $reference)
            ->first();
    }

    public function findByReferenceWithDetails(string $reference): ?Inquiry
    {
        return $this->withDetails(Inquiry::query())
            ->where('reference', $reference)
            ->first();
    }

    public function paginateForStaff(?string $escapedSearch, ?User $user, ?string $statusFilter, int $perPage, int $page): LengthAwarePaginator
    {
        $role = $user?->primaryRole();

        return Inquiry::query()
            ->when($statusFilter !== null && $statusFilter !== 'all', function (Builder $query) use ($role, $statusFilter): void {
                if ($role === Role::Marketing) {
                    $query->whereHas('lead', fn (Builder $lead) => $lead->where('status', $statusFilter));
                } elseif ($role === Role::Sales) {
                    $query->where('sales_status', $statusFilter);
                } elseif ($role === Role::Accounting) {
                    $query->where('payment_status', $statusFilter);
                } elseif ($role === Role::Logistics || $role === Role::Technical) {
                    $query->where('process_status', $statusFilter);
                } else {
                    try {
                        $status = InquiryStatus::from($statusFilter);
                        $query->whereDisplayStatus($status);
                    } catch (\ValueError) {
                        // Invalid status
                    }
                }
            })
            ->when($escapedSearch !== null, function (Builder $query) use ($escapedSearch): void {
                $pattern = "%{$escapedSearch}%";

                $query->where(function (Builder $query) use ($pattern): void {
                    $query->where('reference', 'ILIKE', $pattern)
                        ->orWhere('name', 'ILIKE', $pattern)
                        ->orWhereHas('clientRecord', fn (Builder $client) => $client->where('full_name', 'ILIKE', $pattern)
                            ->orWhereHas('contacts', fn (Builder $contact) => $contact->where('value', 'ILIKE', $pattern)))
                        ->orWhereHas('client', fn (Builder $client) => $client->where('name', 'ILIKE', $pattern)
                            ->orWhere('email', 'ILIKE', $pattern));
                });
            })
            ->with([
                'clientRecord:id,kind,user_id,full_name',
                'clientRecord.contacts:id,client_id,kind,value',
                'client:id,name,email',
                'client.profile:user_id,phone',
            ])
            ->withLeadStatus()
            ->withJobOrderFlag()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage, page: $page);
    }

    /**
     * What the details and edit pages read of an inquiry.
     *
     * @param  Builder<Inquiry>  $query
     * @return Builder<Inquiry>
     */
    private function withDetails(Builder $query): Builder
    {
        return $query
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
                'lead:id,reference,status,status_changed_at',
            ]);
    }
}
