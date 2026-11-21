<?php

namespace App\Repositories\Contracts;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

interface UserRepositoryInterface
{
    /**
     * Find a user by their email address.
     */
    public function findByEmail(string $email): ?User;

    /**
     * Create a new user.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): User;

    /**
     * The Accounts page: the staff accounts the admin manages (Role::staffRoles(),
     * never an admin or a client), but the archived, newest first, a page at a
     * time. The search (already escaped for ILIKE) matches the name or
     * the email; `$active` keeps the active (true) or inactive (false) ones,
     * and null keeps both.
     *
     * @return LengthAwarePaginator<int, User>
     */
    public function paginateAccounts(?string $escapedSearch, ?bool $active, int $perPage, int $page): LengthAwarePaginator;

    /**
     * How many admins may still sign in, leaving one user out: the guard that
     * keeps the last one from being turned off or archived.
     */
    public function countActiveAdminsExcept(User $user): int;

    /**
     * The records that keep the database from deleting the user (each of
     * them a RESTRICT foreign key to `users`): as a client, their inquiries,
     * bookings (schedules), leads and client record; as staff, the inquiries
     * they handle or own and the leads they hold.
     *
     * @return array{inquiries: int, bookings: int, leads: int, clientRecord: bool, handled: int, owned: int, heldLeads: int}
     */
    public function deletionBlockers(User $user): array;
}
