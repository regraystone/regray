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

    /** @param  array{reference: string, client_id: int, marketing_user_id: int}  $attributes */
    public function create(array $attributes): Lead;

    /** A row of the lead's log: the lead opened (no inquiry), or a decision on `$inquiryId`. */
    public function createHistory(Lead $lead, ?LeadStatus $from, LeadStatus $to, ?int $changedBy, CarbonInterface $changedAt, ?string $note = null, ?int $inquiryId = null): LeadStatusHistory;

    /** The client's lead, if they have one, locked until the transaction ends. */
    public function forClientForUpdate(User $client): ?Lead;

    /** The lead, locked until the transaction ends. */
    public function lockForUpdate(int $id): Lead;

    public function moveTo(Lead $lead, User $marketingUser): void;

    /**
     * One page of the Leads page, newest first, with each lead's client and
     * their profile, marketing user, inquiries and their group chats, and
     * history. Every lead for the super-admin; otherwise the marketing user's
     * own. With `$status`, only leads with an inquiry at that decision (idle:
     * one still waiting for marketing). `$escapedSearch` matches the
     * reference, the client's name, email and phone, and the reference of any
     * of the lead's inquiries.
     *
     * @return LengthAwarePaginator<int, Lead>
     */
    public function paginateFor(?User $marketingUser, ?string $escapedSearch, int $perPage, int $page, ?LeadStatus $status = null): LengthAwarePaginator;

    /**
     * The leads staff may open an inquiry from, newest first, with their client
     * and their inquiries' decisions. Only the marketing user's own when one is
     * given.
     *
     * @return Collection<int, Lead>
     */
    public function pickable(?User $marketingUser): Collection;

    public function findByReference(string $reference): ?Lead;
}
