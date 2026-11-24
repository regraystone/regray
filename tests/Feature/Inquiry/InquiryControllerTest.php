<?php

use App\Actions\Inquiry\CreateInquiryAction;
use App\Enums\Role;
use App\Models\EdgeProfile;
use App\Models\Finish;
use App\Models\Inquiry;
use App\Models\Service;
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
            ->where('serviceTypes.1.services', fn ($services) => collect($services)->pluck('id')->all() === ['center-tables', 'side-tables', 'dining-tables', 'pedestal', 'other-customized-furniture'])
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
                        'slug' => 'quantity', 'label' => 'How many pieces?', 'scope' => 'stone', 'answerType' => 'number', 'required' => true, 'allowsCustom' => true,
                        'options' => [],
                        'suggestions' => [],
                    ],
                    [
                        // The stone row lists its own stone's slabs: the question has none.
                        'slug' => 'slab_size', 'label' => 'Slab size', 'scope' => 'stone', 'answerType' => 'text', 'required' => true, 'allowsCustom' => true,
                        'options' => [],
                        'suggestions' => [],
                    ],
                    [
                        'slug' => 'thickness', 'label' => 'Thickness', 'scope' => 'stone', 'answerType' => 'text', 'required' => true, 'allowsCustom' => true,
                        'options' => [],
                        'suggestions' => [],
                    ],
                    [
                        // Slabs supplied alone may come uncut.
                        'slug' => 'edge_profile', 'label' => 'Edge profile', 'scope' => 'stone', 'answerType' => 'choice', 'required' => false, 'allowsCustom' => false,
                        'options' => EdgeProfile::query()->where('is_active', true)->orderBy('sort_order')->get()
                            ->map(fn (EdgeProfile $edge): array => ['value' => $edge->name, 'label' => $edge->name, 'isAdvisory' => $edge->is_advisory])->all(),
                        'suggestions' => [],
                    ],
                    [
                        'slug' => 'finish', 'label' => 'Finish', 'scope' => 'stone', 'answerType' => 'choice', 'required' => true, 'allowsCustom' => false,
                        'options' => Finish::query()->where('is_active', true)->orderBy('sort_order')->get()
                            ->map(fn (Finish $finish): array => ['value' => $finish->name, 'label' => $finish->name, 'isAdvisory' => $finish->is_advisory])->all(),
                        'suggestions' => [],
                    ],
                ],
            ])
            ->where('serviceTypes.1.services.2.fields', fn ($fields) => collect($fields)->pluck('slug')->all() === ['area_sqm', 'seaters', 'edge_profile', 'finish', 'table_base']
                && collect($fields)->firstWhere('slug', 'table_base')['options'] === [
                    ['value' => 'wood', 'label' => 'Wood', 'linksToStone' => false],
                    ['value' => 'steel', 'label' => 'Steel', 'linksToStone' => false],
                    ['value' => 'same-as-stone', 'label' => 'Same as stone', 'linksToStone' => true],
                ])
            ->where('serviceTypes.0.services.2.fields', [])
            ->where('serviceTypes.0.services.2.requiresStones', false)
            ->missing('services')
            ->missing('furnitureTypes')
            ->has('stones.data.0', fn ($stone) => $stone->hasAll(['slug', 'name', 'thumbnail', 'brand', 'type', 'variants', 'sizes', 'thicknesses', 'sizeNote']))
            ->has('edgeProfiles', 8)
            ->has('finishes.0', fn ($finish) => $finish->hasAll(['id', 'name', 'isAdvisory']))
            ->missing('locations'));
});

it('leaves an inactive edge profile off the Inquire page', function (): void {
    EdgeProfile::query()->where('name', 'Half Bevel')->update(['is_active' => false]);

    $this->actingAs(inquiryClient())
        ->get(route('quote.inquire'))
        ->assertInertia(fn ($page) => $page->has('edgeProfiles', 7));
});
