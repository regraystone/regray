<?php

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Stone;
use App\Models\User;
use Database\Seeders\ProjectCategorySeeder;
use Database\Seeders\ProjectSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\StoneSeeder;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        StoneSeeder::class,
        ProjectCategorySeeder::class,
        ProjectSeeder::class,
    ]);
});

describe('Home page permissions', function (): void {
    test('home.view grants access to /home feed', function (): void {
        $user = User::factory()->role(Role::Client)->create();

        expect(in_array(Permission::HomeView->value, RolePermissionSeeder::MATRIX[Role::Client->value], true))->toBeTrue();

        $this->actingAs($user)
            ->get(route('home.index'))
            ->assertOk();
    });

    test('user without home.view gets 403 on /home', function (): void {
        $user = User::factory()->role(Role::Technical)->create();

        expect(in_array(Permission::HomeView->value, RolePermissionSeeder::MATRIX[Role::Technical->value], true))->toBeFalse();

        $this->actingAs($user)
            ->get(route('home.index'))
            ->assertForbidden();
    });

    test('home-stone.view grants access to /home/{stone}', function (): void {
        $user = User::factory()->role(Role::Client)->create();
        $stone = Stone::query()->first();

        expect(in_array(Permission::HomeStoneView->value, RolePermissionSeeder::MATRIX[Role::Client->value], true))->toBeTrue();

        $this->actingAs($user)
            ->get(route('home.stone', $stone->slug))
            ->assertOk();
    });

    test('user without home-stone.view gets 403 on /home/{stone}', function (): void {
        $user = User::factory()->role(Role::Owner)->create();
        $stone = Stone::query()->first();

        expect(in_array(Permission::HomeStoneView->value, RolePermissionSeeder::MATRIX[Role::Owner->value], true))->toBeFalse();

        $this->actingAs($user)
            ->get(route('home.stone', $stone->slug))
            ->assertForbidden();
    });

    test('home-projects.view grants access to /home/{stone}/{project}', function (): void {
        $user = User::factory()->role(Role::Client)->create();
        $stone = Stone::query()
            ->with('projects')
            ->whereHas('projects')
            ->first();
        $project = $stone->projects->first();

        expect(in_array(Permission::HomeProjectsView->value, RolePermissionSeeder::MATRIX[Role::Client->value], true))->toBeTrue();

        $this->actingAs($user)
            ->get(route('home.project', [$stone->slug, $project->slug]))
            ->assertOk();
    });

    test('user without home-projects.view gets 403 on /home/{stone}/{project}', function (): void {
        $user = User::factory()->role(Role::Marketing)->create();
        $stone = Stone::query()
            ->with('projects')
            ->whereHas('projects')
            ->first();
        $project = $stone->projects->first();

        expect(in_array(Permission::HomeProjectsView->value, RolePermissionSeeder::MATRIX[Role::Marketing->value], true))->toBeFalse();

        $this->actingAs($user)
            ->get(route('home.project', [$stone->slug, $project->slug]))
            ->assertForbidden();
    });

    test('only client and admin have home.view', function (string $role): void {
        expect(in_array(Permission::HomeView->value, RolePermissionSeeder::MATRIX[$role], true))
            ->toEqual(in_array($role, [Role::Client->value, Role::Admin->value]));
    })->with(Role::names());

    test('only client and admin have home-stone.view', function (string $role): void {
        expect(in_array(Permission::HomeStoneView->value, RolePermissionSeeder::MATRIX[$role], true))
            ->toEqual(in_array($role, [Role::Client->value, Role::Admin->value]));
    })->with(Role::names());

    test('only client and admin have home-projects.view', function (string $role): void {
        expect(in_array(Permission::HomeProjectsView->value, RolePermissionSeeder::MATRIX[$role], true))
            ->toEqual(in_array($role, [Role::Client->value, Role::Admin->value]));
    })->with(Role::names());

    test('admin can access all home pages', function (): void {
        $user = User::factory()->role(Role::Admin)->create();
        $stone = Stone::query()
            ->with('projects')
            ->whereHas('projects')
            ->first();
        $project = $stone->projects->first();

        $this->actingAs($user)->get(route('home.index'))->assertOk();
        $this->actingAs($user)->get(route('home.stone', $stone->slug))->assertOk();
        $this->actingAs($user)->get(route('home.project', [$stone->slug, $project->slug]))->assertOk();
    });

    test('all staff roles cannot access any home page', function (string $role): void {
        if (in_array($role, [Role::Client->value, Role::Admin->value])) {
            $this->markTestSkipped("$role is not a staff role");
        }

        $user = User::factory()->role($role)->create();
        $stone = Stone::query()
            ->with('projects')
            ->whereHas('projects')
            ->first();
        $project = $stone->projects->first();

        $this->actingAs($user)->get(route('home.index'))->assertForbidden();
        $this->actingAs($user)->get(route('home.stone', $stone->slug))->assertForbidden();
        $this->actingAs($user)->get(route('home.project', [$stone->slug, $project->slug]))->assertForbidden();
    })->with(Role::names());
});

describe('Saved page permissions', function (): void {
    test('saved.view grants access to /saved list', function (): void {
        $user = User::factory()->role(Role::Client)->create();

        expect(in_array(Permission::SavedView->value, RolePermissionSeeder::MATRIX[Role::Client->value], true))->toBeTrue();

        $this->actingAs($user)
            ->get(route('saved.index'))
            ->assertOk();
    });

    test('user without saved.view gets 403 on /saved', function (): void {
        $user = User::factory()->role(Role::Sales)->create();

        expect(in_array(Permission::SavedView->value, RolePermissionSeeder::MATRIX[Role::Sales->value], true))->toBeFalse();

        $this->actingAs($user)
            ->get(route('saved.index'))
            ->assertForbidden();
    });

    test('saved-stone.view grants access to /saved/{stone}', function (): void {
        $user = User::factory()->role(Role::Client)->create();
        $stone = Stone::query()->first();

        expect(in_array(Permission::SavedStoneView->value, RolePermissionSeeder::MATRIX[Role::Client->value], true))->toBeTrue();

        $this->actingAs($user)
            ->get(route('saved.show', $stone->slug))
            ->assertOk();
    });

    test('user without saved-stone.view gets 403 on /saved/{stone}', function (): void {
        $user = User::factory()->role(Role::Logistics)->create();
        $stone = Stone::query()->first();

        expect(in_array(Permission::SavedStoneView->value, RolePermissionSeeder::MATRIX[Role::Logistics->value], true))->toBeFalse();

        $this->actingAs($user)
            ->get(route('saved.show', $stone->slug))
            ->assertForbidden();
    });

    test('saved-projects.view grants access to /saved/{stone}/{project}', function (): void {
        $user = User::factory()->role(Role::Client)->create();
        $stone = Stone::query()
            ->with('projects')
            ->whereHas('projects')
            ->first();
        $project = $stone->projects->first();

        expect(in_array(Permission::SavedProjectsView->value, RolePermissionSeeder::MATRIX[Role::Client->value], true))->toBeTrue();

        $this->actingAs($user)
            ->get(route('saved.project', [$stone->slug, $project->slug]))
            ->assertOk();
    });

    test('user without saved-projects.view gets 403 on /saved/{stone}/{project}', function (): void {
        $user = User::factory()->role(Role::Accounting)->create();
        $stone = Stone::query()
            ->with('projects')
            ->whereHas('projects')
            ->first();
        $project = $stone->projects->first();

        expect(in_array(Permission::SavedProjectsView->value, RolePermissionSeeder::MATRIX[Role::Accounting->value], true))->toBeFalse();

        $this->actingAs($user)
            ->get(route('saved.project', [$stone->slug, $project->slug]))
            ->assertForbidden();
    });

    test('saved.manage grants access to PUT /saved/{stone}', function (): void {
        $user = User::factory()->role(Role::Client)->create();
        $stone = Stone::query()->first();

        expect(in_array(Permission::SavedManage->value, RolePermissionSeeder::MATRIX[Role::Client->value], true))->toBeTrue();

        $this->actingAs($user)
            ->put(route('saved.store', $stone->slug))
            ->assertRedirect();
    });

    test('user without saved.manage gets 403 on PUT /saved/{stone}', function (): void {
        $user = User::factory()->role(Role::Technical)->create();
        $stone = Stone::query()->first();

        expect(in_array(Permission::SavedManage->value, RolePermissionSeeder::MATRIX[Role::Technical->value], true))->toBeFalse();

        $this->actingAs($user)
            ->put(route('saved.store', $stone->slug))
            ->assertForbidden();
    });

    test('saved.manage grants access to DELETE /saved/{stone}', function (): void {
        $user = User::factory()->role(Role::Client)->create();
        $stone = Stone::query()->first();

        expect(in_array(Permission::SavedManage->value, RolePermissionSeeder::MATRIX[Role::Client->value], true))->toBeTrue();

        $this->actingAs($user)
            ->delete(route('saved.destroy', $stone->slug))
            ->assertRedirect();
    });

    test('user without saved.manage gets 403 on DELETE /saved/{stone}', function (): void {
        $user = User::factory()->role(Role::Owner)->create();
        $stone = Stone::query()->first();

        expect(in_array(Permission::SavedManage->value, RolePermissionSeeder::MATRIX[Role::Owner->value], true))->toBeFalse();

        $this->actingAs($user)
            ->delete(route('saved.destroy', $stone->slug))
            ->assertForbidden();
    });

    test('only client and admin have saved.view', function (string $role): void {
        expect(in_array(Permission::SavedView->value, RolePermissionSeeder::MATRIX[$role], true))
            ->toEqual(in_array($role, [Role::Client->value, Role::Admin->value]));
    })->with(Role::names());

    test('only client and admin have saved-stone.view', function (string $role): void {
        expect(in_array(Permission::SavedStoneView->value, RolePermissionSeeder::MATRIX[$role], true))
            ->toEqual(in_array($role, [Role::Client->value, Role::Admin->value]));
    })->with(Role::names());

    test('only client and admin have saved-projects.view', function (string $role): void {
        expect(in_array(Permission::SavedProjectsView->value, RolePermissionSeeder::MATRIX[$role], true))
            ->toEqual(in_array($role, [Role::Client->value, Role::Admin->value]));
    })->with(Role::names());

    test('only client and admin have saved.manage', function (string $role): void {
        expect(in_array(Permission::SavedManage->value, RolePermissionSeeder::MATRIX[$role], true))
            ->toEqual(in_array($role, [Role::Client->value, Role::Admin->value]));
    })->with(Role::names());

    test('admin can access all saved pages', function (): void {
        $user = User::factory()->role(Role::Admin)->create();
        $stone = Stone::query()
            ->with('projects')
            ->whereHas('projects')
            ->first();
        $project = $stone->projects->first();

        $this->actingAs($user)->get(route('saved.index'))->assertOk();
        $this->actingAs($user)->get(route('saved.show', $stone->slug))->assertOk();
        $this->actingAs($user)->get(route('saved.project', [$stone->slug, $project->slug]))->assertOk();
        $this->actingAs($user)->put(route('saved.store', $stone->slug))->assertRedirect();
        $this->actingAs($user)->delete(route('saved.destroy', $stone->slug))->assertRedirect();
    });

    test('all staff roles cannot access any saved page', function (string $role): void {
        if (in_array($role, [Role::Client->value, Role::Admin->value])) {
            $this->markTestSkipped("$role is not a staff role");
        }

        $user = User::factory()->role($role)->create();
        $stone = Stone::query()
            ->with('projects')
            ->whereHas('projects')
            ->first();
        $project = $stone->projects->first();

        $this->actingAs($user)->get(route('saved.index'))->assertForbidden();
        $this->actingAs($user)->get(route('saved.show', $stone->slug))->assertForbidden();
        $this->actingAs($user)->get(route('saved.project', [$stone->slug, $project->slug]))->assertForbidden();
        $this->actingAs($user)->put(route('saved.store', $stone->slug))->assertForbidden();
        $this->actingAs($user)->delete(route('saved.destroy', $stone->slug))->assertForbidden();
    })->with(Role::names());
});

describe('Permission enum completeness', function (): void {
    test('all new permissions exist in enum', function (): void {
        expect(Permission::HomeStoneView->value)->toBe('home-stone.view');
        expect(Permission::SavedStoneView->value)->toBe('saved-stone.view');
        expect(Permission::SavedProjectsView->value)->toBe('saved-projects.view');
    });

    test('all new permissions are in permission names', function (): void {
        $names = Permission::names();

        expect($names)->toContain('home-stone.view');
        expect($names)->toContain('saved-stone.view');
        expect($names)->toContain('saved-projects.view');
    });
});

describe('Permission matrix consistency', function (): void {
    test('permissions in matrix exist in enum', function (string $role): void {
        $granted = RolePermissionSeeder::MATRIX[$role];
        $names = Permission::names();

        expect(array_diff($granted, $names))->toBe([]);
    })->with(Role::names());

    test('no role has duplicate permissions', function (string $role): void {
        $granted = RolePermissionSeeder::MATRIX[$role];

        expect($granted)->toBe(array_values(array_unique($granted)));
    })->with(Role::names());

    test('client has exactly the permissions it needs', function (): void {
        $client = RolePermissionSeeder::MATRIX[Role::Client->value];

        // Home pages
        expect($client)->toContain(Permission::HomeView->value);
        expect($client)->toContain(Permission::HomeStoneView->value);
        expect($client)->toContain(Permission::HomeProjectsView->value);

        // Saved pages
        expect($client)->toContain(Permission::SavedView->value);
        expect($client)->toContain(Permission::SavedStoneView->value);
        expect($client)->toContain(Permission::SavedProjectsView->value);
        expect($client)->toContain(Permission::SavedManage->value);

        // Profile
        expect($client)->toContain(Permission::ProfileView->value);
        expect($client)->toContain(Permission::ProfileScheduleView->value);

        // Inquiry & Schedule
        expect($client)->toContain(Permission::InquiriesCreate->value);
        expect($client)->toContain(Permission::ScheduleCreate->value);

        // Other
        expect($client)->toContain(Permission::QuotationsView->value);
        expect($client)->toContain(Permission::MessagesView->value);
    });

    test('admin has all permissions', function (): void {
        $admin = collect(RolePermissionSeeder::MATRIX[Role::Admin->value])->sort()->values()->all();
        $allPermissions = collect(Permission::names())->sort()->values()->all();

        expect($admin)->toEqual($allPermissions);
    });

    test('no staff role has home or saved permissions', function (string $role): void {
        if (in_array($role, [Role::Client->value, Role::Admin->value])) {
            $this->markTestSkipped("$role is not a staff role");
        }

        $granted = RolePermissionSeeder::MATRIX[$role];

        expect($granted)->not->toContain(Permission::HomeView->value);
        expect($granted)->not->toContain(Permission::HomeStoneView->value);
        expect($granted)->not->toContain(Permission::HomeProjectsView->value);
        expect($granted)->not->toContain(Permission::SavedView->value);
        expect($granted)->not->toContain(Permission::SavedStoneView->value);
        expect($granted)->not->toContain(Permission::SavedProjectsView->value);
        expect($granted)->not->toContain(Permission::SavedManage->value);
    })->with(Role::names());
});

describe('Guest and unauthenticated access', function (): void {
    test('guest is sent to login on /home', function (): void {
        $this->get(route('home.index'))
            ->assertRedirect(route('login'));
    });

    test('guest is sent to login on /home/{stone}', function (): void {
        $stone = Stone::query()->first();

        $this->get(route('home.stone', $stone->slug))
            ->assertRedirect(route('login'));
    });

    test('guest is sent to login on /home/{stone}/{project}', function (): void {
        $stone = Stone::query()
            ->with('projects')
            ->whereHas('projects')
            ->first();
        $project = $stone->projects->first();

        $this->get(route('home.project', [$stone->slug, $project->slug]))
            ->assertRedirect(route('login'));
    });

    test('guest is sent to login on /saved', function (): void {
        $this->get(route('saved.index'))
            ->assertRedirect(route('login'));
    });

    test('guest is sent to login on /saved/{stone}', function (): void {
        $stone = Stone::query()->first();

        $this->get(route('saved.show', $stone->slug))
            ->assertRedirect(route('login'));
    });

    test('guest is sent to login on /saved/{stone}/{project}', function (): void {
        $stone = Stone::query()
            ->with('projects')
            ->whereHas('projects')
            ->first();
        $project = $stone->projects->first();

        $this->get(route('saved.project', [$stone->slug, $project->slug]))
            ->assertRedirect(route('login'));
    });
});

describe('Invalid slugs return 404', function (): void {
    test('invalid stone slug returns 404 on /home/{stone}', function (): void {
        $user = User::factory()->role(Role::Client)->create();

        $this->actingAs($user)
            ->get(route('home.stone', 'invalid-slug-that-does-not-exist'))
            ->assertNotFound();
    });

    test('invalid stone slug returns 404 on /saved/{stone}', function (): void {
        $user = User::factory()->role(Role::Client)->create();

        $this->actingAs($user)
            ->get(route('saved.show', 'invalid-slug-that-does-not-exist'))
            ->assertNotFound();
    });

    test('invalid project slug returns 404 on /home/{stone}/{project}', function (): void {
        $user = User::factory()->role(Role::Client)->create();
        $stone = Stone::query()->first();

        $this->actingAs($user)
            ->get(route('home.project', [$stone->slug, 'invalid-project']))
            ->assertNotFound();
    });

    test('invalid project slug returns 404 on /saved/{stone}/{project}', function (): void {
        $user = User::factory()->role(Role::Client)->create();
        $stone = Stone::query()->first();

        $this->actingAs($user)
            ->get(route('saved.project', [$stone->slug, 'invalid-project']))
            ->assertNotFound();
    });

    test('malformed stone slug returns 404 on /home/{stone}', function (): void {
        $user = User::factory()->role(Role::Client)->create();

        $this->actingAs($user)
            ->get(route('home.stone', 'invalid_slug_with_underscores'))
            ->assertNotFound();
    });

    test('malformed stone slug returns 404 on /saved/{stone}', function (): void {
        $user = User::factory()->role(Role::Client)->create();

        $this->actingAs($user)
            ->get(route('saved.show', 'invalid_slug_with_underscores'))
            ->assertNotFound();
    });
});
