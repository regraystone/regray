<?php

namespace App\Services;

use App\Data\PhAddress;
use App\Models\Ph\PhCity;
use App\Repositories\Contracts\PhAddressRepositoryInterface;

/**
 * Turns the codes an address picker sent into an address, or says what is
 * wrong with them, field by field, in the words the picker shows.
 *
 * Every level has to be a PSGC place, and each the parent of the next: the
 * city in the region (and in the province, when it has one), the barangay in
 * the city, the postal code one of the city's. A city in no province (NCR,
 * Isabela City, Cotabato City) takes no province. The names come from the
 * PSGC rows, never from the request. The pin is optional, and only ever
 * inside the Philippines.
 *
 * resources/js/lib/ph-address.ts checks the same rules on the page.
 *
 * @phpstan-type AddressInput array{regionCode?: mixed, provinceCode?: mixed, cityCode?: mixed, barangayCode?: mixed, postalCode?: mixed, lat?: mixed, lng?: mixed}
 */
class PhAddressService
{
    /** The fields of an address as the picker sends them. */
    public const FIELDS = ['regionCode', 'provinceCode', 'cityCode', 'barangayCode', 'postalCode', 'lat', 'lng'];

    /** The Philippines' extent, for a pin: latitude, then longitude. */
    public const BOUNDS = ['lat' => [4.2, 21.3], 'lng' => [116.0, 127.0]];

    public function __construct(
        public readonly PhAddressRepositoryInterface $places,
    ) {}

    /**
     * The address the input names, or what is wrong with it.
     *
     * @param  AddressInput  $input
     * @return array{address: PhAddress|null, errors: array<string, string>}
     */
    public function check(array $input): array
    {
        $errors = [];
        $text = fn (string $key): ?string => isset($input[$key]) && is_scalar($input[$key]) && trim((string) $input[$key]) !== ''
            ? trim((string) $input[$key])
            : null;

        $regionCode = $text('regionCode');
        $provinceCode = $text('provinceCode');
        $cityCode = $text('cityCode');
        $barangayCode = $text('barangayCode');
        $postalCode = $text('postalCode');

        $region = $regionCode === null ? null : $this->places->region($regionCode);
        $province = $provinceCode === null ? null : $this->places->province($provinceCode);
        $city = $cityCode === null ? null : $this->places->city($cityCode);
        $barangay = $barangayCode === null ? null : $this->places->barangay($barangayCode);

        if ($region === null) {
            $errors['regionCode'] = $regionCode === null ? 'Choose the region.' : 'Choose the region from the list.';
        }

        if ($provinceCode !== null && $province === null) {
            $errors['provinceCode'] = 'Choose the province from the list.';
        } elseif ($province !== null && $region !== null && $province->region_code !== $region->code) {
            $errors['provinceCode'] = 'Choose a province in this region.';
        }

        if ($city === null) {
            $errors['cityCode'] = $cityCode === null ? 'Choose the city or municipality.' : 'Choose the city or municipality from the list.';
        } elseif ($region !== null && $city->region_code !== $region->code) {
            $errors['cityCode'] = 'Choose a city or municipality in this region.';
        } elseif (! isset($errors['provinceCode'])) {
            $this->checkProvince($city, $provinceCode, $errors);
        }

        if ($barangay === null) {
            $errors['barangayCode'] = $barangayCode === null ? 'Choose the barangay.' : 'Choose the barangay from the list.';
        } elseif ($city !== null && $barangay->city_code !== $city->code) {
            $errors['barangayCode'] = 'Choose a barangay in this city or municipality.';
        }

        if ($city !== null && ! isset($errors['cityCode'])) {
            $codes = $this->places->postalCodesFor($city->code);

            if ($codes !== [] && $postalCode === null) {
                $errors['postalCode'] = 'Choose the postal code.';
            } elseif ($postalCode !== null && ! in_array($postalCode, $codes, true)) {
                $errors['postalCode'] = 'Choose a postal code of this city or municipality.';
            }
        }

        [$lat, $lng] = $this->pin($input, $errors);

        if ($errors !== [] || $region === null || $city === null || $barangay === null) {
            return ['address' => null, 'errors' => $errors];
        }

        return ['address' => new PhAddress(
            $region->code,
            $region->name,
            $city->province?->code,
            $city->province?->name,
            $city->code,
            $city->name,
            $barangay->code,
            $barangay->name,
            $postalCode,
            $lat,
            $lng,
        ), 'errors' => []];
    }

    /**
     * A city in a province takes that province; one in none takes none.
     *
     * @param  array<string, string>  $errors
     */
    private function checkProvince(PhCity $city, ?string $provinceCode, array &$errors): void
    {
        if ($city->province_code === null && $provinceCode !== null) {
            $errors['provinceCode'] = 'This city or municipality is in no province. Leave the province empty.';
        } elseif ($city->province_code !== null && $provinceCode === null) {
            $errors['provinceCode'] = 'Choose the province.';
        } elseif ($city->province_code !== null && $city->province_code !== $provinceCode) {
            $errors['cityCode'] = 'Choose a city or municipality in this province.';
        }
    }

    /**
     * The pin's latitude and longitude, both or neither, inside the country.
     *
     * @param  AddressInput  $input
     * @param  array<string, string>  $errors
     * @return array{0: string|null, 1: string|null}
     */
    private function pin(array $input, array &$errors): array
    {
        $lat = $input['lat'] ?? null;
        $lng = $input['lng'] ?? null;
        $blank = fn (mixed $value): bool => $value === null || $value === '';

        if ($blank($lat) && $blank($lng)) {
            return [null, null];
        }

        if (! is_numeric($lat) || ! is_numeric($lng)
            || (float) $lat < self::BOUNDS['lat'][0] || (float) $lat > self::BOUNDS['lat'][1]
            || (float) $lng < self::BOUNDS['lng'][0] || (float) $lng > self::BOUNDS['lng'][1]) {
            $errors['lat'] = 'Place the pin in the Philippines.';

            return [null, null];
        }

        return [number_format((float) $lat, 6, '.', ''), number_format((float) $lng, 6, '.', '')];
    }
}
