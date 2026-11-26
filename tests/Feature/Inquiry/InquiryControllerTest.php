<?php

use App\Actions\Inquiry\CreateInquiryAction;
use App\Enums\Role;
use App\Models\Brand;
use App\Models\DetailFieldOption;
use App\Models\EdgeProfile;
use App\Models\Finish;
use App\Models\Furniture;
use App\Models\FurnitureType;
use App\Models\Inquiry;
use App\Models\Service;
use App\Models\Stone;
use Illuminate\Support\Facades\Storage;
use Inertia\Support\SessionKey;

beforeEach(function (): void {
    Storage::fake(config('inquiry.disk'));
    seedInquiryCatalog();
});

/*
 * The POST routes carry the same guards as their GET twins plus
 * `inquiries.create`. A client holds `quotations.view` and so reaches the quote
 * URL; the inquiry URL is the staff list's, so it takes `inquiries.view` and
 * the client is turned away from it exactly as they are from its GET.
 */
it('lets marketing send an inquiry for its own lead\'s client, never for itself', function (): void {
    $marketing = inquiryClient(Role::Marketing);
    $client = inquiryClient();
    $lead = makeLead($client, marketing: $marketing);

    $this->actingAs($marketing)
        ->post(route('leads.inquiries.store', $lead->reference), ['projects' => [inquiryProjectInput()]])
        ->assertRedirect()
        ->assertSessionHas(SessionKey::FLASH_DATA.'.inquiry.status', 'success');

    $inquiry = Inquiry::query()->sole();

    expect($inquiry->client_id)->toBe($client->id)
        ->and($inquiry->lead_id)->toBe($lead->id)
        ->and($inquiry->schedule()->value('booked_by'))->toBe($marketing->id);
});

it('flashes an error and keeps the page when the write fails', function (): void {
    $this->mock(CreateInquiryAction::class)
        ->shouldReceive('execute')
        ->andThrow(new RuntimeException('database down'));

    $this->actingAs(inquiryClient())
        ->from(route('quote.inquire'))
        ->post(route('quote.inquire.store'), clientInquiryInput([inquiryProjectInput()]))
        ->assertRedirect(route('quote.inquire'))
        ->assertSessionHas(SessionKey::FLASH_DATA.'.inquiry.status', 'error');
});

it('sends the Inquire page its options', function (): void {
    $slabSupplyTypes = Service::query()->where('slug', 'slab-supply-only')->firstOrFail()
        ->stoneTypes()->orderBy('sort_order')->orderBy('stone_types.id')->pluck('slug')->all();

    // The stones are the chosen service's, so one is named to have a card to check.
    $this->actingAs(inquiryClient())
        ->get(route('quote.inquire', ['stone_service' => 'slab-supply-only']))
        ->assertInertia(fn ($page) => $page
            ->component('inquire')
            ->has('serviceTypes', 2)
            ->where('serviceTypes.0.id', 'natural-stone-applications')
            ->where('serviceTypes.0.name', 'Natural Stone & Applications')
            ->where('serviceTypes.0.services', fn ($services) => collect($services)->pluck('id')->all() === ['slab-supply-only', 'slab-supply-and-installation', 'installation-only', 'countertop-backsplash', 'wall-cladding', 'flooring', 'staircase', 'kitchen-islands', 'lavatories'])
            ->where('serviceTypes.1.id', 'custom-stone-furniture')
            ->where('serviceTypes.1.services', fn ($services) => collect($services)->pluck('id')->all() === ['center-tables', 'side-tables', 'dining-tables', 'other-customized-furniture'])
            ->where('serviceTypes.0.services.0', [
                'id' => 'slab-supply-only',
                'name' => 'Slab Supply Only',
                'description' => 'Premium natural stone slabs supplied for your project.',
                'features' => ['Every stone type Regray carries', 'Sourcing based on your project requirements', 'Nationwide supply'],
                'stoneTypes' => $slabSupplyTypes,
                'requiresStones' => true,
                'fulfillment' => 'delivery_or_pickup',
                'fields' => [
                    [
                        'slug' => 'quantity', 'label' => 'How many pieces?', 'scope' => 'stone', 'answerType' => 'number', 'required' => true, 'allowsCustom' => true, 'pictures' => false,
                        'options' => [],
                        'suggestions' => [],
                    ],
                    [
                        // The stone row lists its own stone's slabs: the question has none.
                        'slug' => 'slab_size', 'label' => 'Slab size', 'scope' => 'stone', 'answerType' => 'text', 'required' => true, 'allowsCustom' => true, 'pictures' => false,
                        'options' => [],
                        'suggestions' => [],
                    ],
                    [
                        'slug' => 'thickness', 'label' => 'Thickness', 'scope' => 'stone', 'answerType' => 'text', 'required' => true, 'allowsCustom' => true, 'pictures' => false,
                        'options' => [],
                        'suggestions' => [],
                    ],
                    [
                        // Slabs supplied alone may come uncut.
                        'slug' => 'edge_profile', 'label' => 'Edge profile', 'scope' => 'stone', 'answerType' => 'choice', 'required' => false, 'allowsCustom' => false, 'pictures' => false,
                        'options' => EdgeProfile::query()->where('is_active', true)->orderBy('sort_order')->get()
                            ->map(fn (EdgeProfile $edge): array => ['value' => $edge->name, 'label' => $edge->name, 'isAdvisory' => $edge->is_advisory, 'description' => $edge->description])->all(),
                        'suggestions' => [],
                    ],
                    [
                        'slug' => 'finish', 'label' => 'Finish', 'scope' => 'stone', 'answerType' => 'choice', 'required' => true, 'allowsCustom' => false, 'pictures' => false,
                        'options' => Finish::query()->where('is_active', true)->orderBy('sort_order')->get()
                            ->map(fn (Finish $finish): array => ['value' => $finish->name, 'label' => $finish->name, 'isAdvisory' => $finish->is_advisory, 'description' => $finish->description])->all(),
                        'suggestions' => [],
                    ],
                ],
            ])
            ->where('serviceTypes.1.services.2.fields', fn ($fields) => collect($fields)->pluck('slug')->all() === ['table_shape', 'table_base_design_dining', 'table_base', 'area_sqm', 'seaters', 'edge_profile', 'finish']
                && collect($fields)->firstWhere('slug', 'table_base')['options'] === [
                    ['value' => 'wood', 'label' => 'Wood', 'linksToStone' => false, 'isCustom' => false, 'isAdvisory' => false, 'description' => null, 'image' => null, 'sizeKind' => null],
                    ['value' => 'steel', 'label' => 'Steel', 'linksToStone' => false, 'isCustom' => false, 'isAdvisory' => false, 'description' => null, 'image' => null, 'sizeKind' => null],
                    ['value' => 'same-as-stone', 'label' => 'Same as stone', 'linksToStone' => true, 'isCustom' => false, 'isAdvisory' => false, 'description' => null, 'image' => null, 'sizeKind' => null],
                ]
                && collect($fields)->firstWhere('slug', 'table_base')['pictures'] === false
                // Asked with pictures, a dining table its own base design question, listing only dining bases.
                && collect($fields)->firstWhere('slug', 'table_shape')['pictures'] === true
                && collect($fields)->firstWhere('slug', 'table_base_design_dining')['pictures'] === true
                && collect(collect($fields)->firstWhere('slug', 'table_base_design_dining')['options'])->pluck('value')->all() === ['diamond', 'close-cage', 'tubular-v1', 'tubular-v2', 'x-base-v1', 'x-base-v2', 'clover-v1', 'clover-v2', 'y-base', 'hourglass-v1', 'custom-design', 'not-sure'])
            // A center and a side table each ask their own.
            ->where('serviceTypes.1.services.0.fields', fn ($fields) => collect(collect($fields)->firstWhere('slug', 'table_base_design_center')['options'])->pluck('value')->all() === ['sphere-accent-pedestal', 'u-base', 'half-moon', 'custom-design', 'not-sure'])
            ->where('serviceTypes.1.services.1.fields', fn ($fields) => collect(collect($fields)->firstWhere('slug', 'table_base_design_side')['options'])->pluck('value')->all() === ['c-legs-v1', 'c-legs-v2', 'cylindrical-pedestal', 'hourglass-v2', 'custom-design', 'not-sure'])
            ->where('serviceTypes.0.services.2.fields', [])
            ->where('serviceTypes.0.services.2.requiresStones', false)
            ->missing('services')
            ->missing('furnitureTypes')
            ->has('stones.data.0', fn ($stone) => $stone->hasAll(['slug', 'name', 'thumbnail', 'brand', 'type', 'variants', 'sizes', 'thicknesses', 'sizeNote']))
            ->has('edgeProfiles', 8)
            ->has('finishes.0', fn ($finish) => $finish->hasAll(['id', 'name', 'isAdvisory']))
            ->missing('locations'));
});

it('offers on the Brand question only a brand with an active stone or piece of furniture', function (): void {
    $empty = Brand::factory()->create(['slug' => 'empty-brand', 'sort_order' => 90]);
    Stone::factory()->for($empty)->create(['is_active' => false]);
    $furnitureOnly = Brand::factory()->create(['slug' => 'furniture-only', 'sort_order' => 91]);
    Furniture::factory()->for($furnitureOnly)->create();

    $available = fn (): array => collect(
        $this->actingAs(inquiryClient())->get(route('quote.inquire'))->viewData('page')['props']['brands'],
    )->pluck('available', 'id')->all();

    expect($available())->toMatchArray(['regray-stone' => true, 'empty-brand' => false, 'furniture-only' => true]);

    // The day it has an active product, it is offered.
    Stone::factory()->for($empty)->create();

    expect($available()['empty-brand'])->toBeTrue();
});

it('leaves an inactive edge profile off the Inquire page', function (): void {
    EdgeProfile::query()->where('name', 'Half Bevel')->update(['is_active' => false]);

    $this->actingAs(inquiryClient())
        ->get(route('quote.inquire'))
        ->assertInertia(fn ($page) => $page->has('edgeProfiles', 7));
});

describe('opened from a furniture page', function (): void {
    /** A table of this type, with its own shape and base design (a choice of its table's question) by slug. */
    function tablePiece(string $type, ?string $shape, ?string $base, bool $active = true): Furniture
    {
        $choice = fn (string $field, ?string $slug): ?int => $slug === null ? null : DetailFieldOption::query()
            ->whereHas('detailField', fn ($query) => $field === 'table_base_design' ? $query->where('slug', 'like', 'table\_base\_design\_%') : $query->where('slug', $field))->where('slug', $slug)->value('id');

        return Furniture::factory()->for(Brand::query()->where('slug', 'atelier')->first() ?? Brand::factory()->create(['slug' => 'atelier']))->create([
            'furniture_type_id' => (FurnitureType::query()->where('slug', $type)->first() ?? FurnitureType::factory()->create(['slug' => $type]))->id,
            'table_shape_option_id' => $choice('table_shape', $shape),
            'table_base_design_option_id' => $choice('table_base_design', $base),
            'is_active' => $active,
        ]);
    }

    it('picks the piece\'s brand, its service and its own shape and base design', function (): void {
        $piece = tablePiece('dining-tables', 'oval', 'clover-v1');

        $this->actingAs(inquiryClient())
            ->get(route('quote.inquire', ['furniture' => $piece->slug]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('furniture', [
                'brand' => 'atelier',
                'serviceType' => 'custom-stone-furniture',
                'service' => 'dining-tables',
                'answers' => ['table_shape' => 'oval', 'table_base_design_dining' => 'clover-v1'],
            ]));
    });

    it('orders a center or side table through the service whose base design question lists its base', function (?string $base, string $service, array $answers): void {
        $piece = tablePiece('center-side-tables', 'round', $base);

        $this->actingAs(inquiryClient())
            ->get(route('quote.inquire', ['furniture' => $piece->slug]))
            ->assertInertia(fn ($page) => $page
                ->where('furniture.service', $service)
                ->where('furniture.answers', ['table_shape' => 'round', ...$answers]));
    })->with([
        'C-legs, a side table' => ['c-legs-v1', 'side-tables', ['table_base_design_side' => 'c-legs-v1']],
        'a half moon, a center table' => ['half-moon', 'center-tables', ['table_base_design_center' => 'half-moon']],
        'no base: the first' => [null, 'center-tables', []],
    ]);

    it('leaves out a choice no longer offered, and picks nothing for a piece not shown or unknown', function (): void {
        $piece = tablePiece('dining-tables', 'square', 'y-base');
        DetailFieldOption::query()->where('slug', 'y-base')->update(['archived_at' => now(), 'is_active' => false]);
        $hidden = tablePiece('dining-tables', 'round', null, active: false);
        $client = inquiryClient();

        $this->actingAs($client)
            ->get(route('quote.inquire', ['furniture' => $piece->slug]))
            ->assertInertia(fn ($page) => $page->where('furniture.answers', ['table_shape' => 'square']));
        $this->get(route('quote.inquire', ['furniture' => $hidden->slug]))
            ->assertInertia(fn ($page) => $page->where('furniture', null));
        $this->get(route('quote.inquire', ['furniture' => 'no-such-piece']))
            ->assertInertia(fn ($page) => $page->where('furniture', null));
        $this->get(route('quote.inquire'))
            ->assertInertia(fn ($page) => $page->where('furniture', null));
    });
});
