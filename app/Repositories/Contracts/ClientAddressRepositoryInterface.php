<?php

namespace App\Repositories\Contracts;

use App\Models\ClientAddress;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * A client's address book. Every read is scoped to the account, so an id that
 * is somebody else's is simply not found.
 */
interface ClientAddressRepositoryInterface
{
    /** @return Collection<int, ClientAddress> The default first, then the most recently changed. */
    public function forUser(User $user): Collection;

    public function findForUser(User $user, int $id): ?ClientAddress;

    /**
     * Lock the account's row until the transaction ends, so two changes to one
     * address book (two tabs adding the fifth address) run one after the other.
     */
    public function lockBook(User $user): void;

    public function countForUser(User $user): int;

    /** @param  array<string, mixed>  $attributes */
    public function create(User $user, array $attributes): ClientAddress;

    /** @param  array<string, mixed>  $attributes */
    public function update(ClientAddress $address, array $attributes): ClientAddress;

    public function delete(ClientAddress $address): void;

    /** Make this one the default, and no other. */
    public function makeDefault(ClientAddress $address): void;

    /** The address changed most recently, or null when the book is empty. */
    public function mostRecentlyUpdated(User $user): ?ClientAddress;
}
