<?php

namespace App\Repositories\Contracts;

use App\Enums\ContactKind;
use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface ClientRepositoryInterface
{
    public function find(int $id): ?Client;

    public function findForUser(User $user): ?Client;

    /** A platform client record for the account. Throws on a second one. */
    public function createForUser(User $user): Client;

    /**
     * A direct client with its contacts, already normalised. Throws on a
     * contact another client has.
     *
     * @param  array{full_name: string, company: ?string, address: ?string}  $attributes
     * @param  list<array{kind: ContactKind, value: string}>  $contacts
     */
    public function createDirect(array $attributes, array $contacts, ?int $createdBy): Client;

    /**
     * The client that already has one of these contacts, with the contact it
     * matched on, or null.
     *
     * @param  list<array{kind: ContactKind, value: string}>  $contacts
     * @return array{client: Client, kind: ContactKind, value: string}|null
     */
    public function findByContacts(array $contacts): ?array;

    /**
     * Accounts with the client role whose name or email matches, by name.
     *
     * @return Collection<int, User>
     */
    public function searchAccounts(string $escapedSearch, int $limit): Collection;
}
