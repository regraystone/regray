<?php

namespace App\Services\Message;

use App\Enums\ConversationMemberRole;
use App\Enums\ConversationType;
use App\Enums\MessageType;
use App\Enums\Permission;
use App\Models\Conversation;
use App\Models\ConversationMember;
use App\Models\User;
use App\Repositories\Eloquent\EloquentConversationRepository;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Which unread messages are due an email, and where those emails have got to.
 *
 * Two kinds of reminder:
 * - a member who has not read a text message someone else sent them, once the
 *   oldest of those messages has waited past the cutoff;
 * - the support pool, when a client has written in a Regray Team thread that
 *   nobody has claimed and the oldest of those messages has waited past it.
 *
 * Only people's text messages count. Cards and system lines announce things
 * the reader can see for themselves and never prompt an email. A message is
 * emailed about once: the markers below move past it as soon as it is.
 *
 * @phpstan-type DueThread array{conversationId: int, name: string, count: int, latestId: int}
 */
class MessageReminderService
{
    /**
     * Members with unread messages due a reminder, grouped by member.
     *
     * @return list<array{user: User, threads: list<DueThread>}>
     */
    public function dueForMembers(CarbonInterface $cutoff): array
    {
        $rows = DB::table('conversation_members as cm')
            ->join('messages as m', function ($join): void {
                $join->on('m.conversation_id', '=', 'cm.conversation_id')
                    ->whereRaw('m.id > coalesce(cm.last_read_message_id, 0)')
                    ->whereRaw('m.id > coalesce(cm.last_notified_message_id, 0)')
                    ->whereColumn('m.sender_id', '<>', 'cm.user_id');
            })
            ->join('users as u', 'u.id', '=', 'cm.user_id')
            ->whereIn('cm.role', [ConversationMemberRole::Member->value, ConversationMemberRole::Admin->value])
            ->whereNotNull('u.email_verified_at')
            ->where('m.type', MessageType::Text->value)
            ->whereNotNull('m.sender_id')
            ->whereNull('m.deleted_at')
            ->groupBy('cm.conversation_id', 'cm.user_id')
            ->havingRaw('min(m.created_at) <= ?', [$cutoff])
            ->get([
                'cm.conversation_id',
                'cm.user_id',
                DB::raw('max(m.id) as latest_id'),
                DB::raw('count(m.id) as unread'),
            ]);

        if ($rows->isEmpty()) {
            return [];
        }

        $users = User::query()->whereKey($this->ids($rows, 'user_id'))->get()->keyBy('id');
        $conversations = $this->conversations($this->ids($rows, 'conversation_id'));
        $due = [];

        foreach ($rows->groupBy('user_id') as $userId => $threads) {
            $user = $users->get($userId);

            if ($user === null) {
                continue;
            }

            $list = [];

            foreach ($threads as $row) {
                $conversation = $conversations->get($row->conversation_id);

                if ($conversation === null) {
                    continue;
                }

                $list[] = [
                    'conversationId' => (int) $row->conversation_id,
                    'name' => $this->nameFor($conversation, $user),
                    'count' => (int) $row->unread,
                    'latestId' => (int) $row->latest_id,
                ];
            }

            $due[] = ['user' => $user, 'threads' => $list];
        }

        return $due;
    }

    /**
     * Unclaimed Regray Team threads whose client has been waiting past the
     * cutoff, with the client's name.
     *
     * @return list<DueThread>
     */
    public function dueForPool(CarbonInterface $cutoff): array
    {
        $rows = DB::table('conversations as c')
            ->join('messages as m', function ($join): void {
                $join->on('m.conversation_id', '=', 'c.id')
                    ->whereColumn('m.sender_id', 'c.support_client_id')
                    ->whereRaw('m.id > coalesce(c.pool_notified_message_id, 0)');
            })
            ->join('users as u', 'u.id', '=', 'c.support_client_id')
            ->where('c.type', ConversationType::Support->value)
            ->whereNull('c.assigned_to')
            ->where('m.type', MessageType::Text->value)
            ->whereNull('m.deleted_at')
            ->groupBy('c.id', 'u.name')
            ->havingRaw('min(m.created_at) <= ?', [$cutoff])
            ->orderBy('c.id')
            ->get([
                'c.id',
                'u.name',
                DB::raw('max(m.id) as latest_id'),
                DB::raw('count(m.id) as waiting'),
            ]);

        $due = [];

        foreach ($rows as $row) {
            $due[] = [
                'conversationId' => (int) $row->id,
                'name' => (string) $row->name,
                'count' => (int) $row->waiting,
                'latestId' => (int) $row->latest_id,
            ];
        }

        return $due;
    }

    /**
     * Everyone who answers Regray Team threads: staff who may claim them,
     * with a verified address.
     *
     * @return EloquentCollection<int, User>
     */
    public function poolMembers(): EloquentCollection
    {
        return User::permission(Permission::SupportClaim->value)
            ->whereNotNull('email_verified_at')
            ->get()
            ->reject(fn (User $user): bool => $user->isClient())
            ->values();
    }

    /**
     * Note that `$userId` has been emailed about every message in the
     * conversation up to `$messageId`. Never moves the marker back.
     */
    public function markMemberNotified(int $conversationId, int $userId, int $messageId): void
    {
        ConversationMember::query()
            ->where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->where(fn ($query) => $query->whereNull('last_notified_message_id')->orWhere('last_notified_message_id', '<', $messageId))
            ->update(['last_notified_message_id' => $messageId]);
    }

    /** Note that the pool has been emailed about the thread up to `$messageId`. */
    public function markPoolNotified(int $conversationId, int $messageId): void
    {
        Conversation::query()
            ->whereKey($conversationId)
            ->where(fn ($query) => $query->whereNull('pool_notified_message_id')->orWhere('pool_notified_message_id', '<', $messageId))
            ->update(['pool_notified_message_id' => $messageId]);
    }

    /**
     * The distinct ids in one column of the rows.
     *
     * @param  Collection<int, \stdClass>  $rows
     * @return list<int>
     */
    private function ids(Collection $rows, string $column): array
    {
        $ids = [];

        foreach ($rows as $row) {
            $ids[(int) $row->{$column}] = true;
        }

        return array_keys($ids);
    }

    /**
     * @param  list<int>  $ids
     * @return EloquentCollection<int, Conversation>
     */
    private function conversations(array $ids): EloquentCollection
    {
        return Conversation::query()
            ->whereKey($ids)
            ->with(['supportClient:id,name', 'members.user:id,name'])
            ->get()
            ->keyBy('id');
    }

    /**
     * The conversation as `$user` sees it in their list: a client's own
     * Regray Team thread is "Regray", staff see it under the client's
     * name, a direct chat is the other person.
     */
    private function nameFor(Conversation $conversation, User $user): string
    {
        if ($conversation->isSupport()) {
            return $conversation->support_client_id === $user->id
                ? EloquentConversationRepository::SUPPORT_NAME
                : ($conversation->supportClient->name ?? EloquentConversationRepository::SUPPORT_NAME);
        }

        if ($conversation->type === ConversationType::Direct) {
            $other = $conversation->members->first(fn (ConversationMember $member): bool => $member->user_id !== $user->id);

            return $other?->user->name ?? 'Direct message';
        }

        return (string) $conversation->name;
    }
}
