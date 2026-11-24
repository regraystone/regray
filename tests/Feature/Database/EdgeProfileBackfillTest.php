<?php

use App\Models\EdgeProfile;
use App\Models\StoneType;
use Database\Seeders\EdgeProfileSeeder;
use Illuminate\Support\Facades\Schema;

/*
 * The edge profiles each stone type starts with: the migration that added
 * them, and EdgeProfileSeeder on a new database, give every type every edge
 * profile but the "advise me" one, in the list's order. Once types have
 * their own, set on the Stone types page, the seeder leaves them be, a type
 * emptied on purpose included.
 */

beforeEach(function (): void {
    $this->straight = EdgeProfile::query()->create(['name' => 'Straight', 'is_advisory' => false, 'is_active' => true, 'sort_order' => 1]);
    $this->bevel = EdgeProfile::query()->create(['name' => 'Bevel', 'is_advisory' => false, 'is_active' => true, 'sort_order' => 0]);
    EdgeProfile::query()->create(['name' => 'Not sure yet, advise me', 'is_advisory' => true, 'is_active' => true, 'sort_order' => 2]);
    EdgeProfile::query()->create(['name' => 'Retired', 'is_advisory' => false, 'is_active' => true, 'sort_order' => 3])->delete();
    $this->marble = StoneType::factory()->create();
    $this->granite = StoneType::factory()->create();
    StoneType::factory()->create()->delete();
});

it('gives every stone type every edge profile but the "advise me" one, in order, when migrated', function (): void {
    $migration = require database_path('migrations/2026_11_22_000009_create_edge_profile_stone_type_table.php');
    Schema::drop('edge_profile_stone_type');

    $migration->up();

    expect($this->marble->edgeProfiles()->get()->modelKeys())->toBe([$this->bevel->id, $this->straight->id])
        ->and($this->granite->edgeProfiles()->get()->modelKeys())->toBe([$this->bevel->id, $this->straight->id])
        ->and(DB::table('edge_profile_stone_type')->count())->toBe(4);
});

it('does the same on a new database when seeded', function (): void {
    DB::table('edge_profile_stone_type')->delete();

    $this->seed(EdgeProfileSeeder::class);
    // The seeder adds its own profiles to the list first.
    $offered = EdgeProfile::query()->where('is_advisory', false)->orderBy('sort_order')->orderBy('id')->pluck('id')->all();

    expect($offered)->toContain($this->bevel->id, $this->straight->id)
        ->and($this->marble->edgeProfiles()->get()->modelKeys())->toBe($offered)
        ->and($this->granite->edgeProfiles()->get()->modelKeys())->toBe($offered);
});

it('leaves every type\'s own edge profiles when seeded again, one emptied on purpose included', function (): void {
    DB::table('edge_profile_stone_type')->delete();
    $this->marble->edgeProfiles()->sync([$this->straight->id => ['sort_order' => 0]]);

    $this->seed(EdgeProfileSeeder::class);

    expect($this->marble->edgeProfiles()->get()->modelKeys())->toBe([$this->straight->id])
        ->and($this->granite->edgeProfiles()->count())->toBe(0);
});
