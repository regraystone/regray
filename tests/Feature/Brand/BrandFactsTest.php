<?php

use App\Models\Brand;
use App\Models\BrandFact;
use App\Models\BrandMake;
use App\Models\BrandSocial;
use App\Support\StoredFileName;
use Database\Seeders\BrandSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * A brand's facts, what it makes and its social links are rows of their own
 * (brand_facts, brand_makes, brand_socials), set on the admin's
 * Brands page, and its page reads them from there. The six brands' own were
 * hard-coded by slug in public-links.ts; they are in the seed data now, which
 * the migration copied them from.
 */

it('copies the six brands\' facts and what they make, and their pages show them', function (): void {
    $this->seed(BrandSeeder::class);

    expect(Brand::query()->orderBy('sort_order')->get()->mapWithKeys(fn (Brand $brand): array => [
        $brand->slug => [$brand->facts()->count(), $brand->makes()->count()],
    ])->all())->toBe([
        'regray-stone' => [3, 3],
        'cucine' => [4, 1],
        'ascale' => [2, 4],
        'faer' => [2, 4],
        'reform' => [1, 2],
        'res-italia' => [2, 2],
    ]);

    $this->get(route('brands.show', 'cucine'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('brand.data.facts', [
                ['label' => 'Made in', 'value' => 'Treia, Italy'],
                ['label' => 'Founded', 'value' => '1967'],
                ['label' => 'Output', 'value' => 'Around 75,000 kitchens a year'],
                ['label' => 'Sold in', 'value' => '76 countries'],
            ])
            ->where('brand.data.makes', ['Kitchens']));

    $this->get(route('brands.show', 'ascale'))
        ->assertInertia(fn (Assert $page) => $page->where('brand.data.makes', ['Countertops', 'Floors', 'Walls', 'Façades']));
});

it('starts each seeded brand with the three social links its page showed, and shows a brand\'s own', function (): void {
    $this->seed(BrandSeeder::class);
    $regray = [
        ['platform' => 'facebook', 'url' => 'https://www.facebook.com/Regraystone'],
        ['platform' => 'instagram', 'url' => 'https://www.instagram.com/regraystoneph/'],
        ['platform' => 'tiktok', 'url' => 'https://www.tiktok.com/@regraystone'],
    ];

    expect(Brand::query()->count())->toBe(6)
        ->and(Brand::query()->with(Brand::DETAILS)->get()->every(fn (Brand $brand): bool => $brand->details()['socials'] === $regray))->toBeTrue();

    $reform = Brand::query()->with(Brand::DETAILS)->where('slug', 'reform')->sole();
    $reform->replaceDetails([...$reform->details(), 'socials' => [['platform' => 'twitter', 'url' => 'https://twitter.com/reform']]]);

    // Seeding again puts the data's back, without doubling anything.
    $this->seed(BrandSeeder::class);
    expect(BrandSocial::query()->count())->toBe(18);
    $reform->replaceDetails([...$reform->details(), 'socials' => [['platform' => 'twitter', 'url' => 'https://twitter.com/reform']]]);

    $this->get(route('brands.show', 'cucine'))->assertInertia(fn (Assert $page) => $page->where('brand.data.socials', $regray));
    $this->get(route('brands.show', 'reform'))->assertInertia(fn (Assert $page) => $page->where('brand.data.socials', [['platform' => 'twitter', 'url' => 'https://twitter.com/reform']]));
});

it('gives a brand with none an empty list of each', function (): void {
    $brand = Brand::factory()->create(['slug' => 'new-house']);

    expect($brand->load(Brand::DETAILS)->details())->toBe(['facts' => [], 'makes' => [], 'socials' => []]);

    $this->get(route('brands.show', 'new-house'))
        ->assertInertia(fn (Assert $page) => $page->where('brand.data.facts', [])->where('brand.data.makes', [])->where('brand.data.socials', []));
});

it('holds the limits in the database itself', function (Closure $write): void {
    $brand = Brand::factory()->create();

    // In a savepoint: a refused write ends the test's transaction.
    expect(fn () => DB::transaction(fn () => $write($brand)))->toThrow(QueryException::class);
})->with([
    'a seventh fact' => fn (Brand $brand) => $brand->facts()->create(['label' => 'A', 'value' => 'B', 'sort_order' => 6]),
    'two facts in one place' => fn (Brand $brand) => $brand->facts()->createMany([['label' => 'A', 'value' => 'B', 'sort_order' => 0], ['label' => 'C', 'value' => 'D', 'sort_order' => 0]]),
    'a ninth tag' => fn (Brand $brand) => $brand->makes()->create(['name' => 'Doors', 'sort_order' => 8]),
    'one tag twice, in another case' => fn (Brand $brand) => $brand->makes()->createMany([['name' => 'Doors', 'sort_order' => 0], ['name' => 'DOORS', 'sort_order' => 1]]),
    'a network not offered' => fn (Brand $brand) => DB::table('brand_socials')->insert(['brand_id' => $brand->id, 'platform' => 'linkedin', 'url' => 'https://linkedin.com/a', 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now()]),
    'two links for one network' => fn (Brand $brand) => $brand->socials()->createMany([['platform' => 'x', 'url' => 'https://x.com/a', 'sort_order' => 0], ['platform' => 'x', 'url' => 'https://x.com/b', 'sort_order' => 1]]),
    'a sixth link' => fn (Brand $brand) => $brand->socials()->create(['platform' => 'x', 'url' => 'https://x.com/a', 'sort_order' => 5]),
]);

it('deletes a brand\'s detail rows with it', function (): void {
    $brand = Brand::factory()->create();
    $brand->replaceDetails(['facts' => [['label' => 'A', 'value' => 'B']], 'makes' => ['Doors'], 'socials' => [['platform' => 'x', 'url' => 'https://x.com/a']]]);

    $brand->delete();

    expect(BrandFact::query()->count() + BrandMake::query()->count() + BrandSocial::query()->count())->toBe(0);
});

it('names every stored upload by a UUID and the type read from its bytes, never the uploader\'s name', function (): void {
    // A real upload, so its type is read from its bytes: a fake one is typed by its name.
    $path = tempnam(sys_get_temp_dir(), 'upload');
    file_put_contents($path, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
    $pdf = new UploadedFile($path, 'report.html', null, null, true);

    expect(StoredFileName::for(UploadedFile::fake()->image('My Logo.PNG')))->toMatch('/^[0-9a-f-]{36}\.png$/')
        ->and(StoredFileName::for($pdf))->toMatch('/^[0-9a-f-]{36}\.pdf$/');
});
