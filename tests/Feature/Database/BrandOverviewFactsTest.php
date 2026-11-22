<?php

use App\Models\Brand;
use Database\Seeders\BrandSeeder;

/*
 * A brand's Overview is its facts alone (and the live catalogue count), so
 * the Category and "See it at" rows the page used to add are facts now, and
 * Regray Stone's "Based in" and "Installed" go. The migration moves an
 * existing database there without touching what the admin has changed.
 */
beforeEach(function (): void {
    $this->seed(BrandSeeder::class);
    $this->migration = require database_path('migrations/2026_11_22_000004_move_brand_overview_rows_into_facts.php');
});

/** @return list<array{label: string, value: string}> */
function overviewFacts(string $slug): array
{
    return Brand::query()->where('slug', $slug)->firstOrFail()->facts()->get(['label', 'value'])
        ->map(fn ($fact): array => ['label' => $fact->label, 'value' => $fact->value])
        ->all();
}

/** @param  list<array{label: string, value: string}>  $facts */
function setOverviewFacts(string $slug, array $facts): void
{
    $brand = Brand::query()->where('slug', $slug)->firstOrFail();
    $brand->facts()->delete();

    foreach ($facts as $order => $fact) {
        $brand->facts()->create([...$fact, 'sort_order' => $order]);
    }
}

const SEE_IT_AT = ['label' => 'See it at', 'value' => 'Regray showroom, Makati City'];

test('a fresh seed gives every brand its Category and "See it at", and Regray Stone no "Based in" or "Installed"', function (): void {
    expect(overviewFacts('regray-stone'))->toBe([
        ['label' => 'Services', 'value' => 'Sourcing, fabrication and installation, nationwide'],
        ['label' => 'Category', 'value' => 'Stone & Surfaces, Furniture & Interiors'],
        SEE_IT_AT,
    ]);

    foreach (Brand::query()->pluck('slug') as $slug) {
        $labels = array_column(overviewFacts($slug), 'label');

        expect($labels)->toContain('Category', 'See it at')
            ->and(count($labels))->toBeLessThanOrEqual(config('brands.facts_max'));
    }
});

test('the old facts become the new ones, running it twice changes nothing more, and going back restores them', function (): void {
    $old = [
        ['label' => 'Based in', 'value' => 'Makati City, Philippines'],
        ['label' => 'Services', 'value' => 'Sourcing, fabrication and installation, nationwide'],
        ['label' => 'Installed', 'value' => 'Over 1,000 spaces'],
    ];
    setOverviewFacts('regray-stone', $old);
    setOverviewFacts('cucine', [['label' => 'Made in', 'value' => 'Treia, Italy']]);

    $this->migration->up();
    $this->migration->up();

    expect(overviewFacts('regray-stone'))->toBe([
        ['label' => 'Services', 'value' => 'Sourcing, fabrication and installation, nationwide'],
        ['label' => 'Category', 'value' => 'Stone & Surfaces, Furniture & Interiors'],
        SEE_IT_AT,
    ])->and(overviewFacts('cucine'))->toBe([
        ['label' => 'Made in', 'value' => 'Treia, Italy'],
        ['label' => 'Category', 'value' => 'Kitchens'],
        SEE_IT_AT,
    ]);

    $this->migration->down();

    expect(overviewFacts('regray-stone'))->toBe($old)
        ->and(overviewFacts('cucine'))->toBe([['label' => 'Made in', 'value' => 'Treia, Italy']]);
});

test('what the admin changed is kept, and a full brand gets no more', function (): void {
    setOverviewFacts('ascale', [['label' => 'See it at', 'value' => 'Our BGC gallery']]);
    $full = array_map(fn (int $n): array => ['label' => "Fact {$n}", 'value' => 'Value'], range(1, 6));
    setOverviewFacts('reform', $full);
    setOverviewFacts('regray-stone', [['label' => 'Based in', 'value' => 'Bonifacio Global City']]);

    $this->migration->up();

    expect(overviewFacts('ascale'))->toBe([
        ['label' => 'See it at', 'value' => 'Our BGC gallery'],
        ['label' => 'Category', 'value' => 'Stone & Surfaces'],
    ])->and(overviewFacts('reform'))->toBe($full)
        ->and(overviewFacts('regray-stone')[0])->toBe(['label' => 'Based in', 'value' => 'Bonifacio Global City']);
});
