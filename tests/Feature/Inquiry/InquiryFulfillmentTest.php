<?php

use App\Enums\ClientKind;
use App\Enums\Role;
use App\Models\Brand;
use App\Models\Client;
use App\Models\ClientAddress;
use App\Models\Inquiry;
use App\Models\Stone;
use Database\Seeders\BrandSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake(config('inquiry.disk'));
    seedInquiryCatalog();
    $this->seed(BrandSeeder::class);
    $this->client = inquiryClient();
});

/** Send one project as the client, returning the response. */
function sendAsClient(mixed $test, array $project): mixed
{
    return $test->actingAs($test->client)->post(route('quote.inquire.store'), clientInquiryInput([inquiryProjectInput($project)]));
}

describe('brands', function (): void {
    it('keeps the brands the client would like', function (): void {
        // The stone sent is from one of them: the answer keeps the stones to its brands.
        $stoneBrand = Stone::query()->where('slug', inquiryStoneRow()['stoneId'])->sole()->brand->slug;
        $brands = Brand::query()->where('slug', '!=', $stoneBrand)->orderBy('sort_order')->limit(1)->pluck('slug')->push($stoneBrand)->all();

        sendAsClient($this, ['brands' => $brands, 'brandNoPreference' => null])->assertSessionHasNoErrors();

        $inquiry = Inquiry::query()->with('brands')->sole();

        expect($inquiry->brand_no_preference)->toBeFalse()
            ->and($inquiry->brands->pluck('slug')->sort()->values()->all())->toBe(collect($brands)->sort()->values()->all());
    });

    it('keeps No preference, with no brands', function (): void {
        sendAsClient($this, [])->assertSessionHasNoErrors();

        $inquiry = Inquiry::query()->with('brands')->sole();

        expect($inquiry->brand_no_preference)->toBeTrue()
            ->and($inquiry->brands)->toBeEmpty();
    });

    it('needs brands or No preference, and not both', function (array $answer, string $field): void {
        sendAsClient($this, ['brandNoPreference' => null, ...$answer])->assertSessionHasErrors($field);

        expect(Inquiry::query()->exists())->toBeFalse();
    })->with([
        'neither' => [[], 'projects.0.brands'],
        'both' => [['brands' => ['cucine'], 'brandNoPreference' => '1'], 'projects.0.brands'],
        'an unknown brand' => [['brands' => ['nobody']], 'projects.0.brands.0'],
    ]);

    it('keeps the stones to the brands chosen, and takes any with No preference', function (): void {
        $row = inquiryStoneRow();
        $brand = Stone::query()->where('slug', $row['stoneId'])->sole()->brand;
        $other = Brand::query()->whereKeyNot($brand->id)->where('is_active', true)->orderBy('sort_order')->value('slug');

        sendAsClient($this, ['brands' => [$other], 'brandNoPreference' => null, 'stones' => [$row]])
            ->assertSessionHasErrors(['projects.0.stones.0.stoneId' => "That stone isn't from the brands you chose."]);

        expect(Inquiry::query()->exists())->toBeFalse();

        sendAsClient($this, ['brands' => [$other, $brand->slug], 'brandNoPreference' => null, 'stones' => [$row]])->assertSessionHasNoErrors();
        sendAsClient($this, ['stones' => [$row]])->assertSessionHasNoErrors();

        expect(Inquiry::query()->count())->toBe(2);
    });

    it('refuses an inactive brand', function (): void {
        Brand::query()->where('slug', 'cucine')->update(['is_active' => false]);

        sendAsClient($this, ['brands' => ['cucine'], 'brandNoPreference' => null])->assertSessionHasErrors('projects.0.brands.0');
    });
});

describe('a service delivered or picked up', function (): void {
    it('copies the saved delivery address onto the inquiry', function (): void {
        $saved = saveTestAddress($this->client, 'Office', 'Kapitolyo');

        sendAsClient($this, ['addressId' => (string) $saved->id])->assertSessionHasNoErrors();

        $inquiry = Inquiry::query()->sole();

        expect($inquiry->delivery_method->value)->toBe('delivery')
            ->and($inquiry->address_city_name)->toBe('City of Pasig')
            ->and($inquiry->address_barangay_name)->toBe('Kapitolyo')
            ->and($inquiry->fulfillmentSummary())->toBe('Delivery to Kapitolyo, City of Pasig, National Capital Region, 1603');

        // A copy: the book can change, the inquiry keeps what it was sent with.
        $saved->delete();

        expect($inquiry->refresh()->address_city_name)->toBe('City of Pasig');
    });

    it('takes a pickup with no address', function (): void {
        sendAsClient($this, ['deliveryMethod' => 'pickup', 'address' => null])->assertSessionHasNoErrors();

        $inquiry = Inquiry::query()->sole();

        expect($inquiry->delivery_method->value)->toBe('pickup')
            ->and($inquiry->address_city_code)->toBeNull()
            ->and($inquiry->fulfillmentSummary())->toBe('Pickup');
    });

    it('needs the method, and a delivery its address', function (): void {
        sendAsClient($this, ['deliveryMethod' => null])->assertSessionHasErrors('projects.0.deliveryMethod');

        $this->actingAs($this->client)
            ->post(route('quote.inquire.store'), clientInquiryInput([inquiryProjectInput(['address' => null])]))
            ->assertSessionHasErrors(['projects.0.addressId' => 'Choose the delivery address.']);
    });
});

describe('an installed service', function (): void {
    it('refuses a delivery method, which it does not ask', function (): void {
        sendAsClient($this, ['serviceId' => 'installation-only', 'deliveryMethod' => 'pickup'])
            ->assertSessionHasErrors(['projects.0.deliveryMethod' => 'This service is installed on site, so there is no delivery method to choose.']);
    });

    it('asks no method, only the installation address', function (): void {
        sendAsClient($this, ['serviceId' => 'installation-only'])->assertSessionHasNoErrors();

        $inquiry = Inquiry::query()->sole();

        expect($inquiry->delivery_method)->toBeNull()
            ->and($inquiry->address_city_name)->toBe('City of Makati')
            ->and($inquiry->fulfillmentSummary())->toStartWith('Installation at Bel-Air');
    });

    it('needs the installation address', function (): void {
        $this->actingAs($this->client)
            ->post(route('quote.inquire.store'), clientInquiryInput([inquiryProjectInput(['serviceId' => 'installation-only', 'address' => null])]))
            ->assertSessionHasErrors(['projects.0.addressId' => 'Choose the installation address.']);

        expect(Inquiry::query()->exists())->toBeFalse();
    });
});

describe('the client\'s address book', function (): void {
    it('is the only place a client\'s address comes from', function (): void {
        $this->actingAs($this->client)
            ->post(route('quote.inquire.store'), ['projects' => [inquiryProjectInput()]])
            ->assertSessionHasErrors('projects.0.address');

        expect(Inquiry::query()->exists())->toBeFalse();
    });

    it('refuses another client\'s saved address', function (): void {
        $theirs = saveTestAddress(inquiryClient());

        sendAsClient($this, ['addressId' => (string) $theirs->id])->assertSessionHasErrors('projects.0.addressId');

        expect(Inquiry::query()->exists())->toBeFalse();
    });

    it('offers the client their own book on the Inquire page, and nothing of it to staff', function (): void {
        saveTestAddress($this->client);
        saveTestAddress(inquiryClient(), 'Not mine');

        $this->actingAs($this->client)->get(route('quote.inquire'))
            ->assertInertia(fn ($page) => $page->has('addresses', 1)->where('addresses.0.label', 'Home'));

        $this->actingAs(inquiryClient(Role::Sales))->get(route('quote.inquire'))
            ->assertInertia(fn ($page) => $page->missing('addresses'));
    });
});

describe('staff', function (): void {
    it('enter the address with the picker, and never a saved one', function (): void {
        $record = Client::query()->create(['kind' => ClientKind::Direct, 'full_name' => 'Walk-in']);
        $sales = inquiryClient(Role::Sales);

        $this->actingAs($sales)
            ->post(route('inquiry.direct-clients.inquiries.store', $record->id), ['projects' => [inquiryProjectInput(['address' => testAddressInput('San Isidro')])]])
            ->assertSessionHasNoErrors();

        expect(Inquiry::query()->sole()->address_province_name)->toBe('Rizal');

        $saved = saveTestAddress($this->client);

        $this->actingAs($sales)
            ->post(route('inquiry.direct-clients.inquiries.store', $record->id), ['projects' => [inquiryProjectInput(['addressId' => (string) $saved->id])]])
            ->assertSessionHasErrors('projects.0.addressId');

        expect(ClientAddress::query()->count())->toBe(1);
    });

    it('get each level of a bad picked address on its field', function (): void {
        $record = Client::query()->create(['kind' => ClientKind::Direct, 'full_name' => 'Walk-in']);

        $this->actingAs(inquiryClient(Role::Sales))
            ->post(route('inquiry.direct-clients.inquiries.store', $record->id), ['projects' => [inquiryProjectInput([
                'address' => testAddressInput('Bel-Air', ['barangayCode' => '137403009']),
            ])]])
            ->assertSessionHasErrors('projects.0.address.barangayCode');
    });
});
