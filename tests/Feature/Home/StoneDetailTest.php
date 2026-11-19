<?php

use App\Enums\Role;
use App\Models\Project;
use App\Models\Stone;
use App\Models\User;
use Database\Seeders\BrandSeeder;
use Database\Seeders\ProjectCategorySeeder;
use Database\Seeders\ProjectSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\StoneSeeder;
use Illuminate\Support\Collection;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * A stone's page shows, around the stone, the stones before and after it of
 * its brand and type, more of that type, and the published projects it was
 * used in, through every door it has.
 */
beforeEach(function (): void {
    $this->seed([RolePermissionSeeder::class, BrandSeeder::class, StoneSeeder::class, ProjectCategorySeeder::class, ProjectSeeder::class]);
});

/** The active stones of the type with the most of them, in catalog order. */
function stonesOfBusiestType(): Collection
{
    $typeId = Stone::query()->where('is_active', true)
        ->selectRaw('stone_type_id, count(*) as total')
        ->groupBy('stone_type_id')
        ->orderByDesc('total')
        ->value('stone_type_id');

    return Stone::query()->where('is_active', true)->where('stone_type_id', $typeId)->orderBy('sort_order')->orderBy('name')->pluck('slug')->values();
}

function stonePage(string $slug): TestResponse
{
    return test()->get(route('brands.stones.show', ['brand' => 'regray-stone', 'stone' => $slug]));
}

test('the page steps to the previous and next stones of the same type, and lists more of it', function (): void {
    $stones = stonesOfBusiestType();

    stonePage($stones[1])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('stone-profile')
            ->where('previous.slug', $stones[0])
            ->where('next.slug', $stones[2])
            ->has('related', min(4, $stones->count() - 1))
            ->where('related', fn ($related): bool => collect($related)->doesntContain('slug', $stones[1]))
            ->has('related.0', fn (Assert $card) => $card->hasAll(['slug', 'name', 'thumbnail', 'brand', 'type'])->etc()));

    stonePage($stones[0])->assertInertia(fn (Assert $page) => $page->where('previous', null));
});

test('an inactive stone is neither a neighbour nor related', function (): void {
    $stones = stonesOfBusiestType();
    Stone::query()->where('slug', $stones[2])->update(['is_active' => false]);

    stonePage($stones[1])
        ->assertInertia(fn (Assert $page) => $page
            ->where('next.slug', fn (?string $slug): bool => $slug !== $stones[2])
            ->where('related', fn ($related): bool => collect($related)->doesntContain('slug', $stones[2])));
});

test('the page lists the published projects the stone was used in', function (): void {
    $stone = Stone::query()->has('projects')->with('projects')->firstOrFail();
    $project = $stone->projects->first();

    stonePage($stone->slug)
        ->assertInertia(fn (Assert $page) => $page
            ->where('projects', fn ($projects): bool => collect($projects)->contains('slug', $project->slug))
            ->has('projects.0', fn (Assert $card) => $card->hasAll(['slug', 'name', 'location', 'completedYear', 'thumbnail'])));

    Project::query()->whereKey($project->id)->update(['is_published' => false]);

    stonePage($stone->slug)
        ->assertInertia(fn (Assert $page) => $page->where('projects', fn ($projects): bool => collect($projects)->doesntContain('slug', $project->slug)));
});

test('the saved door and the dashboard door show the same surroundings', function (): void {
    $stones = stonesOfBusiestType();
    $client = User::factory()->role(Role::Client)->create();

    foreach ([route('saved.show', $stones[1]), route('dashboard.brands.stones.show', ['brand' => 'regray-stone', 'stone' => $stones[1]])] as $url) {
        $this->actingAs($client)->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('stone-profile')
                ->where('previous.slug', $stones[0])
                ->where('next.slug', $stones[2])
                ->has('related')
                ->has('projects'));
    }
});
