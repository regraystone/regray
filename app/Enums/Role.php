<?php

namespace App\Enums;

/**
 * Who a user is, so they may be granted a bundle of permissions.
 *
 * A role is only a bundle: nothing authorizes against one. Access is always
 * checked against a Permission, so a role may be renamed, split or merged
 * without a single route or view having to know.
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
    case Mktg = 'mktg';

    /** Sales, who read and work the pipeline. */
    case Sales = 'sales';

    /** Accounts. */
    case Acct = 'acct';

    /** The bench technicians. */
    case Tech = 'tech';

    /** Logistics. */
    case Logi = 'logi';

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
