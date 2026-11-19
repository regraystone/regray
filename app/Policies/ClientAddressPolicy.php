<?php

namespace App\Policies;

use App\Models\ClientAddress;
use App\Models\User;

/**
 * An address in an address book is its owner's alone: only they see it,
 * change it, make it their default or delete it. The super-admin passes every
 * ability through `Gate::before`, so the controller also looks an address up
 * among the signed-in user's own: another account's id is not found at all,
 * for anyone.
 */
class ClientAddressPolicy
{
    public function view(User $user, ClientAddress $address): bool
    {
        return $address->user_id === $user->id;
    }

    public function update(User $user, ClientAddress $address): bool
    {
        return $address->user_id === $user->id;
    }

    public function delete(User $user, ClientAddress $address): bool
    {
        return $address->user_id === $user->id;
    }
}
