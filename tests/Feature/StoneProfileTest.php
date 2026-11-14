<?php

use App\Actions\Home\ShowProjectAction;
use App\Actions\Stone\ShowStoneAction;
use App\Enums\Role;
use App\Models\Application;
use App\Models\CareTip;
use App\Models\Finish;
use App\Models\Stone;
use App\Models\StoneType;
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

test('a client reads the full database stone profile through either door', function (string $door): void {
    $stone = Stone::query()->where('slug', 'cosmic-black')->firstOrFail();
    $type = $stone->type;
    $type->update(['description' => 'Database description']);
    // Its slabs, out of order: grouped by size in variant order, thinnest first within a size.
    $stone->variants()->delete();
    $slabs = Stone::factory()->withVariants([
        [['3.15', '1.98', 'm'], [30, 'mm']],
        [['3.15', '1.98', 'm'], [20, 'mm']],
        [[60, 120, 'cm'], [20, 'mm']],
        [['3.15', '1.98', 'm'], [0.75, 'in']],
    ])->create();
    $slabs->variants()->update(['stone_id' => $stone->id]);
    $stone->update([
        'name' => 'Database Granite',
        'origin' => 'Database origin',
        'thumbnail_path' => 'stones/hero.jpg',
    ]);
    $stone->images()->create(['path' => 'stones/second.jpg', 'sort_order' => 1]);
    $images = $stone->images()->orderBy('sort_order')->get();
    $images[0]->update(['alt_text' => 'Last photo', 'path' => '/storage/stones/last.jpg', 'sort_order' => 50]);
    $images[1]->update(['alt_text' => 'First photo', 'path' => 'stones/first.jpg', 'sort_order' => -1]);

    $lastApplication = Application::create(['name' => 'Last application']);
    $firstApplication = Application::create(['name' => 'First application']);
    $type->applications()->sync([
        $lastApplication->id => ['sort_order' => 9],
        $firstApplication->id => ['sort_order' => 1],
    ]);
    $lastTip = CareTip::create(['tip' => 'Last care tip']);
    $firstTip = CareTip::create(['tip' => 'First care tip']);
    $type->careTips()->sync([
        $lastTip->id => ['sort_order' => 9],
        $firstTip->id => ['sort_order' => 1],
    ]);
    $lastFinish = Finish::create(['name' => 'Last finish', 'is_active' => true, 'is_advisory' => false, 'sort_order' => 0]);
    $firstFinish = Finish::create(['name' => 'First finish', 'is_active' => true, 'is_advisory' => false, 'sort_order' => 9]);
    $inactiveFinish = Finish::create(['name' => 'Inactive finish', 'is_active' => false, 'is_advisory' => false, 'sort_order' => 0]);
    $advisoryFinish = Finish::query()->where('is_advisory', true)->firstOrFail();
    $type->finishes()->sync([
        $lastFinish->id => ['sort_order' => 9],
        $firstFinish->id => ['sort_order' => 1],
        $inactiveFinish->id => ['sort_order' => 0],
        $advisoryFinish->id => ['sort_order' => 0],
    ]);

    $user = User::factory()->role(Role::Client)->create();

    $response = $this->actingAs($user)->get($door);

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('stone-profile')
        ->missing('stoneId')
        ->missing('projectId')
        ->has('stone', fn (Assert $resource) => $resource
            ->has('data', fn (Assert $data) => $data
                ->where('slug', 'cosmic-black')
                ->where('name', 'Database Granite')
                ->where('thumbnail', '/storage/stones/hero.jpg')
                ->where('bestSeller', false)
                ->where('brand', ['slug' => 'regray-stone', 'name' => 'Regray Stone'])
                ->where('type', ['slug' => 'granite', 'name' => 'Granite'])
                ->where('description', 'Database description')
                ->where('specifications', [
                    'origin' => 'Database origin',
                    'slabs' => [
                        ['size' => '3.15 x 1.98 m', 'thicknesses' => ['20 mm', '30 mm', '0.75 in']],
                        ['size' => '60 x 120 cm', 'thicknesses' => ['20 mm']],
                    ],
                    'sizeNote' => null,
                ])
                ->where('finishes', ['First finish', 'Last finish'])
                ->where('applications', ['First application', 'Last application'])
                ->where('care', ['First care tip', 'Last care tip'])
                ->where('gallery', [
                    ['src' => '/storage/stones/first.jpg', 'name' => 'First photo'],
                    ['src' => '/storage/stones/last.jpg', 'name' => 'Last photo'],
                ])
                ->missing('projects')
            )
        )
    );

    $response->assertInertia(fn (Assert $page) => $page->missing('projectSlug'));
})->with(['/dashboard/saved/stones/cosmic-black', '/dashboard/brands/regray-stone/stones/cosmic-black']);

test('shows the not-found page for an unknown, inactive or deleted stone through both doors', function (): void {
    $user = User::factory()->role(Role::Client)->create();
    $notFound = function (string $slug) use ($user): void {
        foreach ([route('saved.show', $slug), route('dashboard.brands.stones.show', ['brand' => 'regray-stone', 'stone' => $slug])] as $url) {
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
        route('dashboard.brands.stones.show', ['brand' => 'regray-stone', 'stone' => 'onyx--stone']),
        route('home.show', ['slug' => 'Bad_Project']),
        route('home.show', ['slug' => str_repeat('a', 101)]),
    ] as $url) {
        $this->actingAs($user)->get($url)->assertNotFound()
            ->assertInertia(fn (Assert $page) => $page->component('not-found'));
    }
});

test('stones in one type share its facts but have their own galleries', function (): void {
    $type = StoneType::query()->where('slug', 'granite')->firstOrFail();
    $type->update(['description' => 'Shared type description']);
    $user = User::factory()->role(Role::Client)->create();

    foreach (['cosmic-black', 'golden-fusion'] as $slug) {
        $this->actingAs($user)->get(route('dashboard.brands.stones.show', ['brand' => 'regray-stone', 'stone' => $slug]))->assertInertia(fn (Assert $page) => $page
            ->where('stone.data.slug', $slug)
            ->where('stone.data.description', 'Shared type description')
            ->where('stone.data.type', ['slug' => 'granite', 'name' => 'Granite'])
            ->has('stone.data.gallery', 1)
        );
    }
});

test('saving and unsaving uses the individual stone and exposes its type', function (): void {
    $user = User::factory()->role(Role::Client)->create();
    $stone = Stone::query()->where('slug', 'cosmic-black')->firstOrFail();

    $this->actingAs($user)->from(route('home.index'))->put(route('saved.store', $stone->slug))->assertRedirect(route('home.index'));

    $this->assertDatabaseHas('saved_stones', ['user_id' => $user->id, 'stone_id' => $stone->id]);
    $this->actingAs($user)->get(route('saved.index'))->assertInertia(fn (Assert $page) => $page
        ->has('savedStones.data', 1)
        ->where('savedStones.data.0.slug', 'cosmic-black')
        ->where('savedStones.data.0.type', ['slug' => 'granite', 'name' => 'Granite'])
    );
    $this->actingAs($user)->delete(route('saved.destroy', $stone->slug))->assertRedirect();
    $this->assertDatabaseMissing('saved_stones', ['user_id' => $user->id, 'stone_id' => $stone->id]);
});

test('a stone sold without set sizes lists its pieces by their note', function (): void {
    $stone = Stone::query()->whereHas('type', fn ($query) => $query->where('slug', 'crazy-cut'))->orderBy('sort_order')->firstOrFail();

    $this->actingAs(User::factory()->role(Role::Client)->create())
        ->get(route('dashboard.brands.stones.show', ['brand' => 'regray-stone', 'stone' => $stone->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('stone.data.specifications.slabs', [['size' => null, 'thicknesses' => ['2 cm', '3 cm', '4 cm']]])
            ->where('stone.data.specifications.sizeNote', 'Diameter 15–40 cm')
        );
});
