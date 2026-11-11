<?php

namespace App\Policies;

use App\Enums\InquiryStatusTrack;
use App\Enums\Permission;
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

    /** Move one of its status tracks: sales, accounting or logistics. */
    public function updateStatus(User $user, Inquiry $inquiry, InquiryStatusTrack $track): bool
    {
        return $user->can(Permission::forStatusTrack($track)->value) && $inquiry->isVisibleTo($user);
    }
}
