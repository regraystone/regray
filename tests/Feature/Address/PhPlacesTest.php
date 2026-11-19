<?php

use App\Enums\Role;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    seedInquiryCatalog();
    seedTestPlaces();
    $this->user = inquiryClient();
});

it('is for signed-in users only', function (): void {
    $this->getJson(route('ph.regions'))->assertUnauthorized();
});

it('lists the regions, found by a name written either way', function (): void {
    $this->actingAs($this->user)->getJson(route('ph.regions'))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('nextPage', null);

    $this->actingAs($this->user)->getJson(route('ph.regions', ['q' => 'national capital']))
        ->assertOk()
        ->assertJsonPath('data.0.code', '130000000')
        ->assertJsonPath('data.0.name', 'National Capital Region')
        ->assertJsonCount(1, 'data');
});

it('lists the provinces of a region', function (): void {
    $this->actingAs($this->user)->getJson(route('ph.provinces', ['region' => '040000000']))
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Rizal');

    $this->actingAs($this->user)->getJson(route('ph.provinces', ['region' => '130000000']))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('lists the cities of a region with no provinces, or of a province', function (): void {
    $this->actingAs($this->user)->getJson(route('ph.cities', ['region' => '130000000', 'q' => 'Makati City']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.code', '137602000');

    $this->actingAs($this->user)->getJson(route('ph.cities', ['province' => '045800000']))
        ->assertOk()
        ->assertJsonPath('data.0.name', 'City of Antipolo');
});

it('lists the barangays and the postal codes of a city', function (): void {
    $this->actingAs($this->user)->getJson(route('ph.barangays', ['city' => '137602000']))
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Bel-Air');

    $this->actingAs($this->user)->getJson(route('ph.postal-codes', ['city' => '137602000']))
        ->assertOk()
        ->assertJsonPath('data', [['code' => '1209', 'name' => '1209']]);
});

it('asks for the level above first', function (string $route): void {
    $this->actingAs($this->user)->getJson(route($route))->assertUnprocessable();
})->with(['ph.provinces', 'ph.cities', 'ph.barangays', 'ph.postal-codes']);

it('refuses a code that is not a PSGC code', function (): void {
    $this->actingAs($this->user)->getJson(route('ph.barangays', ['city' => 'makati']))
        ->assertUnprocessable();
});

it('is open to staff, who enter the address on an inquiry', function (): void {
    $this->actingAs(inquiryClient(Role::Sales))->getJson(route('ph.regions'))->assertOk();
});

describe('reading a point on the map', function (): void {
    // What Mapbox's reverse geocoding gives, lowest name first: it never
    // names the PSGC region, and its "region" is the city in Metro Manila.
    it('finds the city first, and takes its region from the city', function (): void {
        $this->actingAs($this->user)->getJson(route('ph.resolve', [
            'names' => ['Avenida de Macati', 'Bel-Air', 'Makati', 'Makati'],
            'postalCode' => '1224',
        ]))
            ->assertOk()
            ->assertExactJson([
                'region' => ['code' => '130000000', 'name' => 'National Capital Region'],
                'province' => null,
                'noProvince' => true,
                'city' => ['code' => '137602000', 'name' => 'City of Makati'],
                'barangay' => ['code' => '137602002', 'name' => 'Bel-Air'],
                // The city's only listed code, not the street's.
                'postalCode' => '1209',
                'needsPostalCode' => true,
                'missed' => [],
            ]);
    });

    it('takes the province from the city, and names the barangay it could not match', function (): void {
        $this->actingAs($this->user)->getJson(route('ph.resolve', [
            'names' => ['L. Jaena Street', 'San Jose', 'Antipolo', 'Rizal'],
            'postalCode' => '1870',
        ]))
            ->assertOk()
            ->assertJsonPath('region.name', 'CALABARZON')
            ->assertJsonPath('province.name', 'Rizal')
            ->assertJsonPath('noProvince', false)
            ->assertJsonPath('city.name', 'City of Antipolo')
            ->assertJsonPath('barangay', null)
            ->assertJsonPath('postalCode', '1870')
            ->assertJsonPath('missed', ['barangay']);
    });

    it('matches a barangay written with its Pob.', function (): void {
        $this->actingAs($this->user)->getJson(route('ph.resolve', ['names' => ['San Isidro', 'Antipolo', 'Rizal']]))
            ->assertOk()
            ->assertJsonPath('barangay.name', 'San Isidro (Pob.)');
    });

    it('tells apart cities of one name by the postal code, then by the province', function (): void {
        DB::table('ph_cities')->insert([
            'code' => '045899000', 'name' => 'Makati', 'search_name' => 'makati',
            'region_code' => '040000000', 'province_code' => '045800000', 'is_city' => false,
        ]);
        DB::table('ph_postal_codes')->insert(['postal_code' => '1999', 'city_code' => '045899000']);

        $resolve = fn (array $query) => $this->actingAs($this->user)->getJson(route('ph.resolve', $query))->assertOk()->json('city.code');

        expect($resolve(['names' => ['Makati'], 'postalCode' => '1999']))->toBe('045899000')
            ->and($resolve(['names' => ['Makati', 'Rizal']]))->toBe('045899000')
            // Nothing else to go by: Metro Manila's, which Mapbox names twice.
            ->and($resolve(['names' => ['Makati', 'Makati']]))->toBe('137602000');
    });

    it('stops at the province, or the region, when no city matches', function (): void {
        $this->actingAs($this->user)->getJson(route('ph.resolve', ['names' => ['Somewhere', 'Rizal']]))
            ->assertOk()
            ->assertJsonPath('province.name', 'Rizal')
            ->assertJsonPath('region.name', 'CALABARZON')
            ->assertJsonPath('city', null)
            ->assertJsonPath('missed', ['city']);

        $this->actingAs($this->user)->getJson(route('ph.resolve', ['names' => ['Somewhere', 'Metro Manila']]))
            ->assertOk()
            ->assertJsonPath('region.name', 'National Capital Region')
            ->assertJsonPath('noProvince', true)
            ->assertJsonPath('missed', ['city']);
    });

    it('says when nothing matches', function (): void {
        $this->actingAs($this->user)->getJson(route('ph.resolve', ['names' => ['Atlantis']]))
            ->assertOk()
            ->assertJsonPath('region', null)
            ->assertJsonPath('missed', ['region']);
    });

    it('refuses a request with no names, or a postal code that is not one', function (array $query): void {
        $this->actingAs($this->user)->getJson(route('ph.resolve', $query))->assertUnprocessable();
    })->with([
        'no names' => [[]],
        'a bad postal code' => [['names' => ['Makati'], 'postalCode' => '12a4']],
        'too many names' => [['names' => array_fill(0, 9, 'Makati')]],
    ]);

    it('is for signed-in users only', function (): void {
        $this->getJson(route('ph.resolve', ['names' => ['Makati']]))->assertUnauthorized();
    });
});
