<?php

namespace App\Repositories\Contracts;

use App\Enums\ConversationMemberRole;
use App\Models\ConversationMember;
use Illuminate\Database\Eloquent\Collection;

/**
 * Writes to `conversation_members`, keyed by (conversation_id, user_id).
 *
 * The model has no single-column key, so a loaded row can never be saved: every
 * change here is an insert or a query-builder update on both key columns.
 */
interface ConversationMemberRepositoryInterface
{
    public function find(int $conversationId, int $userId): ?ConversationMember;

    /** Whether the user is a member who may read and send (not a pool viewer). */
    public function isActiveMember(int $conversationId, int $userId): bool;

    /** Insert the row, or change its role if it is already there. */
    public function upsert(int $conversationId, int $userId, ConversationMemberRole $role): void;

    /** Insert a viewer row if the user has no row at all; never demotes a member. */
    public function ensureViewer(int $conversationId, int $userId): void;

    public function setRole(int $conversationId, int $userId, ConversationMemberRole $role): void;

    public function delete(int $conversationId, int $userId): void;

    /** Delete every viewer row on the conversation, except the given user's. */
    public function deleteViewers(int $conversationId, ?int $exceptUserId = null): int;

    /** Move the read pointer forward only. Returns whether it moved. */
    public function moveReadPointer(int $conversationId, int $userId, int $messageId): bool;

    /** Move the delivered pointer forward only. Returns whether it moved. */
    public function moveDeliveredPointer(int $conversationId, int $userId, int $messageId): bool;

    /**
     * Members who read and send (not pool viewers), with their user.
     *
     * @return Collection<int, ConversationMember>
     */
    public function activeMembers(int $conversationId): Collection;
}
