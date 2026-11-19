<?php

namespace App\Policies;

use App\Enums\InquiryStatusTrack;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Inquiry;
use App\Models\User;

/**
 * What staff may do with an inquiry from the Inquiry list, each on the
 * permission its route checks. Whether the inquiry's state allows it (a track
 * that can still move, an inquiry that is still open) is
 * InquiryStatusRules's, checked beside this, because the super-admin passes
 * every ability here through `Gate::before`.
 *
 * Staff also have to read the inquiry (Inquiry::isVisibleTo()): holding the
 * permission is not enough for one they are not part of. The callers look the
 * inquiry up through Inquiry::scopeVisibleTo() first, so one a user does not
 * read is a 404 there before it is ever refused here.
 */
class InquiryPolicy
{
    /** Open its details: staff who read the Inquiry list and this inquiry, or its own client. */
    public function view(User $user, Inquiry $inquiry): bool
    {
        return ($user->can(Permission::InquiriesView->value) && $inquiry->isVisibleTo($user))
            || ($inquiry->client_id !== null && $inquiry->client_id === $user->id);
    }

    /** Change its project from the Inquiry list. */
    public function update(User $user, Inquiry $inquiry): bool
    {
        return $user->can(Permission::InquiriesEdit->value) && $inquiry->isVisibleTo($user);
    }

    /** Upload its job order after the quote: whoever moves sales. */
    public function uploadJobOrder(User $user, Inquiry $inquiry): bool
    {
        return $user->can(Permission::InquiriesStatusSales->value) && $inquiry->isVisibleTo($user);
    }

    /**
     * Make its group chat: whoever may start group chats
     * (`conversations.create`) and reads the inquiry, once marketing has won
     * it. An inquiry still waiting for marketing, or lost, has none. The
     * super-admin passes here through `Gate::before`, and is still held to the
     * won rule by GroupConversationService::create().
     */
    public function createGroup(User $user, Inquiry $inquiry): bool
    {
        return $user->can(Permission::ConversationsCreate->value)
            && $inquiry->isWonByMarketing()
            && $inquiry->isVisibleTo($user);
    }

    /** Move one of its status tracks: sales, accounting or logistics. */
    public function updateStatus(User $user, Inquiry $inquiry, InquiryStatusTrack $track): bool
    {
        return $user->can(Permission::forStatusTrack($track)->value) && $inquiry->isVisibleTo($user);
    }

    /**
     * Assign someone of `$role` to a walk-in inquiry: technical by its owner or
     * the marketing member who opened it; accounting and logistics by its owner
     * alone. Only who may; whether the inquiry's state allows it (open, won
     * for accounting and logistics, the place still free) is
     * WalkInInquiryService::assign()'s, which the super-admin is held to as well.
     */
    public function assign(User $user, Inquiry $inquiry, Role $role): bool
    {
        if ($inquiry->owner_user_id === null || ! $inquiry->isVisibleTo($user)) {
            return false;
        }

        $isOwner = $inquiry->owner_user_id === $user->id;

        return match ($role) {
            Role::Technical => $isOwner || $this->isMarketingCreator($user, $inquiry),
            Role::Accounting, Role::Logistics => $isOwner,
            default => false,
        };
    }

    /**
     * Decide a walk-in inquiry, won or lost: the marketing member who opened
     * it. One on a lead is decided from the Leads page (LeadPolicy).
     */
    public function decideWalkIn(User $user, Inquiry $inquiry): bool
    {
        return $inquiry->owner_user_id !== null
            && $user->can(Permission::LeadsUpdate->value)
            && $this->isMarketingCreator($user, $inquiry);
    }

    private function isMarketingCreator(User $user, Inquiry $inquiry): bool
    {
        return $inquiry->created_by === $user->id && $user->hasRole(Role::Marketing->value);
    }
}
