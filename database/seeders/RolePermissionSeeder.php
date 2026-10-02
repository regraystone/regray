<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates the Spatie roles and permissions, and the role-to-sidebar matrix.
 *
 * These records are the source of truth for what a user may see: the shared
 * auth.permissions prop is derived from them, and the sidebar filters on it.
 */
class RolePermissionSeeder extends Seeder
{
    /**
     * What a user may be granted: one `view` permission per sidebar page, plus
     * any `add` action that is not a page of its own.
     *
     * Most `view` permissions name a sidebar item, so a permission here is
     * usually a guarantee that the sidebar has an entry for it. `view inquiry
     * details` is the exception: it names a page that has no item of its own,
     * because the details page hangs off the list the user reads their inquiries
     * on, the way the stepper does. `add inquiry` is the first of the action
     * kind: it guards the inquiry stepper, which both navigates to and returns
     * to a page rather than being one.
     *
     * @var array<int, string>
     */
    public const PERMISSIONS = [
        'view home',
        'view overview',
        'view saved',
        'view sales',
        'view quotation',
        'view inquiry',
        'view schedule',
        'view message',
        'view stones',
        'view services',
        'view accounts',
        'view projects',
        'view profile',
        'add inquiry',
        'view inquiry details',
    ];

    /**
     * Which permissions each role grants.
     *
     * Note that the `sales` role and the `view sales` permission are
     * unrelated: the sales role is a person, `view sales` is the Sales page,
     * and no role below happens to be granted it by name.
     *
     * @var array<string, array<int, string>>
     */
    public const MATRIX = [
        'client' => [
            'view home',
            'view saved',
            'view quotation',
            'view message',
            'view profile',
            'add inquiry',
            'view inquiry details',
        ],
        'admin' => [
            'view overview',
            'view stones',
            'view services',
            'view accounts',
            'view projects',
            'view profile',
        ],
        'owner' => [
            'view overview',
            'view sales',
            'view inquiry',
            'view schedule',
            'view message',
            'view profile',
            'view inquiry details',
        ],
        'mktg' => [
            'view overview',
            'view inquiry',
            'view schedule',
            'view message',
            'view profile',
            'add inquiry',
            'view inquiry details',
        ],
        'sales' => [
            'view overview',
            'view inquiry',
            'view schedule',
            'view message',
            'view profile',
            'view inquiry details',
        ],
        'acct' => [
            'view overview',
            'view sales',
            'view inquiry',
            'view message',
            'view profile',
            'view inquiry details',
        ],
        'tech' => [
            'view overview',
            'view inquiry',
            'view message',
            'view profile',
            'view inquiry details',
        ],
        'logi' => [
            'view overview',
            'view inquiry',
            'view message',
            'view profile',
            'view inquiry details',
        ],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (array_keys(self::MATRIX) as $roleName) {
            Role::findOrCreate($roleName, 'web');
        }

        // The package invalidates its own permission cache from a model's
        // `saved` event (RefreshesPermissionCache), but DatabaseSeeder runs
        // under WithoutModelEvents, which swaps in a NullDispatcher and stops
        // that event firing. So the empty snapshot cached by the first
        // findOrCreate() above is never dropped, and syncPermissions() would
        // fail to resolve the names. Flush it explicitly.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::MATRIX as $roleName => $permissions) {
            Role::findByName($roleName, 'web')->syncPermissions($permissions);
        }
    }
}
