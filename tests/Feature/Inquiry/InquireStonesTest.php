<?php

use App\Enums\HomeGridPageSize;
use App\Enums\InquiryStatus;
use App\Models\Stone;
use App\Models\StoneType;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    seedInquiryCatalog();

    $this->client = inquiryClient();
});

/**
 * The slugs of one page of stones on the Inquire page.
 *
 * @param  array<string, mixed>  $query
 * @return list<string>
 */
function inquireStones(mixed $test, array $query = []): array
{
    return collect($test->actingAs($test->client)->get(route('quote.inquire', $query))
        ->assertOk()
        ->viewData('page')['props']['stones']['data'])
        ->pluck('slug')
        ->all();
}

it('sends one page of stones, not all of them', function (): void {
    expect(Stone::query()->active()->count())->toBeGreaterThan(24)
        ->and(inquireStones($this))->toHaveCount(24);

    $this->actingAs($this->client)
        ->withUnencryptedCookie(HomeGridPageSize::COOKIE, '16')
        ->get(route('quote.inquire'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('stones.data', 16)
            ->where('stoneFilters.perPage', 16));
});

it('sends the next page, with no stone twice', function (): void {
    $first = inquireStones($this);
    $second = inquireStones($this, ['page' => 2]);

    expect($second)->not->toBeEmpty()
        ->and(array_intersect($first, $second))->toBeEmpty();
});

it('offers the stone types of the active stones', function (): void {
    $empty = StoneType::factory()->create();

    $types = $this->actingAs($this->client)->get(route('quote.inquire'))->viewData('page')['props']['stoneTypes'];

    expect(collect($types)->pluck('value'))
        ->toContain(Stone::query()->active()->first()->type->slug)
        ->not->toContain($empty->slug);
});

it('filters by stone type', function (): void {
    $type = StoneType::query()->where('slug', 'marble')->firstOrFail();

    expect(inquireStones($this, ['stone_type' => 'marble']))
        ->not->toBeEmpty()
        ->each(fn ($slug) => $slug->toBeIn(Stone::query()->where('stone_type_id', $type->id)->pluck('slug')->all()));
});

it('combines the stone type with Saved', function (): void {
    $marble = Stone::query()->whereHas('type', fn ($type) => $type->where('slug', 'marble'))->firstOrFail();
    $onyx = Stone::query()->whereHas('type', fn ($type) => $type->where('slug', 'onyx'))->firstOrFail();
    $this->client->savedStones()->attach([$marble->id => ['created_at' => now()], $onyx->id => ['created_at' => now()]]);

    $someoneElse = User::factory()->create();
    $unsaved = Stone::query()->whereHas('type', fn ($type) => $type->where('slug', 'marble'))->whereKeyNot($marble->id)->firstOrFail();
    $someoneElse->savedStones()->attach([$unsaved->id => ['created_at' => now()]]);

    expect(inquireStones($this, ['stone_tab' => 'saved']))->toEqualCanonicalizing([$marble->slug, $onyx->slug])
        ->and(inquireStones($this, ['stone_tab' => 'saved', 'stone_type' => 'marble']))->toBe([$marble->slug]);
});

it('combines the stone type with Best Seller', function (): void {
    $marble = Stone::query()->whereHas('type', fn ($type) => $type->where('slug', 'marble'))->firstOrFail();
    makeInquiry($this->client, ['stones' => [$marble->slug]], ['status' => InquiryStatus::Closed]);

    expect(inquireStones($this, ['stone_tab' => 'bestSellers', 'stone_type' => 'marble']))->toContain($marble->slug)
        ->and(inquireStones($this, ['stone_tab' => 'bestSellers', 'stone_type' => 'onyx']))->toBe([]);
});

it('searches by name, stone type and origin, and takes the search as plain text', function (): void {
    $stone = Stone::query()->orderBy('id')->firstOrFail();
    $stone->update(['name' => 'Zebra 100% Stripe', 'origin' => 'Atlantis']);

    expect(inquireStones($this, ['stone_search' => 'zebra']))->toBe([$stone->slug])
        ->and(inquireStones($this, ['stone_search' => 'ATLANTIS']))->toBe([$stone->slug])
        ->and(inquireStones($this, ['stone_search' => '100%']))->toBe([$stone->slug])
        ->and(inquireStones($this, ['stone_search' => '1%0']))->toBe([])
        ->and(inquireStones($this, ['stone_search' => 'marble', 'stone_type' => 'marble']))->not->toBeEmpty();
});

it('treats an unknown tab as All, and keeps the filters for the page to read', function (): void {
    $this->actingAs($this->client)
        ->get(route('quote.inquire', ['stone_tab' => 'nonsense', 'stone_type' => 'marble', 'stone_search' => ' calacatta ']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('stoneFilters.tab', 'all')
            ->where('stoneFilters.type', 'marble')
            ->where('stoneFilters.search', 'calacatta'));
});

it('sends the stones of a restored draft only when asked, and only those still on offer', function (): void {
    [$kept, $inactive] = Stone::query()->with('type')->orderBy('id')->take(2)->get()->all();
    $inactive->update(['is_active' => false]);

    $this->actingAs($this->client)
        ->get(route('quote.inquire'))
        ->assertInertia(fn (Assert $page) => $page->missing('pickedStones'));

    $this->actingAs($this->client)
        ->get(route('quote.inquire', ['picked' => [$kept->slug, $inactive->slug, 'no-such-stone']]))
        ->assertInertia(fn (Assert $page) => $page->reloadOnly('pickedStones', fn (Assert $reload) => $reload
            ->has('pickedStones', 1)
            ->where('pickedStones.0.slug', $kept->slug)
            ->where('pickedStones.0.type.slug', $kept->type->slug)));
});
