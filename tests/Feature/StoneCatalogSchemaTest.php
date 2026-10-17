<?php

use App\Models\Stone;
use App\Models\StoneCategory;
use App\Models\StoneImage;
use Database\Seeders\StoneSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

test('the catalogue seeds nine categories and all seventy-five individual stones and galleries', function (): void {
    $this->seed(StoneSeeder::class);
    $this->seed(StoneSeeder::class);

    $this->assertDatabaseCount('stone_categories', 9);
    $this->assertDatabaseCount('stones', 75);
    $this->assertDatabaseCount('stone_images', 75);
    expect(Stone::query()->whereDoesntHave('images')->count())->toBe(0);
    expect(Stone::query()->where('slug', 'flamed-brushed-black-monumental')->firstOrFail()->name)->toBe('Flamed + Brushed - Black (Monumental)');
    expect(Stone::query()->orderBy('sort_order')->limit(4)->pluck('size')->all())->toBe(['20x120', '60x60', '60x120', '80x80']);
});

test('stone sizes and thickness are enforced by the database', function (array $invalid, string $constraint): void {
    $category = StoneCategory::factory()->create();

    expect(fn () => DB::transaction(fn () => Stone::factory()->for($category, 'category')->create($invalid)))
        ->toThrow(QueryException::class, $constraint);
})->with([
    [['size' => '20X120'], 'chk_stones_size'],
    [['size' => '20 x120'], 'chk_stones_size'],
    [['size' => '20.5x120'], 'chk_stones_size'],
    [['size' => '20x120\n'], 'chk_stones_size'],
    [['thickness_mm' => 0], 'chk_stones_thickness_positive'],
    [['thickness_mm' => -1], 'chk_stones_thickness_positive'],
]);

test('a category cannot be removed while an individual stone references it', function (): void {
    $stone = Stone::factory()->create();

    expect(fn () => DB::transaction(fn () => $stone->category->forceDelete()))->toThrow(QueryException::class);
    $this->assertModelExists($stone);
});

test('gallery images cascade with their stone and positions are unique per stone', function (): void {
    $stone = Stone::factory()->create();
    $image = $stone->images()->create(['path' => 'stones/gallery.jpg', 'sort_order' => 0]);

    expect(fn () => DB::transaction(fn () => $stone->images()->create(['path' => 'stones/duplicate.jpg', 'sort_order' => 0])))
        ->toThrow(QueryException::class, 'idx_stone_images_unique');
    $stone->forceDelete();

    expect(StoneImage::query()->find($image->id))->toBeNull();
});

test('category identifiers are unique without regard to case and reusable after soft deletion', function (): void {
    $category = StoneCategory::factory()->create(['slug' => 'granite', 'name' => 'Granite']);

    expect(fn () => DB::transaction(fn () => StoneCategory::factory()->create(['slug' => 'GRANITE'])))
        ->toThrow(QueryException::class, 'idx_stone_categories_slug_not_deleted');
    expect(fn () => DB::transaction(fn () => StoneCategory::factory()->create(['name' => 'GRANITE'])))
        ->toThrow(QueryException::class, 'idx_stone_categories_name_unique_not_deleted');
    $category->delete();
    $replacement = StoneCategory::factory()->create(['slug' => 'granite', 'name' => 'Granite']);

    $this->assertModelExists($replacement);
});
