<?php

use App\Actions\Home\ShowProjectAction;
use App\Actions\Stone\ShowStoneAction;
use App\Enums\Role;
use App\Models\Application;
use App\Models\CareTip;
use App\Models\Finish;
use App\Models\Project;
use App\Models\Stone;
use App\Models\StoneCategory;
use App\Models\User;
use Database\Seeders\ProjectCategorySeeder;
use Database\Seeders\ProjectSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\StoneSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * A stone's profile, opened from Home or Saved. Who may open which door is
 * WorkspacePagesTest's; the rules for the database rows are SchemaTest's.
 */

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        StoneSeeder::class,
        ProjectCategorySeeder::class,
        ProjectSeeder::class,
    ]);
});

test('a client reads the full database stone profile through either door', function (string $routeName): void {
    $stone = Stone::query()->where('slug', 'cosmic-black')->firstOrFail();
    $category = $stone->category;
    $category->update(['description' => 'Database description', 'origin' => 'Database origin']);
    $stone->update([
        'name' => 'Database Granite',
        'size' => '60x120',
        'thickness_mm' => 75,
        'thumbnail_path' => 'stones/hero.jpg',
        'recommended' => false,
    ]);
    $stone->images()->create(['path' => 'stones/second.jpg', 'sort_order' => 1]);
    $images = $stone->images()->orderBy('sort_order')->get();
    $images[0]->update(['alt_text' => 'Last photo', 'path' => '/storage/stones/last.jpg', 'sort_order' => 50]);
    $images[1]->update(['alt_text' => 'First photo', 'path' => 'stones/first.jpg', 'sort_order' => -1]);

    $lastApplication = Application::create(['name' => 'Last application']);
    $firstApplication = Application::create(['name' => 'First application']);
    $category->applications()->sync([
        $lastApplication->id => ['sort_order' => 9],
        $firstApplication->id => ['sort_order' => 1],
    ]);
    $lastTip = CareTip::create(['tip' => 'Last care tip']);
    $firstTip = CareTip::create(['tip' => 'First care tip']);
    $category->careTips()->sync([
        $lastTip->id => ['sort_order' => 9],
        $firstTip->id => ['sort_order' => 1],
    ]);
    $lastFinish = Finish::create(['name' => 'Last finish', 'is_active' => true, 'is_advisory' => false, 'sort_order' => 0]);
    $firstFinish = Finish::create(['name' => 'First finish', 'is_active' => true, 'is_advisory' => false, 'sort_order' => 9]);
    $inactiveFinish = Finish::create(['name' => 'Inactive finish', 'is_active' => false, 'is_advisory' => false, 'sort_order' => 0]);
    $advisoryFinish = Finish::query()->where('is_advisory', true)->firstOrFail();
    $category->finishes()->sync([
        $lastFinish->id => ['sort_order' => 9],
        $firstFinish->id => ['sort_order' => 1],
        $inactiveFinish->id => ['sort_order' => 0],
        $advisoryFinish->id => ['sort_order' => 0],
    ]);

    $ginza = Project::query()->where('slug', 'ginza-one')->firstOrFail();
    $ginza->update(['thumbnail_path' => 'projects/ginza.jpg']);
    $stone->projects()->attach($ginza);
    foreach ([
        ['slug' => 'zeta-project', 'name' => 'Zeta Project', 'completed_year' => 2026, 'is_published' => true],
        ['slug' => 'alpha-project', 'name' => 'Alpha Project', 'completed_year' => 2026, 'is_published' => true],
        ['slug' => 'older-project', 'name' => 'Older Project', 'completed_year' => 2023, 'is_published' => true],
        ['slug' => 'draft-project', 'name' => 'Draft Project', 'completed_year' => 2027, 'is_published' => false],
    ] as $attributes) {
        $stone->projects()->create([
            ...$attributes,
            'thumbnail_path' => 'projects/'.$attributes['slug'].'.jpg',
            'location' => 'Test location',
            'overview' => 'Test overview',
        ]);
    }
    $deletedProject = $stone->projects()->create([
        'slug' => 'deleted-project', 'name' => 'Deleted Project', 'completed_year' => 2027,
        'is_published' => true, 'thumbnail_path' => 'projects/deleted.jpg',
        'location' => 'Test location', 'overview' => 'Test overview',
    ]);
    $deletedProject->delete();
    $user = User::factory()->role(Role::Client)->create();

    $response = $this->actingAs($user)->get(route($routeName, ['stone' => 'cosmic-black']));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('stone-profile')
        ->missing('stoneId')
        ->missing('projectId')
        ->has('stone', fn (Assert $resource) => $resource
            ->has('data', fn (Assert $data) => $data
                ->where('slug', 'cosmic-black')
                ->where('name', 'Database Granite')
                ->where('thumbnail', '/storage/stones/hero.jpg')
                ->where('recommended', false)
                ->where('category', ['slug' => 'granite', 'name' => 'Granite'])
                ->where('description', 'Database description')
                ->where('specifications', ['stoneType' => 'Granite', 'origin' => 'Database origin', 'size' => '60x120', 'thicknessMm' => 75])
                ->where('finishes', ['First finish', 'Last finish'])
                ->where('applications', ['First application', 'Last application'])
                ->where('care', ['First care tip', 'Last care tip'])
                ->where('gallery', [
                    ['src' => '/storage/stones/first.jpg', 'name' => 'First photo'],
                    ['src' => '/storage/stones/last.jpg', 'name' => 'Last photo'],
                ])
                ->where('projects', [
                    ['slug' => 'alpha-project', 'name' => 'Alpha Project', 'thumbnail' => '/storage/projects/alpha-project.jpg'],
                    ['slug' => 'zeta-project', 'name' => 'Zeta Project', 'thumbnail' => '/storage/projects/zeta-project.jpg'],
                    ['slug' => 'ginza-one', 'name' => 'Ginza One', 'thumbnail' => '/storage/projects/ginza.jpg'],
                    ['slug' => 'older-project', 'name' => 'Older Project', 'thumbnail' => '/storage/projects/older-project.jpg'],
                ])
            )
        )
    );

    $response->assertInertia(fn (Assert $page) => $page->missing('projectSlug'));
})->with(['saved.show', 'home.stone']);

test('shows the not-found page for an unknown, inactive or deleted stone through both doors', function (): void {
    $user = User::factory()->role(Role::Client)->create();
    $notFound = function (string $slug) use ($user): void {
        foreach ([route('saved.show', $slug), route('home.stone', ['stone' => $slug])] as $url) {
            $this->actingAs($user)->get($url)->assertNotFound()
                ->assertInertia(fn (Assert $page) => $page->component('not-found'));
        }
    };

    $notFound('no-such-stone');

    Stone::query()->where('slug', 'arancia')->update(['is_active' => false]);
    $notFound('arancia');

    Stone::query()->where('slug', 'cosmic-black')->firstOrFail()->delete();
    $notFound('cosmic-black');
});

test('answers a malformed stone or project slug with 404 without looking it up', function (): void {
    $this->mock(ShowStoneAction::class)->shouldNotReceive('execute');
    $this->mock(ShowProjectAction::class)->shouldNotReceive('execute');
    $user = User::factory()->role(Role::Client)->create();

    foreach ([
        route('saved.show', ['stone' => 'Bad_Stone']),
        route('saved.show', ['stone' => str_repeat('a', 101)]),
        route('home.stone', ['stone' => 'onyx--stone']),
        route('home.show', ['slug' => 'Bad_Project']),
        route('home.show', ['slug' => str_repeat('a', 101)]),
    ] as $url) {
        $this->actingAs($user)->get($url)->assertNotFound()
            ->assertInertia(fn (Assert $page) => $page->component('not-found'));
    }
});

test('stones in one category share its facts but have their own galleries', function (): void {
    $category = StoneCategory::query()->where('slug', 'granite')->firstOrFail();
    $category->update(['description' => 'Shared category description']);
    $user = User::factory()->role(Role::Client)->create();

    foreach (['cosmic-black', 'golden-fusion'] as $slug) {
        $this->actingAs($user)->get(route('home.stone', $slug))->assertInertia(fn (Assert $page) => $page
            ->where('stone.data.slug', $slug)
            ->where('stone.data.description', 'Shared category description')
            ->where('stone.data.category', ['slug' => 'granite', 'name' => 'Granite'])
            ->has('stone.data.gallery', 1)
        );
    }
});

test('saving and unsaving uses the individual stone and exposes its category', function (): void {
    $user = User::factory()->role(Role::Client)->create();
    $stone = Stone::query()->where('slug', 'cosmic-black')->firstOrFail();

    $this->actingAs($user)->from(route('home.index'))->put(route('saved.store', $stone->slug))->assertRedirect(route('home.index'));

    $this->assertDatabaseHas('saved_stones', ['user_id' => $user->id, 'stone_id' => $stone->id]);
    $this->actingAs($user)->get(route('saved.index'))->assertInertia(fn (Assert $page) => $page
        ->has('savedStones.data', 1)
        ->where('savedStones.data.0.slug', 'cosmic-black')
        ->where('savedStones.data.0.category', ['slug' => 'granite', 'name' => 'Granite'])
    );
    $this->actingAs($user)->delete(route('saved.destroy', $stone->slug))->assertRedirect();
    $this->assertDatabaseMissing('saved_stones', ['user_id' => $user->id, 'stone_id' => $stone->id]);
});
