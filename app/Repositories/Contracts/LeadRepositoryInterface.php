<?php

namespace App\Repositories\Contracts;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\LeadStatusHistory;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface LeadRepositoryInterface
{
    /** The next value of the `lead_reference_seq` sequence. */
    public function nextReferenceNumber(): int;

    /** @param  array{reference: string, client_id: int, marketing_user_id: int, status: LeadStatus, status_changed_at: CarbonInterface}  $attributes */
    public function create(array $attributes): Lead;

    public function createHistory(Lead $lead, ?LeadStatus $from, LeadStatus $to, ?int $changedBy, CarbonInterface $changedAt, ?string $note = null): LeadStatusHistory;

    /** The client's open (idle or won) lead, if they have one, locked until the transaction ends. */
    public function openForUpdate(User $client): ?Lead;

    /** The lead, locked until the transaction ends. */
    public function lockForUpdate(int $id): Lead;

    public function updateStatus(Lead $lead, LeadStatus $status, CarbonInterface $changedAt): void;

    public function moveTo(Lead $lead, User $marketingUser): void;

    /**
     * One page of the Leads page, newest first, with each lead's client and
     * their profile, marketing user, inquiries and their group chats, and
     * history. Every lead for the super-admin; otherwise the marketing user's
     * own. `$escapedSearch` matches the reference and the client's name,
     * email and phone.
     *
     * @return LengthAwarePaginator<int, Lead>
     */
    public function paginateFor(?User $marketingUser, ?string $escapedSearch, int $perPage, int $page): LengthAwarePaginator;

    /**
     * Leads at one of these statuses, newest first, with their client. Only
     * the marketing user's own when one is given.
     *
     * @param  list<LeadStatus>  $statuses
     * @return Collection<int, Lead>
     */
    public function withStatuses(array $statuses, ?User $marketingUser): Collection;

    public function findByReference(string $reference): ?Lead;
}
