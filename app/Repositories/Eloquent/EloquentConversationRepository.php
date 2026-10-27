<?php

namespace App\Repositories\Eloquent;

use App\Data\Message\ConversationListFilters;
use App\Enums\ConversationMemberRole;
use App\Enums\ConversationType;
use App\Models\Conversation;
use App\Models\ConversationMember;
use App\Models\Message;
use App\Models\User;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Date;

class EloquentConversationRepository implements ConversationRepositoryInterface
{
    public const SUPPORT_NAME = 'Regray Team';

    public const SUPPORT_AVATAR = '/images/regray-logo.svg';

    /**
     * Messages from others the user has not read. System notices and removed
     * messages do not count. Two bindings: the user id, twice.
     */
    private const UNREAD_SQL = 'SELECT COUNT(*) FROM messages um'
        .' WHERE um.conversation_id = conversations.id'
        .' AND um.deleted_at IS NULL'
        ." AND um.type <> 'system'"
        .' AND (um.sender_id IS NULL OR um.sender_id <> ?)'
        .' AND um.id > COALESCE((SELECT ucm.last_read_message_id FROM conversation_members ucm WHERE ucm.conversation_id = conversations.id AND ucm.user_id = ?), 0)';

    public function paginateForUser(
        User $user,
        ConversationListFilters $filters,
        ?string $escapedSearch,
        bool $includeSupportPool,
        bool $includeAllSupport,
        int $perPage,
    ): LengthAwarePaginator {
        return $this->listQuery($user, $includeSupportPool, $includeAllSupport)
            ->when($filters->onlyUnread(), fn (Builder $query) => $query->whereRaw(
                '('.self::UNREAD_SQL.') > 0',
                [$user->id, $user->id],
            ))
            ->when($escapedSearch !== null, function (Builder $query) use ($escapedSearch, $user): void {
                $pattern = "%{$escapedSearch}%";

                $query->where(function (Builder $query) use ($pattern, $user): void {
                    $query->where('conversations.name', 'ILIKE', $pattern)
                        ->orWhereExists(fn (QueryBuilder $members) => $members
                            ->selectRaw('1')
                            ->from('conversation_members as scm')
                            ->join('users as su', 'su.id', '=', 'scm.user_id')
                            ->whereColumn('scm.conversation_id', 'conversations.id')
                            ->where('scm.user_id', '!=', $user->id)
                            ->where('scm.role', '!=', ConversationMemberRole::Viewer->value)
                            ->where('su.name', 'ILIKE', $pattern))
                        ->orWhereExists(fn (QueryBuilder $client) => $client
                            ->selectRaw('1')
                            ->from('users as sc')
                            ->whereColumn('sc.id', 'conversations.support_client_id')
                            ->where('sc.name', 'ILIKE', $pattern));
                });
            })
            ->orderByRaw('COALESCE(conversations.support_client_id = ?, false) DESC', [$user->id])
            ->orderByDesc('last_activity_at')
            ->orderByDesc('conversations.id')
            ->paginate($perPage, page: $filters->page);
    }

    public function findSummaryForUser(User $user, int $conversationId): ?Conversation
    {
        return $this->listQuery($user, includeSupportPool: true, includeAllSupport: true, restrict: false)
            ->where('conversations.id', $conversationId)
            ->first();
    }

    public function previewMessages(array $messageIds): Collection
    {
        if ($messageIds === []) {
            return new Collection;
        }

        return Message::query()
            ->withTrashed()
            ->whereIn('id', $messageIds)
            ->with(['sender:id,name', 'attachments:id,message_id,mime_type', 'systemUser:id,name', 'inquiry:id,reference', 'inquiryProject:id,name', 'schedule:id,title,booked_by_type'])
            ->get();
    }

    public function find(int $id): ?Conversation
    {
        return Conversation::query()->find($id);
    }

    public function findSupportFor(User $client): ?Conversation
    {
        return Conversation::query()
            ->where('type', ConversationType::Support->value)
            ->where('support_client_id', $client->id)
            ->first();
    }

    public function createSupportFor(User $client): Conversation
    {
        return Conversation::query()->create([
            'type' => ConversationType::Support,
            'name' => self::SUPPORT_NAME,
            'avatar_path' => self::SUPPORT_AVATAR,
            'support_client_id' => $client->id,
            'created_by' => $client->id,
        ]);
    }

    public function lockForUpdate(int $id): Conversation
    {
        return Conversation::query()->whereKey($id)->lockForUpdate()->firstOrFail();
    }

    public function assignIfUnassigned(int $id, int $userId, CarbonInterface $at): bool
    {
        return Conversation::query()
            ->whereKey($id)
            ->where('type', ConversationType::Support->value)
            ->whereNull('assigned_to')
            ->update(['assigned_to' => $userId, 'assigned_at' => $at, 'updated_at' => $at]) === 1;
    }

    public function setAssignee(int $id, ?int $userId, ?CarbonInterface $at): void
    {
        Conversation::query()
            ->whereKey($id)
            ->update(['assigned_to' => $userId, 'assigned_at' => $userId === null ? null : $at, 'updated_at' => Date::now()]);
    }

    public function createGroup(array $attributes): Conversation
    {
        return Conversation::query()->create([
            'type' => ConversationType::Group,
            'name' => $attributes['name'],
            'created_by' => $attributes['created_by'],
        ]);
    }

    public function touch(int $id): void
    {
        Conversation::query()->whereKey($id)->update(['updated_at' => Date::now()]);
    }

    public function groupForInquiry(int $inquiryId): ?Conversation
    {
        return Conversation::query()
            ->where('type', ConversationType::Group->value)
            ->where('inquiry_id', $inquiryId)
            ->first();
    }

    public function memberUserIds(int $conversationId): array
    {
        return array_values(ConversationMember::query()
            ->where('conversation_id', $conversationId)
            ->whereIn('role', [ConversationMemberRole::Member->value, ConversationMemberRole::Admin->value])
            ->pluck('user_id')
            ->map(fn ($id): int => (int) $id)
            ->all());
    }

    /**
     * The conversations a user may see, with the columns a list row needs.
     *
     * `$restrict` false skips the visibility clause, for looking up one row the
     * policy has already admitted.
     *
     * @return Builder<Conversation>
     */
    private function listQuery(User $user, bool $includeSupportPool, bool $includeAllSupport, bool $restrict = true): Builder
    {
        return Conversation::query()
            ->select('conversations.*')
            ->selectRaw('(SELECT MAX(m.id) FROM messages m WHERE m.conversation_id = conversations.id) AS last_message_id')
            ->selectRaw('COALESCE((SELECT MAX(m.created_at) FROM messages m WHERE m.conversation_id = conversations.id), conversations.created_at) AS last_activity_at')
            ->selectRaw('('.self::UNREAD_SQL.') AS unread_count', [$user->id, $user->id])
            ->with([
                'supportClient:id,name',
                'assignee:id,name',
                'members' => fn ($members) => $members
                    ->whereIn('role', [ConversationMemberRole::Member->value, ConversationMemberRole::Admin->value])
                    ->with('user:id,name'),
            ])
            ->when($restrict, fn (Builder $query) => $query->where(function (Builder $query) use ($user, $includeSupportPool, $includeAllSupport): void {
                $query->whereExists(fn (QueryBuilder $member) => $member
                    ->selectRaw('1')
                    ->from('conversation_members as vcm')
                    ->whereColumn('vcm.conversation_id', 'conversations.id')
                    ->where('vcm.user_id', $user->id)
                    ->whereIn('vcm.role', [ConversationMemberRole::Member->value, ConversationMemberRole::Admin->value]));

                if ($includeAllSupport) {
                    $query->orWhere('conversations.type', ConversationType::Support->value);
                } elseif ($includeSupportPool) {
                    $query->orWhere(fn (Builder $pool) => $pool
                        ->where('conversations.type', ConversationType::Support->value)
                        ->whereNull('conversations.assigned_to'));
                }
            }));
    }
}
