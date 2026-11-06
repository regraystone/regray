<?php

use App\Actions\Inquiry\CreateInquiryAction;
use App\Enums\Role;
use App\Models\EdgeProfile;
use App\Models\Inquiry;
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
        ->post(route('quote.inquire.store'), ['projects' => [inquiryProjectInput()]])
        ->assertRedirect(route('quote.inquire'))
        ->assertSessionHas(SessionKey::FLASH_DATA.'.inquiry.status', 'error');
});

it('sends the Inquire page its options', function (): void {
    $this->actingAs(inquiryClient())
        ->get(route('quote.inquire'))
        ->assertInertia(fn ($page) => $page
            ->component('inquire')
            ->where('services', [
                ['id' => 'full-package', 'name' => 'Stone supply, fabrication and installation', 'needsFurniture' => false],
                ['id' => 'fabrication-supply', 'name' => 'Stone supply and fabrication', 'needsFurniture' => false],
                ['id' => 'installation-supply', 'name' => 'Stone supply and installation', 'needsFurniture' => false],
                ['id' => 'supply-only', 'name' => 'Stone supply only', 'needsFurniture' => false],
                ['id' => 'furniture-only', 'name' => 'Customized furniture', 'needsFurniture' => true],
                ['id' => 'full-kitchen-fit-out', 'name' => 'Full kitchen fit-out', 'needsFurniture' => false],
                ['id' => 'cabinetry-countertop-installation', 'name' => 'Cabinetry with stone countertop and installation', 'needsFurniture' => false],
                ['id' => 'cabinetry-countertop-supply', 'name' => 'Cabinetry with stone countertop', 'needsFurniture' => false],
            ])
            ->where('furnitureTypes.1', ['id' => 'tables', 'name' => 'Tables'])
            ->has('furnitureTypes', 8)
            ->has('stones.0', fn ($stone) => $stone->hasAll(['slug', 'name', 'thumbnail', 'bestSeller', 'brand', 'category', 'description', 'specifications']))
            ->has('edgeProfiles', 8)
            ->has('finishes.0', fn ($finish) => $finish->hasAll(['id', 'name', 'isAdvisory']))
            ->where('locations', config('inquiry.locations')));
});

it('leaves an inactive edge profile off the Inquire page', function (): void {
    EdgeProfile::query()->where('name', 'Half Bevel')->update(['is_active' => false]);

    $this->actingAs(inquiryClient())
        ->get(route('quote.inquire'))
        ->assertInertia(fn ($page) => $page->has('edgeProfiles', 7));
});
