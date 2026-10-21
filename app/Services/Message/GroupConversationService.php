<?php

namespace App\Services\Message;

use App\Enums\ConversationMemberRole;
use App\Enums\Permission;
use App\Enums\Role;
use App\Events\Message\GroupCreated;
use App\Models\Conversation;
use App\Models\User;
use App\Repositories\Contracts\ConversationMemberRepositoryInterface;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Group chats started from the Message page: a name, some staff and exactly one
 * client. The creator is the group's admin.
 *
 * TODO(inquiry-binding): groups will be bound to inquiries later. Until then no
 * group is linked to an inquiry.
 */
class GroupConversationService
{
    public const PEOPLE_LIMIT = 20;

    public function __construct(
        public readonly ConversationRepositoryInterface $conversations,
        public readonly ConversationMemberRepositoryInterface $members,
    ) {}

    /**
     * @param  list<int>  $staffIds
     */
    public function create(User $creator, string $name, array $staffIds, int $clientId): Conversation
    {
        $staff = $this->staffQuery()->whereIn('id', $staffIds)->get();
        $client = $this->clientQuery()->whereKey($clientId)->first();

        if ($staff->count() !== count(array_unique($staffIds))) {
            throw ValidationException::withMessages(['staffIds' => 'Choose staff members who can use Message.']);
        }

        if ($client === null) {
            throw ValidationException::withMessages(['clientId' => 'Choose one client.']);
        }

        $conversation = $this->conversations->createGroup(['name' => $name, 'created_by' => $creator->id]);

        foreach ($staff as $member) {
            $this->members->upsert($conversation->id, $member->id, ConversationMemberRole::Member);
        }

        $this->members->upsert($conversation->id, $client->id, ConversationMemberRole::Member);
        $this->members->upsert($conversation->id, $creator->id, ConversationMemberRole::Admin);

        event(new GroupCreated($conversation->id, $this->conversations->memberUserIds($conversation->id)));

        return $conversation;
    }

    /**
     * Staff and clients a group can be made with, matching a search.
     *
     * @return array{staff: Collection<int, User>, clients: Collection<int, User>}
     */
    public function people(?string $search): array
    {
        $search = $search === null ? '' : trim($search);

        return [
            'staff' => $this->matching($this->staffQuery(), $search)->get(['id', 'name']),
            'clients' => $this->matching($this->clientQuery(), $search)->get(['id', 'name']),
        ];
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    private function matching(Builder $query, string $search): Builder
    {
        $pattern = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $search).'%';

        return $query
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('name', 'ILIKE', $pattern)
                ->orWhere('email', 'ILIKE', $pattern)))
            ->orderBy('name')
            ->limit(self::PEOPLE_LIMIT);
    }

    /**
     * Non-client users who may use the Message page.
     *
     * @return Builder<User>
     */
    private function staffQuery(): Builder
    {
        return User::query()
            ->whereDoesntHave('roles', fn (Builder $role) => $role->where('name', Role::Client->value))
            ->where(fn (Builder $query) => $query
                ->whereHas('roles.permissions', fn (Builder $permission) => $permission->where('name', Permission::MessagesView->value))
                ->orWhereHas('roles', fn (Builder $role) => $role->where('name', Role::superAdmin()->value)));
    }

    /** @return Builder<User> */
    private function clientQuery(): Builder
    {
        return User::query()->whereHas('roles', fn (Builder $role) => $role->where('name', Role::Client->value));
    }
}
