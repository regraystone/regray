<?php

namespace App\Data;

/**
 * One address in the Philippines, as every place that holds one holds it: each
 * level by its PSGC code and its name, the postal code, and the map pin. There
 * is no street or house number.
 *
 * The client's address book (`client_addresses`) keeps these columns as they
 * are; an inquiry keeps a copy under `address_` (Inquiry::address()).
 * PhAddressRules builds one from codes it has checked, so a name is always the
 * PSGC's own, never one the browser sent.
 *
 * @phpstan-type PhAddressColumns array{region_code: string, region_name: string, province_code: string|null, province_name: string|null, city_code: string, city_name: string, barangay_code: string, barangay_name: string, postal_code: string|null, lat: string|null, lng: string|null}
 * @phpstan-type PhAddressPayload array{regionCode: string, regionName: string, provinceCode: string|null, provinceName: string|null, cityCode: string, cityName: string, barangayCode: string, barangayName: string, postalCode: string|null, lat: float|null, lng: float|null, line: string}
 */
final class PhAddress
{
    /** The columns, in the order they are stored. */
    public const COLUMNS = [
        'region_code', 'region_name', 'province_code', 'province_name', 'city_code', 'city_name',
        'barangay_code', 'barangay_name', 'postal_code', 'lat', 'lng',
    ];

    public function __construct(
        public readonly string $regionCode,
        public readonly string $regionName,
        public readonly ?string $provinceCode,
        public readonly ?string $provinceName,
        public readonly string $cityCode,
        public readonly string $cityName,
        public readonly string $barangayCode,
        public readonly string $barangayName,
        public readonly ?string $postalCode,
        public readonly ?string $lat,
        public readonly ?string $lng,
    ) {}

    /**
     * Read from a row's columns, under `$prefix` (`address_` on an inquiry).
     * Null when the row has no address.
     *
     * @param  array<string, mixed>  $row
     */
    public static function fromColumns(array $row, string $prefix = ''): ?self
    {
        $value = fn (string $column): ?string => isset($row[$prefix.$column]) ? (string) $row[$prefix.$column] : null;

        if ($value('city_code') === null) {
            return null;
        }

        return new self(
            (string) $value('region_code'),
            (string) $value('region_name'),
            $value('province_code'),
            $value('province_name'),
            (string) $value('city_code'),
            (string) $value('city_name'),
            (string) $value('barangay_code'),
            (string) $value('barangay_name'),
            $value('postal_code'),
            $value('lat'),
            $value('lng'),
        );
    }

    /**
     * The columns, under `$prefix`, ready to write.
     *
     * @return array<string, string|null>
     */
    public function toColumns(string $prefix = ''): array
    {
        $columns = [
            'region_code' => $this->regionCode,
            'region_name' => $this->regionName,
            'province_code' => $this->provinceCode,
            'province_name' => $this->provinceName,
            'city_code' => $this->cityCode,
            'city_name' => $this->cityName,
            'barangay_code' => $this->barangayCode,
            'barangay_name' => $this->barangayName,
            'postal_code' => $this->postalCode,
            'lat' => $this->lat,
            'lng' => $this->lng,
        ];

        return array_combine(array_map(fn (string $column): string => $prefix.$column, array_keys($columns)), $columns);
    }

    /**
     * Every column under `$prefix` set to null, for a row with no address.
     *
     * @return array<string, null>
     */
    public static function emptyColumns(string $prefix = ''): array
    {
        return array_fill_keys(array_map(fn (string $column): string => $prefix.$column, self::COLUMNS), null);
    }

    /**
     * "Barangay, City, Province, Region, Postal code", leaving out a level the
     * address has none of. The same as ph-address.ts formatAddressLine().
     */
    public function line(): string
    {
        return implode(', ', array_values(array_filter(
            [$this->barangayName, $this->cityName, $this->provinceName, $this->regionName, $this->postalCode],
            fn (?string $part): bool => $part !== null && $part !== '',
        )));
    }

    /**
     * The shape every page reads.
     *
     * @return PhAddressPayload
     */
    public function toPayload(): array
    {
        return [
            'regionCode' => $this->regionCode,
            'regionName' => $this->regionName,
            'provinceCode' => $this->provinceCode,
            'provinceName' => $this->provinceName,
            'cityCode' => $this->cityCode,
            'cityName' => $this->cityName,
            'barangayCode' => $this->barangayCode,
            'barangayName' => $this->barangayName,
            'postalCode' => $this->postalCode,
            'lat' => $this->lat === null ? null : (float) $this->lat,
            'lng' => $this->lng === null ? null : (float) $this->lng,
            'line' => $this->line(),
        ];
    }
}
