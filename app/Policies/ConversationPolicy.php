<?php

namespace App\Policies;

use App\Enums\ConversationMemberRole;
use App\Enums\Permission;
use App\Enums\Role;
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
     *
     * An inquiry's group chat is read by the staff who read its inquiry
     * (Inquiry::isVisibleTo()), whether or not they are in its member list:
     * the admin and the owner read every one. Its client reads it as a
     * member. A group bound to no inquiry keeps the membership rule.
     */
    public function view(User $user, Conversation $conversation): bool
    {
        if ($conversation->isSupport()) {
            return $conversation->support_client_id === $user->id
                || $conversation->assigned_to === $user->id
                || ($conversation->assigned_to === null && $user->can(Permission::SupportClaim->value));
        }

        if ($conversation->isInquiryGroup() && ! $user->isClient()) {
            return $this->readsInquiry($user, $conversation);
        }

        return $this->members->isActiveMember($conversation->id, $user->id);
    }

    /**
     * Writing needs access first. In a group, a member who reads and sends,
     * who in an inquiry's group must also read the inquiry; on a Regray Team
     * thread, whoever may view it, with the assignment rules applied by the
     * service. The super-admin sends in any group through `Gate::before`. The
     * owner reads and never writes, in any conversation.
     *
     * The support pool also passes here after someone else has claimed the
     * thread, though it can no longer read it: a pool member whose send lost
     * the race is told who is handling it (409, from the service) rather than
     * refused outright. The name is no secret to them; the claim was announced
     * to the whole pool.
     */
    public function send(User $user, Conversation $conversation): bool
    {
        if ($user->hasRole(Role::Owner->value)) {
            return false;
        }

        if ($conversation->isSupport()) {
            return $this->view($user, $conversation)
                || $user->can(Permission::SupportClaim->value);
        }

        $member = $this->members->find($conversation->id, $user->id);

        if ($member === null || $member->role === ConversationMemberRole::Viewer) {
            return false;
        }

        if ($conversation->isInquiryGroup() && ! $user->isClient()) {
            return $this->readsInquiry($user, $conversation);
        }

        return true;
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

    /**
     * Add accounting and logistics to an inquiry's group chat: a sales member
     * of the group who reads its inquiry. Who may be added is
     * GroupConversationService::addMembers().
     */
    public function addMembers(User $user, Conversation $conversation): bool
    {
        return $conversation->isInquiryGroup()
            && $user->hasRole(Role::Sales->value)
            && $this->members->isActiveMember($conversation->id, $user->id)
            && $this->readsInquiry($user, $conversation);
    }

    /** Whether this staff member reads the inquiry the group chat is bound to. */
    private function readsInquiry(User $user, Conversation $conversation): bool
    {
        $inquiry = $conversation->inquiry;

        return $inquiry !== null && $inquiry->isVisibleTo($user);
    }
}
