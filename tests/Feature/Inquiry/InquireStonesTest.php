<?php

use App\Enums\HomeGridPageSize;
use App\Enums\InquiryStatus;
use App\Models\Service;
use App\Models\Stone;
use App\Models\StoneType;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    seedInquiryCatalog();

    $this->client = inquiryClient();
});

/**
 * The slugs of one page of stones on the Inquire page, for Slab Supply Only
 * (which every stone type is offered for) unless `$query` names a service.
 *
 * @param  array<string, mixed>  $query
 * @return list<string>
 */
function inquireStones(mixed $test, array $query = []): array
{
    return collect($test->actingAs($test->client)->get(route('quote.inquire', ['stone_service' => 'slab-supply-only', ...$query]))
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
        ->get(route('quote.inquire', ['stone_service' => 'slab-supply-only']))
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
    Service::query()->where('slug', 'slab-supply-only')->firstOrFail()->stoneTypes()->attach($empty);

    $types = $this->actingAs($this->client)->get(route('quote.inquire', ['stone_service' => 'slab-supply-only']))->viewData('page')['props']['stoneTypes'];

    expect(collect($types)->pluck('value'))
        ->toContain(Stone::query()->active()->first()->type->slug)
        ->not->toContain($empty->slug);
});

it('sends no stones and no stone types until a service is chosen', function (): void {
    foreach ([[], ['stone_service' => 'no-such-service']] as $query) {
        $this->actingAs($this->client)
            ->get(route('quote.inquire', $query))
            ->assertInertia(fn (Assert $page) => $page
                ->has('stones.data', 0)
                ->where('stoneTypes', [])
                ->where('stoneFilters.service', null));
    }
});

it('sends only the stones whose primary type is linked to the service', function (): void {
    $countertop = Service::query()->where('slug', 'countertop-backsplash')->firstOrFail();
    $linked = $countertop->stoneTypes->modelKeys();
    $offered = Stone::query()->active()->whereIn('stone_type_id', $linked)->pluck('slug')->all();

    $slugs = inquireStones($this, ['stone_service' => 'countertop-backsplash']);

    expect($slugs)->not->toBeEmpty()
        ->each->toBeIn($offered)
        ->and($slugs)->not->toContain(unofferedStoneSlug('countertop-backsplash'));
});

it('lists only the stone types linked to the service that have an active stone for it', function (): void {
    $flooring = Service::query()->where('slug', 'flooring')->firstOrFail();
    $linked = $flooring->stoneTypes()->orderBy('sort_order')->get();
    [$emptied] = $linked->all();
    Stone::query()->where('stone_type_id', $emptied->id)->update(['is_active' => false]);

    $types = collect($this->actingAs($this->client)->get(route('quote.inquire', ['stone_service' => 'flooring']))->viewData('page')['props']['stoneTypes'])->pluck('value');

    expect($types->all())->toBe($linked->skip(1)->pluck('slug')->values()->all())
        ->and(StoneType::query()->whereNotIn('id', $linked->modelKeys())->pluck('slug')->intersect($types))->toBeEmpty();
});

it('sends the stone types again on a partial reload for another service', function (): void {
    $this->actingAs($this->client)
        ->get(route('quote.inquire', ['stone_service' => 'pedestal']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('stoneTypes', fn ($types) => collect($types)->pluck('value')->sort()->values()->all() === Service::query()->where('slug', 'pedestal')->firstOrFail()->stoneTypes->pluck('slug')->sort()->values()->all())
            ->reloadOnly(['stones', 'stoneFilters', 'stoneTypes'], fn (Assert $reload) => $reload->has('stoneTypes')));
});

it('keeps Saved and Best Seller within the service', function (): void {
    $unoffered = Stone::query()->where('slug', unofferedStoneSlug('countertop-backsplash'))->firstOrFail();
    $offered = Stone::query()->where('slug', offeredStoneSlug('countertop-backsplash'))->firstOrFail();
    $this->client->savedStones()->attach([$unoffered->id => ['created_at' => now()], $offered->id => ['created_at' => now()]]);

    expect(inquireStones($this, ['stone_service' => 'countertop-backsplash', 'stone_tab' => 'saved']))->toBe([$offered->slug]);
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
        ->get(route('quote.inquire', ['stone_service' => 'slab-supply-only', 'picked' => [$kept->slug, $inactive->slug, 'no-such-stone']]))
        ->assertInertia(fn (Assert $page) => $page->reloadOnly('pickedStones', fn (Assert $reload) => $reload
            ->has('pickedStones', 1)
            ->where('pickedStones.0.slug', $kept->slug)
            ->where('pickedStones.0.type.slug', $kept->type->slug)));
});

it('sends the stones of a restored draft only when they fit its service', function (): void {
    $fits = offeredStoneSlug('countertop-backsplash');
    $misfit = unofferedStoneSlug('countertop-backsplash');

    $this->actingAs($this->client)
        ->get(route('quote.inquire', ['stone_service' => 'countertop-backsplash', 'picked' => [$fits, $misfit]]))
        ->assertInertia(fn (Assert $page) => $page->reloadOnly('pickedStones', fn (Assert $reload) => $reload
            ->has('pickedStones', 1)
            ->where('pickedStones.0.slug', $fits)));

    $this->actingAs($this->client)
        ->get(route('quote.inquire', ['picked' => [$fits]]))
        ->assertInertia(fn (Assert $page) => $page->reloadOnly('pickedStones', fn (Assert $reload) => $reload->has('pickedStones', 0)));
});

it('does not offer a service made from no stone type, and tells the page which types each service takes', function (): void {
    Service::query()->where('slug', 'pedestal')->firstOrFail()->stoneTypes()->detach();

    $serviceTypes = collect($this->actingAs($this->client)->get(route('quote.inquire'))->viewData('page')['props']['serviceTypes']);
    $services = $serviceTypes->flatMap(fn (array $type) => $type['services'])->keyBy('id');

    expect($services->has('pedestal'))->toBeFalse()
        ->and($services->has('dining-tables'))->toBeTrue()
        ->and($services['dining-tables']['stoneTypes'])->toEqualCanonicalizing(Service::query()->where('slug', 'dining-tables')->firstOrFail()->stoneTypes->pluck('slug')->all())
        // It asks for no stones, so it is offered made from none.
        ->and($services['installation-only']['stoneTypes'])->toBe([])
        ->and($services['installation-only']['requiresStones'])->toBeFalse();
});

it('carries each stone\'s slabs on its card: every combination, its sizes and thicknesses once each, and its size note', function (): void {
    $stone = Stone::factory()->withVariants([
        [[160, 320, 'cm'], [30, 'mm']],
        [[160, 320, 'cm'], [20, 'mm']],
        [['3.15', '1.98', 'm'], [20, 'mm']],
        [[60, 120, 'cm'], [0.75, 'in']],
    ])->create([
        'name' => 'Slab Card Stone',
        'size_note' => null,
        'stone_type_id' => Stone::query()->offeredFor(inquiryService('slab-supply-only'))->value('stone_type_id'),
    ]);

    $card = collect($this->actingAs($this->client)->get(route('quote.inquire', ['stone_service' => 'slab-supply-only', 'stone_search' => 'Slab Card Stone']))
        ->viewData('page')['props']['stones']['data'])->firstWhere('slug', $stone->slug);

    expect($card['variants'])->toBe([
        ['size' => '160 x 320 cm', 'thickness' => '30 mm'],
        ['size' => '160 x 320 cm', 'thickness' => '20 mm'],
        ['size' => '3.15 x 1.98 m', 'thickness' => '20 mm'],
        ['size' => '60 x 120 cm', 'thickness' => '0.75 in'],
    ])
        ->and($card['sizes'])->toBe([
            ['value' => '60 x 120 cm', 'label' => '60 x 120 cm'],
            ['value' => '160 x 320 cm', 'label' => '160 x 320 cm'],
            ['value' => '3.15 x 1.98 m', 'label' => '3.15 x 1.98 m'],
        ])
        ->and($card['thicknesses'])->toBe([
            ['value' => '20 mm', 'label' => '20 mm'],
            ['value' => '30 mm', 'label' => '30 mm'],
            ['value' => '0.75 in', 'label' => '0.75 in'],
        ])
        ->and($card['sizeNote'])->toBeNull();
});
