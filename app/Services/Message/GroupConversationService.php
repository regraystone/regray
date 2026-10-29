<?php

namespace App\Services\Message;

use App\Enums\ConversationMemberRole;
use App\Enums\Permission;
use App\Enums\Role;
use App\Events\Message\GroupCreated;
use App\Models\Conversation;
use App\Models\Inquiry;
use App\Models\User;
use App\Repositories\Contracts\ConversationMemberRepositoryInterface;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Group chats started from the Message page or the Leads page: a name, one
 * client, one of that client's inquiries the group is bound to, and staff. The
 * creator is the group's admin, and every owner is always added.
 *
 * A group bound to an inquiry is that inquiry's group chat: its status cards
 * post there (SupportConversationService::cardThreadFor()), and an inquiry has
 * at most one.
 *
 * Who may be picked as staff depends on who creates it. Marketing picks from
 * sales and technical only; anyone else with `conversations.create` picks from
 * every staff member who may use Message. Later, a sales member of the group
 * may add accounting and logistics users, and nobody else (addMembers()).
 */
class GroupConversationService
{
    public const PEOPLE_LIMIT = 20;

    /** The roles marketing may put in a new group. */
    public const MARKETING_STAFF_ROLES = [Role::Sales, Role::Technical];

    /** The roles a sales member may add to a group later. */
    public const LATER_STAFF_ROLES = [Role::Accounting, Role::Logistics];

    public function __construct(
        public readonly ConversationRepositoryInterface $conversations,
        public readonly ConversationMemberRepositoryInterface $members,
    ) {}

    /**
     * @param  list<int>  $staffIds
     *
     * @throws ValidationException When a person, the client or the inquiry may not be chosen.
     */
    public function create(User $creator, string $name, array $staffIds, int $clientId, int $inquiryId): Conversation
    {
        $staff = $this->staffQuery($creator)->whereIn('id', $staffIds)->get();
        $client = $this->clientQuery()->whereKey($clientId)->first();

        if ($staff->count() !== count(array_unique($staffIds))) {
            throw ValidationException::withMessages(['staffIds' => $this->isMarketing($creator)
                ? 'Choose staff from sales and technical.'
                : 'Choose staff members who can use Message.']);
        }

        if ($client === null) {
            throw ValidationException::withMessages(['clientId' => 'Choose one client.']);
        }

        $inquiry = Inquiry::query()->whereKey($inquiryId)->where('client_id', $client->id)->first();

        if ($inquiry === null) {
            throw ValidationException::withMessages(['inquiryId' => "Choose one of {$client->name}'s inquiries."]);
        }

        if ($this->conversations->groupForInquiry($inquiry->id) !== null) {
            throw ValidationException::withMessages(['inquiryId' => "{$inquiry->reference} already has a group chat."]);
        }

        try {
            // A savepoint, so a lost race on the inquiry's one group does not abort the caller's transaction.
            $conversation = DB::transaction(fn (): Conversation => $this->conversations->createGroup([
                'name' => $name,
                'created_by' => $creator->id,
                'inquiry_id' => $inquiry->id,
            ]));
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['inquiryId' => "{$inquiry->reference} already has a group chat."]);
        }

        foreach ([...$staff, ...$this->owners()] as $member) {
            $this->members->upsert($conversation->id, $member->id, ConversationMemberRole::Member);
        }

        $this->members->upsert($conversation->id, $client->id, ConversationMemberRole::Member);
        $this->members->upsert($conversation->id, $creator->id, ConversationMemberRole::Admin);

        event(new GroupCreated($conversation->id, $this->conversations->memberUserIds($conversation->id)));

        return $conversation;
    }

    /**
     * Add accounting and logistics users to an inquiry's group. Whether the
     * adder may (a sales member of the group) is ConversationPolicy::addMembers;
     * this checks who is being added. Somebody already in the group stays as
     * they are.
     *
     * @param  list<int>  $userIds
     *
     * @throws ValidationException When anyone is not accounting or logistics.
     */
    public function addMembers(Conversation $conversation, array $userIds): void
    {
        $users = $this->laterStaffQuery()->whereIn('id', $userIds)->get();

        if ($users->count() !== count(array_unique($userIds))) {
            throw ValidationException::withMessages(['userIds' => 'Choose people from accounting and logistics.']);
        }

        $newIds = [];

        foreach ($users as $user) {
            if (! $this->members->isActiveMember($conversation->id, $user->id)) {
                $this->members->upsert($conversation->id, $user->id, ConversationMemberRole::Member);
                $newIds[] = $user->id;
            }
        }

        if ($newIds !== []) {
            event(new GroupCreated($conversation->id, $newIds));
        }
    }

    /**
     * Accounting and logistics users who are not in the group yet, matching a
     * search: who a sales member may add.
     *
     * @return Collection<int, User>
     */
    public function addableTo(Conversation $conversation, ?string $search): Collection
    {
        return $this->matching($this->laterStaffQuery(), $search === null ? '' : trim($search))
            ->whereDoesntHave('conversations', fn (Builder $member) => $member
                ->where('conversations.id', $conversation->id)
                ->where('conversation_members.role', '!=', ConversationMemberRole::Viewer->value))
            ->get(['id', 'name']);
    }

    /**
     * Who a group can be made with, matching a search: the staff `$creator`
     * may pick, the clients, and the owners every group gets. With a client,
     * that client's inquiries too, each saying whether it has a group already.
     *
     * @return array{staff: Collection<int, User>, clients: Collection<int, User>, owners: Collection<int, User>, inquiries: list<array{id: int, reference: string, name: string, hasGroup: bool}>}
     */
    public function people(User $creator, ?string $search, ?int $clientId = null): array
    {
        $search = $search === null ? '' : trim($search);

        return [
            'staff' => $this->matching($this->staffQuery($creator), $search)->get(['id', 'name']),
            'clients' => $this->matching($this->clientQuery(), $search)->get(['id', 'name']),
            'owners' => $this->owners(),
            'inquiries' => $clientId === null ? [] : $this->inquiriesOf($clientId),
        ];
    }

    /** @return list<array{id: int, reference: string, name: string, hasGroup: bool}> */
    private function inquiriesOf(int $clientId): array
    {
        return array_values(Inquiry::query()
            ->where('client_id', $clientId)
            ->withExists('conversation as has_group')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get(['id', 'reference', 'name'])
            ->map(fn (Inquiry $inquiry): array => [
                'id' => $inquiry->id,
                'reference' => $inquiry->reference,
                'name' => $inquiry->name,
                'hasGroup' => (bool) $inquiry->getAttribute('has_group'),
            ])
            ->all());
    }

    /**
     * Every user with the owner role, who is put in every new group.
     *
     * @return Collection<int, User>
     */
    private function owners(): Collection
    {
        return User::query()
            ->whereHas('roles', fn (Builder $role) => $role->where('name', Role::Owner->value))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function isMarketing(User $user): bool
    {
        return $user->hasRole(Role::Marketing->value);
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
     * Who `$creator` may pick as staff: sales and technical for marketing;
     * otherwise every non-client who may use the Message page. Owners are
     * added anyway, so they are never picked.
     *
     * @return Builder<User>
     */
    private function staffQuery(User $creator): Builder
    {
        if ($this->isMarketing($creator)) {
            return $this->withRoles(self::MARKETING_STAFF_ROLES);
        }

        return User::query()
            ->whereDoesntHave('roles', fn (Builder $role) => $role->whereIn('name', [Role::Client->value, Role::Owner->value]))
            ->where(fn (Builder $query) => $query
                ->whereHas('roles.permissions', fn (Builder $permission) => $permission->where('name', Permission::MessagesView->value))
                ->orWhereHas('roles', fn (Builder $role) => $role->where('name', Role::superAdmin()->value)));
    }

    /** @return Builder<User> */
    private function laterStaffQuery(): Builder
    {
        return $this->withRoles(self::LATER_STAFF_ROLES);
    }

    /**
     * Users holding one of `$roles` and not the client role.
     *
     * @param  list<Role>  $roles
     * @return Builder<User>
     */
    private function withRoles(array $roles): Builder
    {
        return User::query()
            ->whereHas('roles', fn (Builder $role) => $role->whereIn('name', array_map(fn (Role $role): string => $role->value, $roles)))
            ->whereDoesntHave('roles', fn (Builder $role) => $role->where('name', Role::Client->value));
    }

    /** @return Builder<User> */
    private function clientQuery(): Builder
    {
        return User::query()->whereHas('roles', fn (Builder $role) => $role->where('name', Role::Client->value));
    }
}
