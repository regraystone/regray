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
use App\Repositories\Contracts\InquiryRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Group chats started from the Inbox page or the Leads page: a name, one
 * client, one of that client's inquiries the group is bound to, and its staff.
 * The creator is the group's admin. Every group is bound to an inquiry when it
 * is made.
 *
 * A group bound to an inquiry is that inquiry's group chat: its status cards
 * post there (SupportConversationService::cardThreadFor()), and an inquiry has
 * at most one.
 *
 * Being in the group is what assigns staff to the inquiry: every staff member
 * put in it is written to `inquiry_handlers` with them, in the same
 * transaction (assign()), so the two never disagree. A group takes exactly one
 * sales and one technical member when it is made, picked from those two roles
 * by whoever creates it; later, a sales member of it adds exactly one
 * accounting and one logistics member, together (addMembers()). Owners are
 * never members and never assigned: they read every group through
 * ConversationPolicy, and write in none. Marketing is not assigned either: it
 * reads the inquiry through its lead.
 */
class GroupConversationService
{
    public const PEOPLE_LIMIT = 20;

    /** The roles a new group takes, one member each. */
    public const GROUP_STAFF_ROLES = [Role::Sales, Role::Technical];

    /** The roles a sales member adds to a group later, one member each. */
    public const LATER_STAFF_ROLES = [Role::Accounting, Role::Logistics];

    public function __construct(
        public readonly ConversationRepositoryInterface $conversations,
        public readonly ConversationMemberRepositoryInterface $members,
        public readonly InquiryRepositoryInterface $inquiries,
    ) {}

    /**
     * Has to run inside a transaction: the group, its members and the
     * inquiry's handlers are written together.
     *
     * @param  list<int>  $staffIds  Exactly one sales and one technical user.
     *
     * @throws ValidationException When the staff, the client or the inquiry may not be chosen.
     */
    public function create(User $creator, string $name, array $staffIds, int $clientId, int $inquiryId): Conversation
    {
        $staff = $this->groupStaff($staffIds);
        $client = $this->clientQuery()->whereKey($clientId)->first();

        if ($client === null) {
            throw ValidationException::withMessages(['clientId' => 'Choose one client.']);
        }

        $inquiry = Inquiry::query()->visibleTo($creator)->whereKey($inquiryId)->where('client_id', $client->id)->first();

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

        $this->assign($conversation, $inquiry, $staff, $creator);
        $this->members->upsert($conversation->id, $client->id, ConversationMemberRole::Member);
        $this->members->upsert($conversation->id, $creator->id, ConversationMemberRole::Admin);

        event(new GroupCreated($conversation->id, $this->conversations->memberUserIds($conversation->id)));

        return $conversation;
    }

    /**
     * A sales member adds one accounting and one logistics user to an
     * inquiry's group, together, and so assigns them to its inquiry. Whether
     * the adder may (a sales member of the group who reads its inquiry) is
     * ConversationPolicy::addMembers; this checks who is being added, and
     * refuses a group whose accounting or logistics place is already taken.
     *
     * Has to run inside a transaction: the group's row is locked until it ends,
     * so two adds at once cannot both fill a place.
     *
     * @throws ValidationException When either person may not be added, or the group has one already.
     */
    public function addMembers(Conversation $conversation, User $by, int $accountingId, int $logisticsId): void
    {
        $conversation = $this->conversations->lockForUpdate($conversation->id);

        if ($accountingId === $logisticsId) {
            throw ValidationException::withMessages(['logisticsId' => 'Choose someone else from logistics: one person cannot take both places.']);
        }

        $accounting = $this->withRoles([Role::Accounting])->whereKey($accountingId)->first();
        $logistics = $this->withRoles([Role::Logistics])->whereKey($logisticsId)->first();

        if ($accounting === null) {
            throw ValidationException::withMessages(['accountingId' => 'Choose one person from accounting.']);
        }

        if ($logistics === null) {
            throw ValidationException::withMessages(['logisticsId' => 'Choose one person from logistics.']);
        }

        if ($this->hasMemberWithRole($conversation, Role::Accounting)) {
            throw ValidationException::withMessages(['accountingId' => 'This group already has someone from accounting.']);
        }

        if ($this->hasMemberWithRole($conversation, Role::Logistics)) {
            throw ValidationException::withMessages(['logisticsId' => 'This group already has someone from logistics.']);
        }

        $inquiry = $conversation->inquiry ?? throw ValidationException::withMessages(['accountingId' => 'This group is not bound to an inquiry.']);

        $this->assign($conversation, $inquiry, new Collection([$accounting, $logistics]), $by);

        event(new GroupCreated($conversation->id, [$accounting->id, $logistics->id]));
    }

    /**
     * Who a sales member may add, matching a search: accounting and logistics
     * users not in the group yet, each with their active inquiry count. A role
     * whose place in the group is taken offers nobody.
     *
     * @return array{accounting: Collection<int, User>, logistics: Collection<int, User>}
     */
    public function addableTo(Conversation $conversation, ?string $search): array
    {
        $search = $search === null ? '' : trim($search);
        $addable = fn (Role $role): Collection => $this->hasMemberWithRole($conversation, $role)
            ? new Collection
            : $this->pickable($this->withRoles([$role]), $search)->get();

        return [
            'accounting' => $addable(Role::Accounting),
            'logistics' => $addable(Role::Logistics),
        ];
    }

    /**
     * Who a group can be made with, matching a search: the sales and technical
     * staff, each with their active inquiry count, and the clients. With a
     * client, that client's inquiries `$creator` reads too, each saying
     * whether it has a group already.
     *
     * `staff` is not for a group: it is the super-admin's Reassign dialog's
     * list (staffQuery()).
     *
     * @return array{staff: Collection<int, User>, sales: Collection<int, User>, technical: Collection<int, User>, clients: Collection<int, User>, inquiries: list<array{id: int, reference: string, name: string, hasGroup: bool}>}
     */
    public function people(User $creator, ?string $search, ?int $clientId = null): array
    {
        $search = $search === null ? '' : trim($search);

        return [
            'staff' => $this->matching($this->staffQuery($creator), $search)->get(['id', 'name']),
            'sales' => $this->pickable($this->withRoles([Role::Sales]), $search)->get(),
            'technical' => $this->pickable($this->withRoles([Role::Technical]), $search)->get(),
            'clients' => $this->matching($this->clientQuery(), $search)->get(['id', 'name']),
            'inquiries' => $clientId === null ? [] : $this->inquiriesOf($creator, $clientId),
        ];
    }

    /**
     * Put staff in the inquiry's group and assign them to the inquiry, by
     * `$by`, in one transaction: the membership rows and the `inquiry_handlers`
     * rows are only ever written together, here, so they stay in step.
     *
     * @param  Collection<int, User>  $staff
     */
    private function assign(Conversation $conversation, Inquiry $inquiry, Collection $staff, User $by): void
    {
        DB::transaction(function () use ($conversation, $inquiry, $staff, $by): void {
            foreach ($staff as $member) {
                $this->members->upsert($conversation->id, $member->id, ConversationMemberRole::Member);
            }

            $this->inquiries->addHandlers($inquiry, array_values($staff->map(fn (User $member): int => $member->id)->all()), $by->id, Date::now());
        });
    }

    /**
     * The new group's staff: exactly one sales and one technical user.
     *
     * @param  list<int>  $staffIds
     * @return Collection<int, User>
     *
     * @throws ValidationException
     */
    private function groupStaff(array $staffIds): Collection
    {
        $staff = $this->withRoles(self::GROUP_STAFF_ROLES)->whereIn('id', $staffIds)->with('roles')->get();
        $count = fn (Role $role): int => $staff->filter(fn (User $user): bool => $user->hasRole($role->value))->count();

        if (count(array_unique($staffIds)) !== 2 || $staff->count() !== 2 || $count(Role::Sales) !== 1 || $count(Role::Technical) !== 1) {
            throw ValidationException::withMessages(['staffIds' => 'Choose one sales member and one technical member.']);
        }

        return $staff;
    }

    /** Whether the group has a member, not a viewer, holding `$role`. */
    private function hasMemberWithRole(Conversation $conversation, Role $role): bool
    {
        return $this->withRoles([$role])
            ->whereHas('conversations', fn (Builder $member) => $member
                ->where('conversations.id', $conversation->id)
                ->where('conversation_members.role', '!=', ConversationMemberRole::Viewer->value))
            ->exists();
    }

    /** @return list<array{id: int, reference: string, name: string, hasGroup: bool}> */
    private function inquiriesOf(User $creator, int $clientId): array
    {
        return array_values(Inquiry::query()
            ->visibleTo($creator)
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
     * Staff matching a search, as a picker lists them: id, name and
     * `active_inquiry_count` (User::scopeWithActiveInquiryCount()).
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    private function pickable(Builder $query, string $search): Builder
    {
        return $this->matching($query, $search)
            ->select(['users.id', 'users.name'])
            ->withActiveInquiryCount();
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

    /**
     * The staff the Reassign dialog offers the super-admin: every non-client
     * who may use the Inbox page, owners aside; for marketing, sales and
     * technical. Who may take a Regray Team thread is the reassignment's to
     * check.
     *
     * @return Builder<User>
     */
    private function staffQuery(User $creator): Builder
    {
        if ($creator->hasRole(Role::Marketing->value)) {
            return $this->withRoles(self::GROUP_STAFF_ROLES);
        }

        return User::query()
            ->whereDoesntHave('roles', fn (Builder $role) => $role->whereIn('name', [Role::Client->value, Role::Owner->value]))
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
