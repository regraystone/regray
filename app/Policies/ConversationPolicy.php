<?php

namespace App\Policies;

use App\Enums\ConversationMemberRole;
use App\Enums\Permission;
use App\Models\Conversation;
use App\Models\User;
use App\Repositories\Contracts\ConversationMemberRepositoryInterface;

/**
 * Who may open, write in and hand over a conversation. The HTTP endpoints and
 * the broadcast channels both ask this class, so a thread a user cannot open
 * over HTTP is also one they cannot listen to.
 *
 * The super-admin passes every ability through `Gate::before` in
 * AppServiceProvider, so nothing here needs to name it.
 *
 * Whether a send is allowed *right now* — the thread is unassigned, or someone
 * else is handling it — is not access and is answered with 409 by
 * SupportAssignmentService, not here.
 *
 * TODO(inquiry-binding): groups will be bound to inquiries later. Until then no
 * group is linked to an inquiry, so R8 and R9 do nothing for real data.
 */
class ConversationPolicy
{
    public function __construct(
        public readonly ConversationMemberRepositoryInterface $members,
    ) {}

    /**
     * A member reads a group or direct conversation. A Regray Team thread is
     * read by its client, by whoever is handling it, and — while nobody is —
     * by everyone in the support pool.
     */
    public function view(User $user, Conversation $conversation): bool
    {
        if ($conversation->isSupport()) {
            return $conversation->support_client_id === $user->id
                || $conversation->assigned_to === $user->id
                || ($conversation->assigned_to === null && $user->can(Permission::SupportClaim->value));
        }

        return $this->members->isActiveMember($conversation->id, $user->id);
    }

    /**
     * Writing needs access first. In a group, a member who reads and sends;
     * on a Regray Team thread, whoever may view it, with the assignment rules
     * applied by the service.
     *
     * The support pool also passes here after someone else has claimed the
     * thread, though it can no longer read it: a pool member whose send lost
     * the race is told who is handling it (409, from the service) rather than
     * refused outright. The name is no secret to them; the claim was announced
     * to the whole pool.
     */
    public function send(User $user, Conversation $conversation): bool
    {
        if ($conversation->isSupport()) {
            return $this->view($user, $conversation)
                || $user->can(Permission::SupportClaim->value);
        }

        $member = $this->members->find($conversation->id, $user->id);

        return $member !== null && $member->role !== ConversationMemberRole::Viewer;
    }

    /**
     * Ask to claim a Regray Team thread. Whether it is still free is decided
     * inside the claim, under a row lock, so the loser of a race is told who
     * won (409) rather than being refused outright.
     */
    public function claim(User $user, Conversation $conversation): bool
    {
        return $conversation->isSupport()
            && ! $user->isClient()
            && $user->can(Permission::SupportClaim->value);
    }

    /** Hand the thread back to the pool: the assignee, or the super-admin. */
    public function release(User $user, Conversation $conversation): bool
    {
        return $conversation->isSupport()
            && $conversation->assigned_to !== null
            && $conversation->assigned_to === $user->id;
    }

    /** Give the thread to someone else: the super-admin only. */
    public function reassign(User $user, Conversation $conversation): bool
    {
        return false;
    }
}
