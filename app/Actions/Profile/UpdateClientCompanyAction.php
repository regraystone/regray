<?php

namespace App\Actions\Profile;

use App\Models\User;

/**
 * Set the company a client gave on Edit profile, optional: kept on their
 * profile (user_profiles.company), where the Profile hub reads it. A blank
 * company clears a stored one and opens no profile where none is; a client
 * with no profile yet gets one with the company alone, its phone and address
 * blank, as LeadService::updateClientPhone() opens one with a phone alone.
 * Staff have no profile, so nothing is kept for them.
 */
class UpdateClientCompanyAction
{
    public function execute(User $user, ?string $company): void
    {
        if (! $user->isClient()) {
            return;
        }

        $profile = $user->profile;

        if ($profile !== null) {
            $profile->update(['company' => $company]);
        } elseif ($company !== null) {
            // The relation was read as missing above, so it is set to the new one.
            $user->setRelation('profile', $user->profile()->create(['phone' => '', 'address' => '', 'company' => $company]));
        }
    }
}
