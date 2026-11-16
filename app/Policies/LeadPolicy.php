<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Lead;
use App\Models\User;

/**
 * Who may act on a lead. The route checks the permission first (`leads.view`
 * for the Leads page, `inquiries.create` for opening an inquiry); this answers
 * for the one lead. The super-admin passes every ability through
 * `Gate::before` in AppServiceProvider.
 */
class LeadPolicy
{
    /**
     * Decide its inquiries won or lost, and correct its client's phone: the
     * marketing user who holds it, with `leads.update`.
     */
    public function update(User $user, Lead $lead): bool
    {
        return $user->can(Permission::LeadsUpdate->value) && $lead->marketing_user_id === $user->id;
    }

    /** See it on the Leads page: whoever holds `leads.view`. */
    public function view(User $user, Lead $lead): bool
    {
        return $user->can(Permission::LeadsView->value);
    }

    /**
     * Open an inquiry for the lead's client, a returning customer's included:
     * the marketing user who holds it, with `inquiries.create.platform`. Sales
     * opens inquiries only for direct (walk-in) clients, never under a lead.
     */
    public function createInquiry(User $user, Lead $lead): bool
    {
        return $user->can(Permission::InquiriesCreatePlatform->value)
            && $user->hasRole(Role::Marketing->value)
            && $lead->marketing_user_id === $user->id;
    }
}
