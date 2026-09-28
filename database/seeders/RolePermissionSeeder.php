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
     * The sidebar pages a user may be granted, one permission per page.
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
        'view profile',
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
        ],
        'admin' => [
            'view overview',
            'view stones',
            'view services',
            'view accounts',
            'view profile',
        ],
        'owner' => [
            'view overview',
            'view sales',
            'view inquiry',
            'view schedule',
            'view message',
            'view profile',
        ],
        'mktg' => [
            'view overview',
            'view inquiry',
            'view schedule',
            'view message',
            'view profile',
        ],
        'sales' => [
            'view overview',
            'view inquiry',
            'view schedule',
            'view message',
            'view profile',
        ],
        'acct' => [
            'view overview',
            'view sales',
            'view inquiry',
            'view message',
            'view profile',
        ],
        'tech' => [
            'view overview',
            'view inquiry',
            'view message',
            'view profile',
        ],
        'logi' => [
            'view overview',
            'view inquiry',
            'view message',
            'view profile',
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

        // The package no longer clears its own cache when a permission is
        // created, so the snapshot taken above is still empty here. Drop it
        // again or syncPermissions() cannot resolve the names to ids.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::MATRIX as $roleName => $permissions) {
            Role::findByName($roleName, 'web')->syncPermissions($permissions);
        }
    }
}
