<?php

use App\Enums\Permission;
use App\Enums\Role;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Brand;
use App\Models\Showcase;
use App\Models\ShowcaseCategory;
use App\Models\Stone;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\ShowcaseCategorySeeder;
use Database\Seeders\ShowcaseSeeder;
use Database\Seeders\ShowcaseStonesSeeder;
use Database\Seeders\StoneSeeder;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role as RoleModel;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        StoneSeeder::class,
        ShowcaseCategorySeeder::class,
        ShowcaseSeeder::class,
    ]);
});

test('a client reads the published database profile with ordered public fields', function (): void {
    $showcase = Showcase::query()->where('slug', 'belo-medical-group')->firstOrFail();
    $showcase->update([
        'name' => 'Database showcase',
        'completed_year' => 2026,
        'location' => 'Database location',
        'overview' => 'Database overview',
        'thumbnail_path' => 'projects/profile.jpg',
    ]);
    $images = $showcase->images()->orderBy('sort_order')->get();
    $images[0]->update(['path' => 'projects/last.jpg', 'sort_order' => 20]);
    $images[1]->update(['path' => '/storage/projects/middle.jpg', 'sort_order' => 10]);
    $images[2]->update(['path' => 'projects/first.jpg', 'sort_order' => 5, 'original_name' => 'Kitchen island.jpg']);
    Stone::query()->where('slug', 'star-white')->update(['sort_order' => -1, 'thumbnail_path' => 'stones/patagonia.jpg']);
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)
        ->get(route('home.show', 'belo-medical-group'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('showcase-profile')
            ->missing('showcaseId')
            ->has('showcase', fn (Assert $resource) => $resource
                ->has('data', fn (Assert $data) => $data
                    ->where('slug', 'belo-medical-group')
                    ->where('name', 'Database showcase')
                    ->where('completedYear', 2026)
                    ->where('location', 'Database location')
                    ->where('overview', 'Database overview')
                    ->where('thumbnail', '/storage/projects/profile.jpg')
                    ->where('categories', [
                        ['slug' => 'wall-accents', 'name' => 'Wall Accents'],
                        ['slug' => 'commercial-spaces', 'name' => 'Commercial Spaces'],
                    ])
                    ->has('stones', 2)
                    ->where('stones.0', ['slug' => 'star-white', 'name' => 'Star White', 'thumbnail' => '/storage/stones/patagonia.jpg', 'type' => ['slug' => 'marble', 'name' => 'Marble']])
                    ->where('gallery', [
                        // Its file's own name is its alt text; a committed photo has none.
                        ['src' => '/storage/projects/first.jpg', 'name' => 'Kitchen island'],
                        ['src' => '/storage/projects/middle.jpg', 'name' => null],
                        ['src' => '/storage/projects/last.jpg', 'name' => null],
                    ])
                )
            )
        );
});

test('every seeded showcase uses one or two of its listed stones', function (): void {
    $data = require database_path('seeders/data/showcases.php');

    foreach ($data['showcases'] as $showcaseData) {
        $stoneSlugs = Showcase::query()->where('slug', $showcaseData['slug'])->firstOrFail()
            ->stones()->pluck('slug')->sort()->values()->all();

        expect($stoneSlugs)->toBe(collect($showcaseData['stone_slugs'])->sort()->values()->all())
            ->and(count($stoneSlugs))->toBeBetween(1, 2);
    }
});

test('profile stones with the same sort order are ordered by name', function (): void {
    Stone::query()->whereIn('slug', ['royal-white-iii', 'star-white'])->update(['sort_order' => 0]);
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)->get(route('home.show', 'belo-medical-group'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('showcase.data.stones.0.slug', 'royal-white-iii')
            ->where('showcase.data.stones.1.slug', 'star-white')
        );
});

test('inactive stones and categories are omitted from a profile', function (): void {
    Stone::query()->where('slug', 'royal-white-iii')->update(['is_active' => false]);
    ShowcaseCategory::query()->where('slug', 'wall-accents')->update(['is_active' => false]);
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)->get(route('home.show', 'belo-medical-group'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('showcase.data.stones', 1)
            ->where('showcase.data.stones.0.slug', 'star-white')
            ->where('showcase.data.categories', [
                ['slug' => 'commercial-spaces', 'name' => 'Commercial Spaces'],
            ])
        );
});

test('stones of an archived brand are omitted from a profile, whose pages are gone', function (): void {
    $stone = Stone::query()->where('slug', 'royal-white-iii')->firstOrFail();
    $stone->update(['brand_id' => Brand::factory()->create(['archived_at' => now()])->id]);
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)->get(route('home.show', 'belo-medical-group'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('showcase.data.stones', 1)
            ->where('showcase.data.stones.0.slug', 'star-white')
        );
});

test('a showcase profile does not depend on the state of any stone', function (string $state): void {
    $stone = Stone::query()->where('slug', 'star-white')->firstOrFail();
    if ($state === 'inactive') {
        $stone->update(['is_active' => false]);
    } else {
        $stone->delete();
    }
    $user = User::factory()->role(Role::Client)->create();

    $this->actingAs($user)->get(route('home.show', 'belo-medical-group'))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->component('showcase-profile'));
})->with(['inactive', 'deleted']);

test('stone and showcase profiles keep independent permission guards', function (Permission $permission, string $allowed, string $denied): void {
    $role = RoleModel::create(['name' => 'catalogue-single-door', 'guard_name' => RolePermissionSeeder::GUARD]);
    $role->givePermissionTo($permission->value);
    $user = User::factory()->role($role->name)->create();

    $this->actingAs($user)->get($allowed)->assertOk();
    $this->actingAs($user)->get($denied)->assertForbidden();
})->with([
    [Permission::HomeStoneView, '/dashboard/brands/regray-stone/stones/arctic-pink', '/dashboard/home/ginza-one'],
    [Permission::HomeShowcasesView, '/dashboard/home/ginza-one', '/dashboard/brands/regray-stone/stones/arctic-pink'],
]);

test('moves a flat URL to the stone when a stone and a showcase share its slug', function (): void {
    Showcase::query()->where('slug', 'belo-medical-group')->update(['slug' => 'arctic-pink']);

    $this->actingAs(User::factory()->role(Role::Client)->create())
        ->get('/dashboard/home/arctic-pink')
        ->assertStatus(301)
        ->assertRedirect('/dashboard/brands/regray-stone/stones/arctic-pink');
});

test('moves the old nested stone-and-showcase URL to the flat showcase URL for good', function (): void {
    $this->actingAs(User::factory()->role(Role::Client)->create())
        ->get('/dashboard/home/arctic-pink/ginza-one')
        ->assertStatus(301)
        ->assertRedirect('/dashboard/home/ginza-one');
});

test('shows the not-found page for an unknown, unpublished or deleted showcase, on a visit too, and for an unknown path even to a guest', function (): void {
    $user = User::factory()->role(Role::Client)->create();
    $notFound = fn (string $slug) => $this->actingAs($user)->get(route('home.show', $slug))
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page->component('not-found'));

    $notFound('no-such-showcase');

    Showcase::query()->where('slug', 'belo-medical-group')->update(['is_published' => false]);
    $notFound('belo-medical-group');

    Showcase::query()->where('slug', 'belo-medical-group')->firstOrFail()->delete();
    $notFound('belo-medical-group');

    $this->actingAs($user)->get(route('home.show', 'no-such-showcase'), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(Request::create('/')),
        'X-Requested-With' => 'XMLHttpRequest',
    ])->assertNotFound()->assertJsonPath('component', 'not-found');

    auth()->logout();
    $this->get('/no-such-page')->assertNotFound()->assertInertia(fn (Assert $page) => $page->component('not-found'));
});

test('each project lists the stones it used', function (): void {
    $stones = fn (string $slug): array => Showcase::query()->where('slug', $slug)->sole()->stones()->orderBy('slug')->pluck('slug')->all();

    expect($stones('belo-medical-group'))->toBe(['royal-white-iii', 'star-white'])
        ->and($stones('ayala-land'))->toBe(['avocado-green-iii'])
        ->and($stones('recidencia-del-hamor'))->toBe(['green-crystal'])
        ->and($stones('lazy-suzy-restaurant'))->toBe(['royal-white-iii'])
        ->and($stones('ginza-one'))->toBe(['warrior'])
        ->and($stones('restaurant-idalia'))->toBe(['pardo-brown-i']);
});

test('corrects the stones of a database in use, and an overview still as first seeded, leaving an admin\'s own', function (): void {
    $ginza = Showcase::query()->where('slug', 'ginza-one')->sole();
    $belo = Showcase::query()->where('slug', 'belo-medical-group')->sole();
    // As a database seeded before had them.
    $ginza->stones()->sync(Stone::query()->whereIn('slug', ['glacier-white', 'arancia'])->pluck('id'));
    $ginza->update(['overview' => 'Ginza One is a commercial spaces showcase on Chino Roces Ext., Makati City, completed in 2024. The showcase spans wall accents as well as countertops & backsplash, and features patagonia quartzite and onyx. Explore the stones used in this showcase below.']);
    $belo->update(['overview' => 'Written on the Projects page.']);

    $this->seed(ShowcaseStonesSeeder::class);

    expect($ginza->stones()->pluck('slug')->all())->toBe(['warrior'])
        ->and($ginza->fresh()->overview)->toContain('features patagonia quartzite. Explore the stone used')
        ->and($belo->fresh()->overview)->toBe('Written on the Projects page.');
});
