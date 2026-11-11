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

    public function createHistory(Lead $lead, ?LeadStatus $from, LeadStatus $to, ?int $changedBy, CarbonInterface $changedAt, ?string $note = null, ?int $inquiryId = null): LeadStatusHistory
    {
        return LeadStatusHistory::query()->create([
            'lead_id' => $lead->id,
            'inquiry_id' => $inquiryId,
            'from_status' => $from,
            'to_status' => $to,
            'changed_by' => $changedBy,
            'note' => $note,
            'changed_at' => $changedAt,
        ]);
    }

    public function forClientForUpdate(User $client): ?Lead
    {
        return Lead::query()
            ->whereBelongsTo($client, 'client')
            ->lockForUpdate()
            ->first();
    }

    public function lockForUpdate(int $id): Lead
    {
        return Lead::query()->whereKey($id)->lockForUpdate()->firstOrFail();
    }

    public function moveTo(Lead $lead, User $marketingUser): void
    {
        $lead->forceFill(['marketing_user_id' => $marketingUser->id])->save();
    }

    public function paginateFor(?User $marketingUser, ?string $escapedSearch, int $perPage, int $page, ?LeadStatus $status = null): LengthAwarePaginator
    {
        return $this->visibleTo($marketingUser)
            ->when($status !== null, fn (Builder $query) => $query->whereHas('inquiries', fn (Builder $inquiry) => $status === LeadStatus::Idle
                ? $inquiry->whereNull('marketing_status')
                : $inquiry->where('marketing_status', $status)))
            ->when($escapedSearch !== null, function (Builder $query) use ($escapedSearch): void {
                $pattern = "%{$escapedSearch}%";

                $query->where(fn (Builder $query) => $query
                    ->where('reference', 'ILIKE', $pattern)
                    ->orWhereHas('client', fn (Builder $client) => $client
                        ->where('name', 'ILIKE', $pattern)
                        ->orWhere('email', 'ILIKE', $pattern)
                        ->orWhereHas('profile', fn (Builder $profile) => $profile->where('phone', 'ILIKE', $pattern)))
                    ->orWhereHas('inquiries', fn (Builder $inquiry) => $inquiry->where('reference', 'ILIKE', $pattern)));
            })
            ->with([
                'client:id,name,email',
                'client.profile:user_id,phone',
                'marketingUser:id,name',
                'inquiries' => fn ($inquiries) => $inquiries->select(['id', 'lead_id', 'created_by', 'reference', 'name', 'marketing_status', 'sales_status'])->orderBy('id'),
                'inquiries.conversation:id,inquiry_id,type',
                'history.inquiry:id,reference',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage, page: $page);
    }

    public function pickable(?User $marketingUser, bool $withWonInquiry): Collection
    {
        return $this->visibleTo($marketingUser)
            ->when($withWonInquiry, fn (Builder $query) => $query->whereHas('inquiries', fn (Builder $inquiry) => $inquiry->where('marketing_status', LeadStatus::Won)))
            ->with(['client:id,name', 'inquiries:id,lead_id,marketing_status'])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();
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
