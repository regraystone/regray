<?php

/*
 * The places the demo, E2E and load seeders give an address: the demo
 * clients' address books and the address each demo inquiry is delivered to or
 * installed at. Keyed by a short name the other data files use (`place`).
 *
 * Each is PSGC codes (data/psgc) and a postal code of that city, with a map
 * pin near the barangay; DemoPlaces reads the names from the `ph_*` tables
 * and checks every level as the app does (PhAddressService).
 *
 * @var array<string, array{regionCode: string, provinceCode: string|null, cityCode: string, barangayCode: string, postalCode: string, lat: float, lng: float}>
 */
return [
    'Quezon City' => ['regionCode' => '130000000', 'provinceCode' => null, 'cityCode' => '137404000', 'barangayCode' => '137404022', 'postalCode' => '1121', 'lat' => 14.697400, 'lng' => 121.087900],
    'Loyola Heights' => ['regionCode' => '130000000', 'provinceCode' => null, 'cityCode' => '137404000', 'barangayCode' => '137404055', 'postalCode' => '1108', 'lat' => 14.639600, 'lng' => 121.077900],
    'Makati' => ['regionCode' => '130000000', 'provinceCode' => null, 'cityCode' => '137602000', 'barangayCode' => '137602025', 'postalCode' => '1223', 'lat' => 14.551300, 'lng' => 121.019800],
    'Bel-Air' => ['regionCode' => '130000000', 'provinceCode' => null, 'cityCode' => '137602000', 'barangayCode' => '137602002', 'postalCode' => '1209', 'lat' => 14.561800, 'lng' => 121.029600],
    'Muntinlupa' => ['regionCode' => '130000000', 'provinceCode' => null, 'cityCode' => '137603000', 'barangayCode' => '137603009', 'postalCode' => '1780', 'lat' => 14.414300, 'lng' => 121.033400],
    'San Juan' => ['regionCode' => '130000000', 'provinceCode' => null, 'cityCode' => '137405000', 'barangayCode' => '137405021', 'postalCode' => '1502', 'lat' => 14.602400, 'lng' => 121.048700],
    'Addition Hills' => ['regionCode' => '130000000', 'provinceCode' => null, 'cityCode' => '137405000', 'barangayCode' => '137405001', 'postalCode' => '1500', 'lat' => 14.592900, 'lng' => 121.038700],
    'Pasig' => ['regionCode' => '130000000', 'provinceCode' => null, 'cityCode' => '137403000', 'barangayCode' => '137403009', 'postalCode' => '1603', 'lat' => 14.568700, 'lng' => 121.062400],
    'Taguig' => ['regionCode' => '130000000', 'provinceCode' => null, 'cityCode' => '137607000', 'barangayCode' => '137607020', 'postalCode' => '1634', 'lat' => 14.548800, 'lng' => 121.049600],
    'Antipolo' => ['regionCode' => '040000000', 'provinceCode' => '045800000', 'cityCode' => '045802000', 'barangayCode' => '045802007', 'postalCode' => '1870', 'lat' => 14.586400, 'lng' => 121.176000],
    'Cebu' => ['regionCode' => '070000000', 'provinceCode' => '072200000', 'cityCode' => '072217000', 'barangayCode' => '072217041', 'postalCode' => '6000', 'lat' => 10.332600, 'lng' => 123.897700],
];
