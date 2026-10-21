<?php

namespace App\Repositories\Eloquent;

use App\Enums\ConversationMemberRole;
use App\Models\ConversationMember;
use App\Repositories\Contracts\ConversationMemberRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Date;

class EloquentConversationMemberRepository implements ConversationMemberRepositoryInterface
{
    public function find(int $conversationId, int $userId): ?ConversationMember
    {
        return $this->row($conversationId, $userId)->first();
    }

    public function isActiveMember(int $conversationId, int $userId): bool
    {
        return $this->row($conversationId, $userId)
            ->whereIn('role', [ConversationMemberRole::Member->value, ConversationMemberRole::Admin->value])
            ->exists();
    }

    public function upsert(int $conversationId, int $userId, ConversationMemberRole $role): void
    {
        ConversationMember::query()->upsert(
            [[
                'conversation_id' => $conversationId,
                'user_id' => $userId,
                'role' => $role->value,
                'joined_at' => Date::now(),
            ]],
            ['conversation_id', 'user_id'],
            ['role'],
        );
    }

    public function ensureViewer(int $conversationId, int $userId): void
    {
        ConversationMember::query()->insertOrIgnore([
            'conversation_id' => $conversationId,
            'user_id' => $userId,
            'role' => ConversationMemberRole::Viewer->value,
            'joined_at' => Date::now(),
        ]);
    }

    public function setRole(int $conversationId, int $userId, ConversationMemberRole $role): void
    {
        $this->row($conversationId, $userId)->update(['role' => $role->value]);
    }

    public function delete(int $conversationId, int $userId): void
    {
        $this->row($conversationId, $userId)->delete();
    }

    public function deleteViewers(int $conversationId, ?int $exceptUserId = null): int
    {
        return ConversationMember::query()
            ->where('conversation_id', $conversationId)
            ->where('role', ConversationMemberRole::Viewer->value)
            ->when($exceptUserId !== null, fn (Builder $query) => $query->where('user_id', '!=', $exceptUserId))
            ->delete();
    }

    public function moveReadPointer(int $conversationId, int $userId, int $messageId): bool
    {
        return $this->movePointer('last_read_message_id', $conversationId, $userId, $messageId);
    }

    public function moveDeliveredPointer(int $conversationId, int $userId, int $messageId): bool
    {
        return $this->movePointer('last_delivered_message_id', $conversationId, $userId, $messageId);
    }

    public function activeMembers(int $conversationId): Collection
    {
        return ConversationMember::query()
            ->where('conversation_id', $conversationId)
            ->whereIn('role', [ConversationMemberRole::Member->value, ConversationMemberRole::Admin->value])
            ->with('user:id,name')
            ->get();
    }

    /**
     * A pointer only ever moves forward: the condition is part of the update,
     * so two requests arriving out of order cannot move it back.
     */
    private function movePointer(string $column, int $conversationId, int $userId, int $messageId): bool
    {
        return $this->row($conversationId, $userId)
            ->where(fn (Builder $query) => $query->whereNull($column)->orWhere($column, '<', $messageId))
            ->update([$column => $messageId]) > 0;
    }

    /** @return Builder<ConversationMember> */
    private function row(int $conversationId, int $userId): Builder
    {
        return ConversationMember::query()
            ->where('conversation_id', $conversationId)
            ->where('user_id', $userId);
    }
}
