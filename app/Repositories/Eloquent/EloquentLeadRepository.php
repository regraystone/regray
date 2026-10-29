<?php

namespace App\Repositories\Eloquent;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\LeadStatusHistory;
use App\Models\User;
use App\Repositories\Contracts\LeadRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class EloquentLeadRepository implements LeadRepositoryInterface
{
    public function nextReferenceNumber(): int
    {
        return (int) DB::selectOne("SELECT nextval('lead_reference_seq') AS number")->number;
    }

    public function create(array $attributes): Lead
    {
        return Lead::query()->create($attributes);
    }

    public function createHistory(Lead $lead, ?LeadStatus $from, LeadStatus $to, ?int $changedBy, CarbonInterface $changedAt, ?string $note = null): LeadStatusHistory
    {
        return LeadStatusHistory::query()->create([
            'lead_id' => $lead->id,
            'from_status' => $from,
            'to_status' => $to,
            'changed_by' => $changedBy,
            'note' => $note,
            'changed_at' => $changedAt,
        ]);
    }

    public function openForUpdate(User $client): ?Lead
    {
        return Lead::query()
            ->whereBelongsTo($client, 'client')
            ->whereIn('status', [LeadStatus::Idle->value, LeadStatus::Won->value])
            ->lockForUpdate()
            ->first();
    }

    public function lockForUpdate(int $id): Lead
    {
        return Lead::query()->whereKey($id)->lockForUpdate()->firstOrFail();
    }

    public function updateStatus(Lead $lead, LeadStatus $status, CarbonInterface $changedAt): void
    {
        $lead->forceFill(['status' => $status, 'status_changed_at' => $changedAt])->save();
    }

    public function moveTo(Lead $lead, User $marketingUser): void
    {
        $lead->forceFill(['marketing_user_id' => $marketingUser->id])->save();
    }

    public function paginateFor(?User $marketingUser, ?string $escapedSearch, int $perPage, int $page): LengthAwarePaginator
    {
        return $this->visibleTo($marketingUser)
            ->when($escapedSearch !== null, function (Builder $query) use ($escapedSearch): void {
                $pattern = "%{$escapedSearch}%";

                $query->where(fn (Builder $query) => $query
                    ->where('reference', 'ILIKE', $pattern)
                    ->orWhereHas('client', fn (Builder $client) => $client
                        ->where('name', 'ILIKE', $pattern)
                        ->orWhere('email', 'ILIKE', $pattern)
                        ->orWhereHas('profile', fn (Builder $profile) => $profile->where('phone', 'ILIKE', $pattern))));
            })
            ->with([
                'client:id,name,email',
                'client.profile:user_id,phone',
                'marketingUser:id,name',
                'inquiries' => fn ($inquiries) => $inquiries->select(['id', 'lead_id', 'reference', 'name'])->orderBy('id'),
                'inquiries.conversation:id,inquiry_id,type',
                'history',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage, page: $page);
    }

    public function withStatuses(array $statuses, ?User $marketingUser): Collection
    {
        return $this->visibleTo($marketingUser)
            ->whereIn('status', array_map(fn (LeadStatus $status): string => $status->value, $statuses))
            ->with('client:id,name')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();
    }

    public function filterByStatus(?User $marketingUser, ?string $escapedSearch, int $perPage, int $page, LeadStatus $status): LengthAwarePaginator
    {
        return $this->visibleTo($marketingUser)
            ->where('status', $status)
            ->when($escapedSearch !== null, function (Builder $query) use ($escapedSearch): void {
                $pattern = "%{$escapedSearch}%";

                $query->where(fn (Builder $query) => $query
                    ->where('reference', 'ILIKE', $pattern)
                    ->orWhereHas('client', fn (Builder $client) => $client
                        ->where('name', 'ILIKE', $pattern)
                        ->orWhere('email', 'ILIKE', $pattern)
                        ->orWhereHas('profile', fn (Builder $profile) => $profile->where('phone', 'ILIKE', $pattern))));
            })
            ->with([
                'client:id,name,email',
                'client.profile:user_id,phone',
                'marketingUser:id,name',
                'inquiries' => fn ($inquiries) => $inquiries->select(['id', 'lead_id', 'reference', 'name'])->orderBy('id'),
                'inquiries.conversation:id,inquiry_id,type',
                'history',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage, page: $page);
    }

    public function findByReference(string $reference): ?Lead
    {
        return Lead::query()->where('reference', $reference)->first();
    }

    /** @return Builder<Lead> */
    private function visibleTo(?User $marketingUser): Builder
    {
        return Lead::query()->when($marketingUser !== null, fn (Builder $query) => $query->whereBelongsTo($marketingUser, 'marketingUser'));
    }
}
