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
            ->has('serviceTypes', 2)
            ->where('serviceTypes.0.id', 'natural-stone-applications')
            ->where('serviceTypes.0.name', 'Natural Stone & Applications')
            ->where('serviceTypes.0.services', fn ($services) => collect($services)->pluck('id')->all() === ['slab-supply-only', 'countertop', 'wall-cladding', 'flooring', 'staircase', 'other-applications'])
            ->where('serviceTypes.1.id', 'custom-stone-furniture')
            ->where('serviceTypes.1.services', fn ($services) => collect($services)->pluck('id')->all() === ['tables', 'seating', 'storage', 'decorative-furniture'])
            ->where('serviceTypes.0.services.0', [
                'id' => 'slab-supply-only',
                'name' => 'Slab Supply Only',
                'description' => 'Premium natural stone slabs supplied for your project.',
                'features' => ['Marble, quartzite, onyx, granite and travertine', 'Sourcing based on your project requirements', 'Nationwide supply'],
            ])
            ->missing('services')
            ->missing('furnitureTypes')
            ->has('stones.data.0', fn ($stone) => $stone->hasAll(['slug', 'name', 'thumbnail', 'brand', 'type']))
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
