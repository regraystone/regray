<?php

use App\Enums\Role;
use App\Models\ClientAddress;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    // What is tested is the page's props, not its asset tags.
    $this->withoutVite();
    seedInquiryCatalog();
    $this->client = inquiryClient();
});

/** @param  array<string, mixed>  $overrides */
function addressSave(string $label = 'Home', string $place = 'Bel-Air', array $overrides = []): array
{
    return ['label' => $label, 'address' => testAddressInput($place), ...$overrides];
}

it('shows the client their addresses, the default first', function (): void {
    saveTestAddress($this->client, 'Site', 'San Isidro', default: false);
    saveTestAddress($this->client, 'Home', 'Bel-Air', default: true);

    $this->actingAs($this->client)->get(route('profile.addresses.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('profile/addresses')
            ->where('limit', 5)
            ->where('quickLabels', ['Home', 'Office', 'Site'])
            ->where('returnTo', null)
            ->has('addresses', 2)
            ->where('addresses.0.label', 'Home')
            ->where('addresses.0.isDefault', true)
            ->where('addresses.0.address.cityName', 'City of Makati'));
});

it('offers the way back to the request when opened from it', function (): void {
    $this->actingAs($this->client)->get(route('profile.addresses.index', ['from' => 'inquire']))
        ->assertInertia(fn (Assert $page) => $page->where('returnTo', route('quote.inquire', absolute: false)));
});

it('is not a staff page', function (): void {
    $this->actingAs(inquiryClient(Role::Sales))->get(route('profile.addresses.index'))->assertForbidden();
});

it('adds the first address as the default, with the PSGC names, never the ones sent', function (): void {
    $this->actingAs($this->client)
        ->post(route('profile.addresses.store'), addressSave(' Home ', overrides: ['address' => testAddressInput('Bel-Air', ['cityName' => 'Fake'])]))
        ->assertSessionHasErrors('address');

    $this->actingAs($this->client)
        ->post(route('profile.addresses.store'), addressSave(' Home '))
        ->assertRedirect(route('profile.addresses.index'))
        ->assertSessionHasNoErrors();

    $saved = ClientAddress::query()->sole();

    expect($saved->label)->toBe('Home')
        ->and($saved->is_default)->toBeTrue()
        ->and($saved->city_name)->toBe('City of Makati')
        ->and($saved->barangay_name)->toBe('Bel-Air')
        ->and($saved->postal_code)->toBe('1209')
        ->and($saved->province_code)->toBeNull();
});

it('adds a later address as not the default', function (): void {
    saveTestAddress($this->client);

    $this->actingAs($this->client)->post(route('profile.addresses.store'), addressSave('Site', 'San Isidro'))->assertSessionHasNoErrors();

    expect(ClientAddress::query()->where('is_default', true)->pluck('label')->all())->toBe(['Home'])
        ->and(ClientAddress::query()->where('label', 'Site')->value('province_name'))->toBe('Rizal');
});

it('keeps the client on the way back to their request', function (): void {
    $this->actingAs($this->client)
        ->post(route('profile.addresses.store', ['from' => 'inquire']), addressSave())
        ->assertRedirect(route('profile.addresses.index', ['from' => 'inquire']));
});

it('refuses an address whose levels do not belong together', function (array $address, string $field): void {
    $this->actingAs($this->client)
        ->post(route('profile.addresses.store'), addressSave(overrides: ['address' => testAddressInput('Bel-Air', $address)]))
        ->assertSessionHasErrors($field);

    expect(ClientAddress::query()->exists())->toBeFalse();
})->with([
    'a barangay of another city' => [['barangayCode' => '137403009'], 'address.barangayCode'],
    'a province where the city has none' => [['provinceCode' => '045800000'], 'address.provinceCode'],
    'a postal code of another city' => [['postalCode' => '1603'], 'address.postalCode'],
    'no postal code where the city has some' => [['postalCode' => null], 'address.postalCode'],
    'a pin outside the Philippines' => [['lat' => '35.680000', 'lng' => '139.690000'], 'address.lat'],
    'no barangay' => [['barangayCode' => null], 'address.barangayCode'],
]);

it('needs a label', function (): void {
    $this->actingAs($this->client)->post(route('profile.addresses.store'), addressSave('  '))
        ->assertSessionHasErrors(['label' => 'Give this address a label, like Home or Office.']);
});

it('keeps at most five addresses', function (): void {
    foreach (range(1, 5) as $n) {
        saveTestAddress($this->client, "Place {$n}");
    }

    $this->actingAs($this->client)->post(route('profile.addresses.store'), addressSave('Sixth'))
        ->assertSessionHasErrors(['address' => 'You can keep up to 5 addresses. Delete one to add another.']);

    expect(ClientAddress::query()->count())->toBe(5);
});

it('changes an address in place', function (): void {
    $saved = saveTestAddress($this->client);

    $this->actingAs($this->client)->put(route('profile.addresses.update', $saved->id), addressSave('Office', 'Kapitolyo'))
        ->assertSessionHasNoErrors();

    expect($saved->refresh()->label)->toBe('Office')
        ->and($saved->city_name)->toBe('City of Pasig')
        ->and($saved->is_default)->toBeTrue();
});

it('keeps exactly one default', function (): void {
    $home = saveTestAddress($this->client);
    $site = saveTestAddress($this->client, 'Site', 'San Isidro');

    $this->actingAs($this->client)->post(route('profile.addresses.default', $site->id))->assertSessionHasNoErrors();

    expect($site->refresh()->is_default)->toBeTrue()
        ->and($home->refresh()->is_default)->toBeFalse();
});

it('makes another address the default when the default is deleted', function (): void {
    $home = saveTestAddress($this->client);
    $site = saveTestAddress($this->client, 'Site', 'San Isidro');

    $this->actingAs($this->client)->delete(route('profile.addresses.destroy', $home->id))->assertSessionHasNoErrors();

    expect(ClientAddress::query()->pluck('id')->all())->toBe([$site->id])
        ->and($site->refresh()->is_default)->toBeTrue();
});

it('lets no one reach another client\'s address', function (string $method, string $route): void {
    $theirs = saveTestAddress(inquiryClient());

    $this->actingAs($this->client)
        ->{$method}(route($route, $theirs->id), $method === 'put' ? addressSave('Mine') : [])
        ->assertNotFound();

    expect($theirs->refresh()->label)->toBe('Home')
        ->and($theirs->is_default)->toBeTrue();
})->with([
    'change' => ['put', 'profile.addresses.update'],
    'make default' => ['post', 'profile.addresses.default'],
    'delete' => ['delete', 'profile.addresses.destroy'],
]);
