<?php

namespace App\Enums;

/**
 * Who a user is, so they may be granted a bundle of permissions.
 *
 * A role is mostly a bundle: access to a page or an action is checked against
 * a Permission, so a role may be renamed, split or merged without a single
 * route or view having to know. The one exception is which inquiries, visits
 * and group chats a staff member reads (StaffVisibility): that follows the
 * staff member's part in the inquiry, so it is said here, role by role.
 */
enum Role: string
{
    /**
     * The studio's customer, who browses stones, reads their quotations and
     * raises an inquiry. The default a new registration is given.
     */
    case Client = 'client';

    /**
     * The super-admin, who may do anything.
     *
     * AppServiceProvider gives this role a Gate::before() that returns true
     * before any permission is consulted, so it does not need every permission
     * to be listed in the matrix to reach every page. It is listed anyway, so
     * the shared `auth.permissions` prop still describes what it may see.
     */
    case Admin = 'admin';

    /** The studio owner, who reads the commercial side. */
    case Owner = 'owner';

    /** Marketing, who raise inquiries. */
    case Marketing = 'marketing';

    /** Sales, who read and work the pipeline. */
    case Sales = 'sales';

    /** Accounting. */
    case Accounting = 'accounting';

    /** The bench technicians. */
    case Technical = 'technical';

    /** Logistics. */
    case Logistics = 'logistics';

    /**
     * The role whose Gate::before() bypasses every permission check.
     */
    public static function superAdmin(): self
    {
        return self::Admin;
    }

    /**
     * Whether this role bypasses permission checks entirely.
     */
    public function isSuperAdmin(): bool
    {
        return $this === self::superAdmin();
    }

    /** Reads every inquiry, visit and group chat: the admin, and the owner, read-only. */
    public function seesEveryInquiry(): bool
    {
        return $this === self::Admin || $this === self::Owner;
    }

    /** Reads the inquiries of the leads it holds (`leads.marketing_user_id`). */
    public function seesLeadInquiries(): bool
    {
        return $this === self::Marketing;
    }

    /** Reads the inquiries it opened for a client (`inquiries.created_by`). */
    public function seesCreatedInquiries(): bool
    {
        return $this === self::Marketing || $this === self::Sales;
    }

    /** Reads the inquiries it is assigned to (`inquiry_handlers`). */
    public function seesAssignedInquiries(): bool
    {
        return in_array($this, [self::Sales, self::Technical, self::Accounting, self::Logistics], true);
    }

    /** Reads every visit booked outside an inquiry. */
    public function seesStandaloneVisits(): bool
    {
        return $this === self::Marketing || $this === self::Sales;
    }

    /**
     * Every role name, as the seeder and the tests read them.
     *
     * @return array<int, string>
     */
    public static function names(): array
    {
        return array_column(self::cases(), 'value');
    }
}
