<?php

use App\Enums\AuditEvent;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Project;
use App\Models\Stone;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Archiving a project: it leaves the Projects page and its page goes offline
 * everywhere a project is read. A saved stone's projects keep showing it, as
 * history. Its rows, files and slug stay.
 */

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->role(Role::Admin)->create();
    $this->client = User::factory()->role(Role::Client)->create();
    $this->disk = Storage::fake(config('filesystems.public_disk'));
    $this->disk->put('projects/acme-cover.jpg', 'cover');
    $this->disk->put('projects/acme-photo.jpg', 'photo');

    $this->brand = Brand::factory()->create(['slug' => 'acme-house']);
    $this->stone = Stone::factory()->for($this->brand)->create(['slug' => 'acme-stone']);
    $this->other = makeProject(['slug' => 'other-project', 'name' => 'Other Project', 'completed_year' => 2020]);
    $this->project = makeProject([
        'slug' => 'archived-acme',
        'name' => 'Archived Acme',
        'thumbnail_path' => 'projects/acme-cover.jpg',
        'completed_year' => 2025,
    ]);
    $this->project->stones()->attach($this->stone->id);
    $this->project->images()->create(['path' => 'projects/acme-photo.jpg', 'sort_order' => 0]);
    $this->archive = fn () => $this->actingAs($this->admin)->post(route('dashboard.projects.archive', $this->project->id));
});

/** Whether the page's props mention the archived project at all. */
function mentionsArchivedProject(TestResponse $response): bool
{
    return str_contains(json_encode($response->viewData('page')['props']), 'archived-acme');
}

it('takes it off the Projects page, keeps its rows and files, and audits it', function (): void {
    ($this->archive)()
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Archived Acme is archived.']);

    $this->actingAs($this->admin)
        ->get(route('projects.index'))
        ->assertInertia(fn (Assert $page) => $page->where('projects.meta.total', 1)->where('projects.data.0.slug', 'other-project'));

    $project = Project::query()->withTrashed()->findOrFail($this->project->id);

    expect($project->deleted_at)->not->toBeNull()
        ->and($project->thumbnail_path)->toBe('projects/acme-cover.jpg')
        ->and($project->images()->count())->toBe(1)
        ->and($project->stones()->count())->toBe(1)
        ->and(AuditLog::query()->where('event', AuditEvent::Archived)->sole()->auditable_id)->toBe($this->project->id);

    $this->disk->assertExists('projects/acme-cover.jpg');
    $this->disk->assertExists('projects/acme-photo.jpg');

    // Gone from the page, so it is not found to archive or edit again.
    ($this->archive)()->assertNotFound();
});

it('takes its page offline', function (): void {
    $this->get(route('stone.show', 'archived-acme'))->assertOk();
    $this->actingAs($this->client)->get(route('home.show', 'archived-acme'))->assertOk();

    ($this->archive)();

    $this->actingAs($this->client)->get(route('home.show', 'archived-acme'))->assertNotFound();
    auth()->logout();
    $this->get(route('stone.show', 'archived-acme'))->assertNotFound();
});

it('takes it off every page that lists projects', function (string $route, array $parameters, bool $signedIn): void {
    $visit = fn () => $signedIn
        ? $this->actingAs($this->client)->get(route($route, $parameters))
        : $this->get(route($route, $parameters));

    expect(mentionsArchivedProject($visit()))->toBeTrue();

    ($this->archive)();

    if (! $signedIn) {
        auth()->logout();
    }

    expect(mentionsArchivedProject($visit()))->toBeFalse();
})->with([
    'the landing page' => ['home', [], false],
    'the stone\'s page' => ['brands.stones.show', ['brand' => 'acme-house', 'stone' => 'acme-stone'], false],
]);

it('keeps it on a saved stone\'s projects, as history', function (): void {
    $this->actingAs($this->client)->put(route('saved.store', 'acme-stone'))->assertRedirect();

    ($this->archive)();

    $this->actingAs($this->client)
        ->get(route('saved.project', ['stone' => 'acme-stone', 'project' => 'archived-acme']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('project.data.slug', 'archived-acme'));
});

it('keeps its slug, so no new project takes it', function (): void {
    ($this->archive)();

    expect(Project::query()->withTrashed()->where('slug', 'archived-acme')->count())->toBe(1);
});

it('lets only those allowed archive', function (): void {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(Permission::ProjectsView->value, Permission::ProjectsUpdate->value);

    $this->actingAs($viewer)->post(route('dashboard.projects.archive', $this->project->id))->assertForbidden();
    // The permission is checked before the project is looked up.
    $this->actingAs($viewer)->post(route('dashboard.projects.archive', 999999))->assertForbidden();

    expect($this->project->refresh()->trashed())->toBeFalse();
});
