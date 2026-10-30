<?php

namespace App\Policies;

use App\Enums\LeadStatus;
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
     * Open an inquiry for the lead's client. Marketing may use its own leads;
     * sales only a lead with an inquiry marketing has won, the client already
     * being a customer.
     */
    public function createInquiry(User $user, Lead $lead): bool
    {
        if ($user->hasRole(Role::Sales->value)) {
            return $lead->inquiries()->where('marketing_status', LeadStatus::Won)->exists();
        }

        return $user->hasRole(Role::Marketing->value) && $lead->marketing_user_id === $user->id;
    }
}
