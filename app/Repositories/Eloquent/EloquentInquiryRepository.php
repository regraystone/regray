<?php

namespace App\Repositories\Eloquent;

use App\Data\InquiryListFilters;
use App\Enums\DocumentType;
use App\Enums\InquiryStatus;
use App\Enums\InquiryStatusTrack;
use App\Enums\LeadStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProcessStatus;
use App\Enums\Role;
use App\Enums\SalesStatus;
use App\Models\Inquiry;
use App\Models\InquiryFile;
use App\Models\InquiryHandler;
use App\Models\InquiryStatusHistory;
use App\Models\Lead;
use App\Models\PaymentTransaction;
use App\Models\Stone;
use App\Models\User;
use App\Repositories\Contracts\InquiryRepositoryInterface;
use App\Services\InquiryStatusRules;
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

    public function findVisibleByReferenceForUpdate(User $viewer, string $reference): ?Inquiry
    {
        return Inquiry::query()->visibleTo($viewer)->where('reference', $reference)->lockForUpdate()->first();
    }

    public function addHandlers(Inquiry $inquiry, array $userIds, int $assignedBy, CarbonInterface $assignedAt): void
    {
        InquiryHandler::query()->insertOrIgnore(array_map(fn (int $userId): array => [
            'inquiry_id' => $inquiry->id,
            'user_id' => $userId,
            'assigned_by' => $assignedBy,
            'assigned_at' => $assignedAt,
        ], $userIds));
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

    public function addRefund(Inquiry $inquiry, string $amount, ?int $recordedBy, CarbonInterface $refundedAt): PaymentTransaction
    {
        return PaymentTransaction::query()->create([
            'inquiry_id' => $inquiry->id,
            'kind' => 'refund',
            'amount' => $amount,
            'recorded_by' => $recordedBy,
            'received_at' => $refundedAt,
            'created_at' => $refundedAt,
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

    public function recordContractTotal(Inquiry $inquiry, string $contractTotal): void
    {
        $inquiry->forceFill(['contract_total' => $contractTotal])->save();
    }

    public function recordMarketingStatus(Inquiry $inquiry, LeadStatus $status, CarbonInterface $changedAt): void
    {
        $inquiry->forceFill(['marketing_status' => $status, 'marketing_status_changed_at' => $changedAt])->save();
    }

    public function updateDetails(Inquiry $inquiry, array $attributes): void
    {
        $inquiry->forceFill($attributes)->save();
    }

    public function replaceBrands(Inquiry $inquiry, array $brandIds): void
    {
        $inquiry->brands()->sync($brandIds);
    }

    public function replaceStones(Inquiry $inquiry, array $stones): void
    {
        $inquiry->stones()->delete();
        $this->addStones($inquiry, $stones);
    }

    public function replaceDetailAnswers(Inquiry $inquiry, array $answers): void
    {
        $inquiry->detailAnswers()->delete();
        $inquiry->detailAnswers()->createMany($answers);
    }

    public function deleteClientAttachmentsExcept(Inquiry $inquiry, array $keepIds): array
    {
        $attachments = fn () => $inquiry->files()->where('document_type', DocumentType::ClientAttachment);

        $removed = $attachments()->whereNotIn('id', $keepIds)->get(['id', 'disk', 'path']);

        $attachments()->whereIn('id', $removed->pluck('id'))->delete();

        return array_values($removed->map(fn (InquiryFile $file): array => ['disk' => $file->disk, 'path' => $file->path])->all());
    }

    public function clientHasActive(User $client): bool
    {
        return Inquiry::query()
            ->where('client_id', $client->id)
            ->where(fn (Builder $query) => $query->whereNull('sales_status')->orWhere('sales_status', '!=', SalesStatus::Rejected))
            ->where(fn (Builder $query) => $query->whereNull('process_status')->orWhere('process_status', '!=', ProcessStatus::Completed))
            ->exists();
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
            ->with('service:id,slug,name')
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

    public function findForClientByReferenceForUpdate(User $client, string $reference): ?Inquiry
    {
        return Inquiry::query()
            ->whereBelongsTo($client, 'client')
            ->where('reference', $reference)
            ->lockForUpdate()
            ->first();
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

        $inquiry->forceFill([
            $column => $status,
            "{$column}_changed_at" => $changedAt,
            // Completed is final, so this is set once.
            ...($status === ProcessStatus::Completed ? ['completed_at' => $changedAt] : []),
        ])->save();
    }

    public function findForClientByReferenceWithDetails(User $client, string $reference): ?Inquiry
    {
        return $this->withDetails(Inquiry::query()->whereBelongsTo($client, 'client'))
            ->where('reference', $reference)
            ->first();
    }

    public function findByReferenceWithDetails(User $viewer, string $reference): ?Inquiry
    {
        return $this->withDetails(Inquiry::query()->visibleTo($viewer))
            ->where('reference', $reference)
            ->first();
    }

    public function paginateForStaff(?string $escapedSearch, User $user, ?string $statusFilter, int $perPage, int $page): LengthAwarePaginator
    {
        $role = $user->primaryRole();
        $seesPending = InquiryStatusRules::seesPending($user);

        return Inquiry::query()
            ->visibleTo($user)
            ->when(! $seesPending, fn (Builder $query) => $query->whereNot(fn (Builder $query) => $query->whereDisplayStatus(InquiryStatus::Pending)))
            // Accounting has the approved ones, and keeps those it refunded in full, which that cancelled.
            ->when($role === Role::Accounting, fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereIn('sales_status', [SalesStatus::Won, SalesStatus::ForRefund])
                ->orWhere('payment_status', PaymentStatus::Refunded)))
            // Logistics receives an inquiry once its first payment has started logistics.
            ->when($role === Role::Logistics, fn (Builder $query) => $query->whereNotNull('process_status'))
            ->when($statusFilter !== null && $statusFilter !== 'all', function (Builder $query) use ($role, $statusFilter): void {
                // Accounting and logistics filter by their own status; everyone
                // else by the inquiry's. An unknown value is no filter.
                if ($role === Role::Accounting) {
                    $query->where('payment_status', $statusFilter);
                } elseif ($role === Role::Logistics) {
                    $query->where('process_status', $statusFilter);
                } elseif (($status = InquiryStatus::tryFrom($statusFilter)) !== null) {
                    $query->whereDisplayStatus($status);
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
                'schedule:id,inquiry_id,arrival_at',
                // What InquiryPolicy reads of each row (Inquiry::isVisibleTo()), so it asks nothing more.
                'lead:id,marketing_user_id',
                'handlers:inquiry_id,user_id',
            ])
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
                'service.serviceType',
                'service.features',
                'service.stoneTypes:stone_types.id,stone_types.slug',
                'service.detailFields.options',
                'detailAnswers.detailField',
                'detailAnswers.option',
                'brands:id,slug,name,logo_path,sort_order',
                'schedule:id,inquiry_id,arrival_at,status',
                'stones' => fn ($stones) => $stones->orderBy('sort_order'),
                'stones.stone' => fn ($stone) => $stone->withTrashed(),
                'stones.stone.type' => fn ($type) => $type->withTrashed(),
                'stones.stone.brand',
                // heldStones' cards list each stone's slabs, as the Inquire page's do,
                // and the details page tells a standard slab from a custom one.
                'stones.stone.variants.size',
                'stones.stone.variants.thickness',
                'stones.edgeProfile',
                'stones.finish',
                'files' => fn ($files) => $files->orderBy('id'),
                'statusHistory',
                'paymentTransactions',
                'lead:id,reference,marketing_user_id',
                'handlers:inquiry_id,user_id,assigned_at',
                'handlers.user:id,name',
                'handlers.user.roles',
            ]);
    }
}
