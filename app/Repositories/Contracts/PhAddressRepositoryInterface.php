<?php

namespace App\Repositories\Contracts;

use App\Models\Ph\PhBarangay;
use App\Models\Ph\PhCity;
use App\Models\Ph\PhProvince;
use App\Models\Ph\PhRegion;
use Illuminate\Contracts\Pagination\Paginator;

/**
 * The PSGC places an address is picked from. Each search is one page, in name
 * order, of the places under a parent whose folded name contains the query
 * (`$pattern` is PhPlaceName::searchPattern()'s, or null for every one).
 */
interface PhAddressRepositoryInterface
{
    /** @return Paginator<int, PhRegion> */
    public function searchRegions(?string $pattern, int $perPage, int $page): Paginator;

    /** @return Paginator<int, PhProvince> */
    public function searchProvinces(string $regionCode, ?string $pattern, int $perPage, int $page): Paginator;

    /**
     * The cities of a province, or, with no province, the cities of the region
     * that are in none (NCR's, Isabela City, Cotabato City).
     *
     * @return Paginator<int, PhCity>
     */
    public function searchCities(?string $provinceCode, ?string $regionCode, ?string $pattern, int $perPage, int $page): Paginator;

    /** @return Paginator<int, PhBarangay> */
    public function searchBarangays(string $cityCode, ?string $pattern, int $perPage, int $page): Paginator;

    /** @return list<string> The city's ZIP codes, in order. */
    public function postalCodesFor(string $cityCode): array;

    /**
     * Whether each region has provinces, and cities in none, by code.
     *
     * @param  list<string>  $regionCodes
     * @return array<string, array{hasProvinces: bool, hasCitiesWithoutProvince: bool}>
     */
    public function regionShapes(array $regionCodes): array;

    public function region(string $code): ?PhRegion;

    public function province(string $code): ?PhProvince;

    public function city(string $code): ?PhCity;

    public function barangay(string $code): ?PhBarangay;

    /**
     * The cities and municipalities whose folded name is one of `$names`, in
     * any region, with their province.
     *
     * @param  list<string>  $names  folded by PhPlaceName::fold()
     * @return list<PhCity>
     */
    public function citiesNamed(array $names): array;

    /**
     * @param  list<string>  $names  folded
     * @return list<PhProvince>
     */
    public function provincesNamed(array $names): array;

    /**
     * @param  list<string>  $names  folded, matched against the name or the designation
     * @return list<PhRegion>
     */
    public function regionsNamed(array $names): array;

    /**
     * The city's barangays whose folded name is one of `$names`.
     *
     * @param  list<string>  $names  folded
     * @return list<PhBarangay>
     */
    public function barangaysNamed(string $cityCode, array $names): array;

    /** The city a postal code belongs to, when the code is listed. */
    public function cityCodeForPostalCode(string $postalCode): ?string;
}
