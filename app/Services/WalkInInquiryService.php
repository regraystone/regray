<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Enums\Role;
use App\Enums\SalesStatus;
use App\Models\Inquiry;
use App\Models\InquiryHandler;
use App\Models\User;
use App\Repositories\Contracts\InquiryRepositoryInterface;
use App\Services\Notification\NotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Who owns a walk-in inquiry, and who is assigned to it.
 *
 * Each walk-in inquiry has one owner, a sales user (`inquiries.owner_user_id`):
 * the sales member who opens it, or the one marketing names when it opens it.
 * The owner is its sales handler from the start. Then staff are added, never
 * moved or removed:
 *
 * - technical, by the owner or the marketing member who opened it, any number;
 * - one accounting and one logistics member, by the owner, once sales has won
 *   the inquiry.
 *
 * Who may is InquiryPolicy::assign(); the state each needs is checked here,
 * and again by the `inquiry_handlers` trigger, which refuses the same things.
 */
class WalkInInquiryService
{
    /** The roles a walk-in's staff are added from, in the order its page offers them. */
    public const ASSIGNED_ROLES = [Role::Technical, Role::Accounting, Role::Logistics];

    /** The roles an inquiry takes one of. */
    public const ONE_EACH = [Role::Accounting, Role::Logistics];

    public const CANDIDATE_LIMIT = 50;

    public function __construct(
        public readonly InquiryRepositoryInterface $inquiries,
        public readonly NotificationService $notifications,
    ) {}

    /**
     * The owner of a walk-in inquiry `$by` opens: themselves when they are
     * sales, otherwise the sales member they named.
     *
     * @throws ValidationException When `$by` is not sales and named no sales member.
     */
    public function ownerFor(User $by, ?int $ownerId): User
    {
        if ($by->hasRole(Role::Sales->value)) {
            return $by;
        }

        $owner = $ownerId === null ? null : self::staffWithRole(Role::Sales)->whereKey($ownerId)->first();

        return $owner ?? throw ValidationException::withMessages(['ownerId' => 'Choose the sales member who will own this inquiry.']);
    }

    /**
     * The sales members a walk-in inquiry may be given to, for whoever opens
     * one without being sales; null for a sales member, who owns what they open.
     *
     * @return Collection<int, User>|null
     */
    public function ownerOptions(User $by): ?Collection
    {
        if ($by->hasRole(Role::Sales->value)) {
            return null;
        }

        return self::staffWithRole(Role::Sales)->orderBy('name')->get(['users.id', 'users.name']);
    }

    /**
     * The roles `$user` may add someone from to this walk-in now: allowed by
     * InquiryPolicy::assign() and by the inquiry's state. None on an inquiry
     * that is not a walk-in.
     *
     * @return list<Role>
     */
    public function assignableRoles(User $user, Inquiry $inquiry): array
    {
        if ($inquiry->owner_user_id === null || ! InquiryStatusRules::isOpen($inquiry)) {
            return [];
        }

        return array_values(array_filter(
            self::ASSIGNED_ROLES,
            fn (Role $role): bool => Gate::forUser($user)->allows('assign', [$inquiry, $role]) && $this->stateRefusal($inquiry, $role) === null,
        ));
    }

    /**
     * Who `$user` may add to this walk-in now, by role: the staff of each
     * role they may assign from (assignableRoles()), not already on it.
     *
     * @return array<string, Collection<int, User>>
     */
    public function candidates(User $user, Inquiry $inquiry): array
    {
        $assigned = InquiryHandler::query()->where('inquiry_id', $inquiry->id)->pluck('user_id')->all();
        $candidates = [];

        foreach ($this->assignableRoles($user, $inquiry) as $role) {
            $candidates[$role->value] = self::staffWithRole($role)
                ->whereNotIn('users.id', $assigned)
                ->orderBy('name')
                ->limit(self::CANDIDATE_LIMIT)
                ->get(['users.id', 'users.name']);
        }

        return $candidates;
    }

    /**
     * Add one staff member to a walk-in inquiry, by `$by`, and tell them.
     * Their role says which place they take (the first they hold of
     * technical, accounting and logistics). Has to run inside a transaction:
     * the inquiry's row is locked until it ends, so two adds at once cannot
     * both take the one accounting or logistics place.
     *
     * @throws ModelNotFoundException When `$by` does not read the inquiry, or it is not a walk-in.
     * @throws ValidationException When the person may not be added now.
     */
    public function assign(User $by, string $reference, int $userId): User
    {
        $inquiry = $this->inquiries->findVisibleByReferenceForUpdate($by, $reference);

        if ($inquiry === null || $inquiry->owner_user_id === null) {
            throw (new ModelNotFoundException)->setModel(Inquiry::class, [$reference]);
        }

        $member = User::query()->whereKey($userId)->with('roles')->first();
        $role = $member === null || $member->isClient() ? null : self::stageOf($member);

        if ($member === null || $role === null) {
            throw ValidationException::withMessages(['userId' => 'Choose someone from technical, accounting or logistics.']);
        }

        Gate::forUser($by)->authorize('assign', [$inquiry, $role]);

        if (! InquiryStatusRules::isOpen($inquiry)) {
            throw ValidationException::withMessages(['userId' => "{$inquiry->reference} is closed or cancelled, so no one can be added to it."]);
        }

        if ($inquiry->handlers()->where('user_id', $member->id)->exists()) {
            throw ValidationException::withMessages(['userId' => "{$member->name} is already assigned to {$inquiry->reference}."]);
        }

        $refusal = $this->stateRefusal($inquiry, $role);

        if ($refusal !== null) {
            throw ValidationException::withMessages(['userId' => $refusal]);
        }

        $this->inquiries->addHandlers($inquiry, [$member->id], $by->id, Date::now());
        $this->notifications->send([$member], NotificationType::Inquiry, $inquiry->id, "{$by->name} has assigned you to inquiry {$inquiry->reference}.");

        return $member;
    }

    /**
     * The place a staff member takes on an inquiry, from their roles, as the
     * `inquiry_handlers` trigger reads it (inquiry_handler_stage()).
     */
    public static function stageOf(User $user): ?Role
    {
        foreach ([Role::Sales, ...self::ASSIGNED_ROLES] as $role) {
            if ($user->hasRole($role->value)) {
                return $role;
            }
        }

        return null;
    }

    /**
     * Staff holding `$role`, never a client.
     *
     * @return Builder<User>
     */
    public static function staffWithRole(Role $role): Builder
    {
        return User::query()
            ->whereHas('roles', fn (Builder $query) => $query->where('name', $role->value))
            ->whereDoesntHave('roles', fn (Builder $query) => $query->where('name', Role::Client->value));
    }

    /** Why the inquiry's state does not take someone of `$role` now, or null when it does. */
    private function stateRefusal(Inquiry $inquiry, Role $role): ?string
    {
        if (! in_array($role, self::ONE_EACH, true)) {
            return null;
        }

        $team = strtolower((string) $role->label());

        if (! in_array($inquiry->sales_status, [SalesStatus::Won, SalesStatus::ForRefund], true)) {
            return "{$inquiry->reference} is not won yet. Someone from {$team} joins once sales has won it.";
        }

        $taken = $inquiry->handlers()->with('user.roles')->get()
            ->contains(fn (InquiryHandler $handler): bool => self::stageOf($handler->user) === $role);

        return $taken ? "{$inquiry->reference} already has someone from {$team}." : null;
    }
}
