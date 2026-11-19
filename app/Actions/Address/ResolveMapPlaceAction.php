<?php

namespace App\Actions\Address;

use App\Models\Ph\PhBarangay;
use App\Models\Ph\PhCity;
use App\Models\Ph\PhProvince;
use App\Models\Ph\PhRegion;
use App\Repositories\Contracts\PhAddressRepositoryInterface;
use App\Support\PhPlaceName;

/**
 * The PSGC address a point on the map stands for, from the names Mapbox gives
 * it (lowest first) and its postal code.
 *
 * Mapbox does not name the Philippines' regions: its "region" is the province,
 * or the city itself in Metro Manila and for a few others ("Makati", "Cebu
 * City"). So the city is found first, by name in any region, and the region
 * and the province are the city's own. Where a name is shared ("San Juan" is
 * seven places), the city the postal code belongs to wins, then one whose
 * province is among the names. Its barangay is then looked for among the
 * names. A level that matches nothing is named in `missed`, for the reader to
 * pick by hand.
 */
class ResolveMapPlaceAction
{
    /** Names Mapbox and people use for a region the PSGC names otherwise. */
    private const REGION_ALIASES = [
        'metro manila' => 'national capital region',
        'ncr' => 'national capital region',
        'cordillera' => 'car',
        'cordillera administrative region' => 'car',
        'bangsamoro' => 'barmm',
        'autonomous region in muslim mindanao' => 'barmm',
        'armm' => 'barmm',
    ];

    public function __construct(
        public readonly PhAddressRepositoryInterface $places,
    ) {}

    /**
     * @param  list<string>  $names
     * @return array{region: array{code: string, name: string}|null, province: array{code: string, name: string}|null, noProvince: bool, city: array{code: string, name: string}|null, barangay: array{code: string, name: string}|null, postalCode: string, needsPostalCode: bool, missed: list<string>}
     */
    public function execute(array $names, ?string $postalCode): array
    {
        $folded = array_values(array_unique(array_filter(array_map(PhPlaceName::fold(...), $names), fn (string $name): bool => $name !== '')));
        $city = $this->city($folded, $postalCode);

        if ($city instanceof PhCity) {
            return $this->fromCity($city, $folded, $postalCode);
        }

        $province = $this->places->provincesNamed($folded)[0] ?? null;

        if ($province instanceof PhProvince) {
            $region = $this->places->region($province->region_code);

            return $this->result(region: $region, province: $province, missed: ['city']);
        }

        $aliased = array_map(fn (string $name): string => self::REGION_ALIASES[$name] ?? $name, $folded);
        $region = $this->places->regionsNamed($aliased)[0] ?? null;

        if ($region instanceof PhRegion) {
            $hasProvinces = $this->places->regionShapes([$region->code])[$region->code]['hasProvinces'];

            return $this->result(region: $region, noProvince: ! $hasProvinces, missed: [$hasProvinces ? 'province' : 'city']);
        }

        return $this->result(missed: ['region']);
    }

    /** @param  list<string>  $folded */
    private function city(array $folded, ?string $postalCode): ?PhCity
    {
        $candidates = $this->places->citiesNamed($folded);

        if (count($candidates) <= 1) {
            return $candidates[0] ?? null;
        }

        $postalCity = $postalCode === null ? null : $this->places->cityCodeForPostalCode($postalCode);
        $score = fn (PhCity $city): int => ($city->code === $postalCity ? 4 : 0)
            + ($city->province !== null && in_array($city->province->search_name, $folded, true) ? 2 : 0)
            // Mapbox names a city with no province (Metro Manila's) twice, as its region too.
            + ($city->province_code === null ? 1 : 0);

        usort($candidates, fn (PhCity $a, PhCity $b): int => $score($b) <=> $score($a) ?: strcmp($a->code, $b->code));

        return $candidates[0];
    }

    /**
     * @param  list<string>  $folded
     * @return array{region: array{code: string, name: string}|null, province: array{code: string, name: string}|null, noProvince: bool, city: array{code: string, name: string}|null, barangay: array{code: string, name: string}|null, postalCode: string, needsPostalCode: bool, missed: list<string>}
     */
    private function fromCity(PhCity $city, array $folded, ?string $postalCode): array
    {
        // The lowest name first: Mapbox's neighbourhood before its locality.
        $barangays = $this->places->barangaysNamed($city->code, $folded);
        usort($barangays, fn (PhBarangay $a, PhBarangay $b): int => array_search($a->search_name, $folded, true) <=> array_search($b->search_name, $folded, true));
        $barangay = $barangays[0] ?? null;

        $codes = $this->places->postalCodesFor($city->code);

        return $this->result(
            region: $this->places->region($city->region_code),
            province: $city->province,
            noProvince: $city->province_code === null,
            city: $city,
            barangay: $barangay,
            postalCode: count($codes) === 1 ? $codes[0] : (in_array($postalCode, $codes, true) ? (string) $postalCode : ''),
            needsPostalCode: $codes !== [],
            missed: $barangay === null ? ['barangay'] : [],
        );
    }

    /**
     * @param  list<string>  $missed
     * @return array{region: array{code: string, name: string}|null, province: array{code: string, name: string}|null, noProvince: bool, city: array{code: string, name: string}|null, barangay: array{code: string, name: string}|null, postalCode: string, needsPostalCode: bool, missed: list<string>}
     */
    private function result(
        ?PhRegion $region = null,
        ?PhProvince $province = null,
        bool $noProvince = false,
        ?PhCity $city = null,
        ?PhBarangay $barangay = null,
        string $postalCode = '',
        bool $needsPostalCode = true,
        array $missed = [],
    ): array {
        $place = fn (PhRegion|PhProvince|PhCity|PhBarangay|null $place): ?array => $place === null ? null : ['code' => $place->code, 'name' => $place->name];

        return [
            'region' => $place($region),
            'province' => $place($province),
            'noProvince' => $noProvince,
            'city' => $place($city),
            'barangay' => $place($barangay),
            'postalCode' => $postalCode,
            'needsPostalCode' => $needsPostalCode,
            'missed' => $missed,
        ];
    }
}
