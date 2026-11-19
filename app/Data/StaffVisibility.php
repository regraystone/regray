<?php

namespace App\Data;

use App\Enums\Role;
use App\Models\User;

/**
 * Which inquiries, visits and group chats a staff member reads, from their
 * roles: the union of what each role reads (Role::seesEveryInquiry() and the
 * rest), so a user with two roles reads what either would. A user with no
 * role that reads anything reads nothing.
 *
 * The SQL scopes (Inquiry::scopeVisibleTo(), Schedule::scopeVisibleTo(),
 * Conversation::scopeVisibleToStaff()) and the checks on one loaded row
 * (Inquiry::isVisibleTo(), Schedule::isVisibleTo()) are both built from this,
 * so a list and a policy answer alike. A client's own inquiries, visits and
 * chats are not decided here.
 */
final readonly class StaffVisibility
{
    public function __construct(
        /** Every inquiry, visit and group chat: the admin and the owner. */
        public bool $everything = false,
        /** The inquiries of the leads the user holds. */
        public bool $leadInquiries = false,
        /** The inquiries the user opened. */
        public bool $createdInquiries = false,
        /** The inquiries the user is assigned to. */
        public bool $assignedInquiries = false,
        /** The walk-in inquiries the user owns (`inquiries.owner_user_id`). */
        public bool $ownedInquiries = false,
        /** The visits booked outside an inquiry it handles: booked, assigned or queued (marketing). */
        public bool $handledVisits = false,
        /** The visits booked outside an inquiry assigned to it (sales). */
        public bool $assignedVisits = false,
    ) {}

    public static function for(User $user): self
    {
        $roles = $user->getRoleNames()
            ->map(fn (string $name): ?Role => Role::tryFrom($name))
            ->filter();

        return new self(
            everything: $roles->contains(fn (Role $role): bool => $role->seesEveryInquiry()),
            leadInquiries: $roles->contains(fn (Role $role): bool => $role->seesLeadInquiries()),
            createdInquiries: $roles->contains(fn (Role $role): bool => $role->seesCreatedInquiries()),
            assignedInquiries: $roles->contains(fn (Role $role): bool => $role->seesAssignedInquiries()),
            ownedInquiries: $roles->contains(fn (Role $role): bool => $role->seesOwnedInquiries()),
            handledVisits: $roles->contains(fn (Role $role): bool => $role->seesHandledVisits()),
            assignedVisits: $roles->contains(fn (Role $role): bool => $role->seesAssignedVisits()),
        );
    }

    /** Whether no clause reads any inquiry, so the answer is none at all. */
    public function seesNoInquiry(): bool
    {
        return ! $this->everything && ! $this->leadInquiries && ! $this->createdInquiries && ! $this->assignedInquiries && ! $this->ownedInquiries;
    }
}
