<?php

namespace App\Repositories\Contracts;

use App\Data\Message\ConversationListFilters;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface ConversationRepositoryInterface
{
    /**
     * One page of the conversations a user may see, most recently active first.
     *
     * A conversation is listed when the user is a member who reads and sends,
     * and also — for the support pool — when it is an unassigned Regray Team
     * thread, and — for the super-admin — when it is any Regray Team thread.
     * A client's own Regray Team is always first. Each row carries
     * `last_message_id`, `last_activity_at` and `unread_count`.
     *
     * @param  string|null  $escapedSearch  Already escaped for a LIKE pattern.
     * @return LengthAwarePaginator<int, Conversation>
     */
    public function paginateForUser(
        User $user,
        ConversationListFilters $filters,
        ?string $escapedSearch,
        bool $includeSupportPool,
        bool $includeAllSupport,
        int $perPage,
    ): LengthAwarePaginator;

    /**
     * The unread messages across every conversation the user's list holds:
     * the sum of the rows' `unread_count`, over the same conversations.
     */
    public function unreadTotalFor(User $user, bool $includeSupportPool, bool $includeAllSupport): int;

    /**
     * The same row a page would hold, for one conversation.
     */
    public function findSummaryForUser(User $user, int $conversationId): ?Conversation;

    /**
     * The latest message of each conversation, sender loaded, including removed
     * ones so the preview can say so.
     *
     * @param  list<int>  $messageIds
     * @return Collection<int, Message>
     */
    public function previewMessages(array $messageIds): Collection;

    public function find(int $id): ?Conversation;

    public function findSupportFor(User $client): ?Conversation;

    /** Create the client's Regray Team thread. Throws on a duplicate. */
    public function createSupportFor(User $client): Conversation;

    /** The row, locked until the transaction ends. */
    public function lockForUpdate(int $id): Conversation;

    /**
     * Assign the thread only if nobody has. Returns whether this call won.
     */
    public function assignIfUnassigned(int $id, int $userId, CarbonInterface $at): bool;

    public function setAssignee(int $id, ?int $userId, ?CarbonInterface $at): void;

    /**
     * A group bound to an inquiry: the inquiry's group chat. Throws on a
     * second group for the same inquiry.
     *
     * @param  array{name: string, created_by: int, inquiry_id: int}  $attributes
     */
    public function createGroup(array $attributes): Conversation;

    /** Mark the conversation active now. */
    public function touch(int $id): void;

    /** The group chat bound to an inquiry, if one is. */
    public function groupForInquiry(int $inquiryId): ?Conversation;

    /**
     * User ids that should hear about activity on the conversation's list row.
     *
     * @return list<int>
     */
    public function memberUserIds(int $conversationId): array;
}
