<?php

namespace App\Services\Message;

use App\Enums\ContactKind;
use App\Enums\ConversationMemberRole;
use App\Enums\Role;
use App\Enums\SalesStatus;
use App\Events\Message\GroupCreated;
use App\Models\ClientContact;
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
 *
 * A walk-in's inquiry has a group too, once sales has won it and the walk-in
 * has made a Regray account (createForWalkIn()): its client member is that
 * account, matched by the walk-in's phone or email, its sales member is the
 * inquiry's owner, and the inquiry stays the walk-in record's. Accounting and
 * logistics join any group only once sales has won its inquiry.
 *
 * The clients a creator finds and may choose are only those they are
 * authorized for (User::scopeClientsVisibleTo()): the clients whose lead they
 * hold, whose inquiries they read, whose Regray Team thread is theirs or still
 * in the support pool they belong to. The admin and the owner find every one.
 * The scope is applied before the search and the limit, in people() and in
 * create() alike, so a search never reaches past it and an id picked around it
 * is refused. A client result is labelled by name, or by email when the
 * search is an email the client's matches (User::searchLabel()).
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
        $client = $this->clientQuery($creator)->whereKey($clientId)->first();

        if ($client === null) {
            throw ValidationException::withMessages(['clientId' => 'Choose one client.']);
        }

        $inquiry = Inquiry::query()->visibleTo($creator)->whereKey($inquiryId)->where('client_id', $client->id)->first();

        if ($inquiry === null) {
            throw ValidationException::withMessages(['inquiryId' => "Choose one of {$client->name}'s inquiries."]);
        }

        if (! $inquiry->isWonByMarketing()) {
            throw ValidationException::withMessages(['inquiryId' => "{$inquiry->reference} is not won yet. Mark it won before making its group chat."]);
        }

        return $this->open($creator, $name, $staff, $client, $inquiry);
    }

    /**
     * A walk-in inquiry's group chat, once the walk-in has made a Regray
     * account: from the Inbox page's New group, "For a walk-in inquiry".
     *
     * The inquiry is one of the walk-in inquiries `$creator` reads, its sales
     * has won it, and it has no group yet. The client member is the account
     * holder whose phone or email is the walk-in's (walkInAccounts()), found
     * here, never sent; the inquiry stays the walk-in record's. Its staff are
     * one sales and one technical member, as for any group, and its sales
     * member is its owner.
     *
     * Has to run inside a transaction, as create() does.
     *
     * @param  list<int>  $staffIds  Exactly one sales (the owner) and one technical user.
     *
     * @throws ValidationException When the inquiry, its account or the staff may not be chosen.
     */
    public function createForWalkIn(User $creator, string $name, array $staffIds, int $inquiryId): Conversation
    {
        $inquiry = Inquiry::query()->visibleTo($creator)->whereKey($inquiryId)->whereNotNull('owner_user_id')->with('clientRecord', 'owner:id,name')->first();

        if ($inquiry === null) {
            throw ValidationException::withMessages(['inquiryId' => 'Choose one of the walk-in inquiries you can see.']);
        }

        if (! $inquiry->isWonBySales()) {
            throw ValidationException::withMessages(['inquiryId' => "{$inquiry->reference} is not won yet. Its group chat waits for sales to win it."]);
        }

        $accounts = $this->walkInAccounts($inquiry);
        $walkIn = $inquiry->clientRecord->full_name ?? 'This walk-in';

        if ($accounts->isEmpty()) {
            throw ValidationException::withMessages(['inquiryId' => "{$walkIn} has no Regray account yet. Their group chat waits for an account with their phone number or email."]);
        }

        if ($accounts->count() > 1) {
            throw ValidationException::withMessages(['inquiryId' => "More than one Regray account has {$walkIn}'s phone number or email, so the client cannot be told apart."]);
        }

        $staff = $this->groupStaff($staffIds);

        if (! $staff->contains('id', $inquiry->owner_user_id)) {
            throw ValidationException::withMessages(['staffIds' => 'Its sales member is the inquiry\'s owner, '.($inquiry->owner->name ?? 'its owner').'.']);
        }

        return $this->open($creator, $name, $staff, $accounts->first(), $inquiry);
    }

    /**
     * The walk-in inquiries `$creator` reads, newest first, matching a search
     * on the reference, the project or the walk-in's name: each with its
     * owner, whether sales has won it, whether it has a group already, and the
     * account its group's client would be (null when there is none, or more
     * than one).
     *
     * @return list<array{id: int, reference: string, name: string, client: string, owner: string|null, ownerId: int, isWon: bool, hasGroup: bool, account: array{id: int, name: string, email: string}|null}>
     */
    public function walkInInquiries(User $creator, ?string $search): array
    {
        $search = $search === null ? '' : trim($search);
        $pattern = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $search).'%';

        return array_values(Inquiry::query()
            ->visibleTo($creator)
            ->whereNotNull('owner_user_id')
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('reference', 'ILIKE', $pattern)
                ->orWhere('name', 'ILIKE', $pattern)
                ->orWhereHas('clientRecord', fn (Builder $record) => $record->where('full_name', 'ILIKE', $pattern))))
            ->with(['clientRecord:id,full_name', 'owner:id,name'])
            ->withExists('conversation as has_group')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::PEOPLE_LIMIT)
            ->get()
            ->map(function (Inquiry $inquiry): array {
                $accounts = $this->walkInAccounts($inquiry);
                $account = $accounts->count() === 1 ? $accounts->first() : null;

                return [
                    'id' => $inquiry->id,
                    'reference' => $inquiry->reference,
                    'name' => $inquiry->name,
                    'client' => $inquiry->clientRecord->full_name ?? '',
                    'owner' => $inquiry->owner?->name,
                    'ownerId' => (int) $inquiry->owner_user_id,
                    'isWon' => $inquiry->isWonBySales(),
                    'hasGroup' => (bool) $inquiry->getAttribute('has_group'),
                    'account' => $account === null ? null : ['id' => $account->id, 'name' => $account->name, 'email' => $account->email],
                ];
            })
            ->all());
    }

    /**
     * The client accounts that are this walk-in: those whose email, or whose
     * profile's phone number, is one of the walk-in record's contacts, both
     * compared as contacts are stored (ContactKind::normalise()).
     *
     * @return Collection<int, User>
     */
    public function walkInAccounts(Inquiry $inquiry): Collection
    {
        if ($inquiry->client_record_id === null) {
            return new Collection;
        }

        $contacts = ClientContact::query()->where('client_id', $inquiry->client_record_id)->get(['kind', 'value']);
        $emails = array_values($contacts->where('kind', ContactKind::Email)->pluck('value')->all());
        $phones = array_values($contacts->where('kind', ContactKind::Phone)->pluck('value')->all());

        if ($emails === [] && $phones === []) {
            return new Collection;
        }

        return User::query()
            ->whereHas('roles', fn (Builder $role) => $role->where('name', Role::Client->value))
            ->where(fn (Builder $query) => $query
                ->when($emails !== [], fn (Builder $query) => $query->orWhereIn(DB::raw('LOWER(users.email)'), $emails))
                ->when($phones !== [], fn (Builder $query) => $query->orWhereHas('profile', fn (Builder $profile) => $profile
                    ->whereIn(DB::raw("regexp_replace(user_profiles.phone, '[[:space:]().-]', '', 'g')"), $phones))))
            ->orderBy('users.id')
            ->get(['users.id', 'users.name', 'users.email']);
    }

    /**
     * Make the inquiry's one group: the group, its staff assigned to the
     * inquiry, the client as a member and the creator as its admin.
     *
     * @param  Collection<int, User>  $staff
     */
    private function open(User $creator, string $name, Collection $staff, User $client, Inquiry $inquiry): Conversation
    {
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

        // The `inquiry_handlers` trigger refuses them before this too.
        if (! in_array($inquiry->sales_status, [SalesStatus::Won, SalesStatus::ForRefund], true)) {
            throw ValidationException::withMessages(['accountingId' => "{$inquiry->reference} is not won yet. Accounting and logistics join once sales has won it."]);
        }

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
     * staff, each with their active inquiry count, and the clients `$creator`
     * is authorized for (clientQuery()), each with a `label` (User::searchLabel()).
     * With a client, that client's inquiries `$creator` reads too, each saying
     * whether it has a group already; none for a client outside the scope.
     *
     * @return array{sales: Collection<int, User>, technical: Collection<int, User>, clients: Collection<int, User>, inquiries: list<array{id: int, reference: string, name: string, hasGroup: bool, isWon: bool}>}
     */
    public function people(User $creator, ?string $search, ?int $clientId = null): array
    {
        $search = $search === null ? '' : trim($search);

        return [
            'sales' => $this->pickable($this->withRoles([Role::Sales]), $search)->get(),
            'technical' => $this->pickable($this->withRoles([Role::Technical]), $search)->get(),
            'clients' => $this->matching($this->clientQuery($creator), $search)
                ->get(['users.id', 'users.name', 'users.email'])
                ->each(fn (User $client): User => $client->setAttribute('label', $client->searchLabel($search))),
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

    /** @return list<array{id: int, reference: string, name: string, hasGroup: bool, isWon: bool}> */
    private function inquiriesOf(User $creator, int $clientId): array
    {
        if (! $this->clientQuery($creator)->whereKey($clientId)->exists()) {
            return [];
        }

        return array_values(Inquiry::query()
            ->visibleTo($creator)
            ->where('client_id', $clientId)
            ->withExists('conversation as has_group')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get(['id', 'reference', 'name', 'marketing_status'])
            ->map(fn (Inquiry $inquiry): array => [
                'id' => $inquiry->id,
                'reference' => $inquiry->reference,
                'name' => $inquiry->name,
                'hasGroup' => (bool) $inquiry->getAttribute('has_group'),
                // A group chat waits for marketing to win the inquiry.
                'isWon' => $inquiry->isWonByMarketing(),
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
     * The clients `$creator` may find and choose (User::scopeClientsVisibleTo()).
     *
     * @return Builder<User>
     */
    private function clientQuery(User $creator): Builder
    {
        return User::query()->clientsVisibleTo($creator);
    }
}
