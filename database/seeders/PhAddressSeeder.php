<?php

namespace Database\Seeders;

use App\Support\PhPlaceName;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The Philippines' places (PSGC) and their postal codes, from the CSV files of
 * database/seeders/data/psgc (see its README for where they come from and how
 * scripts/build-psgc-data.py makes them). Reference data, seeded everywhere.
 *
 * Run again, it updates the rows in place, parents before children, a chunk
 * at a time: there are some 42,000 barangays. `search_name` is each name
 * folded as a search folds what is typed (PhPlaceName::fold()).
 */
class PhAddressSeeder extends Seeder
{
    private const CHUNK = 1000;

    public function run(): void
    {
        $this->load('regions', 'ph_regions', ['code'], fn (array $row): array => [
            'code' => $row['code'],
            'name' => $row['name'],
            'designation' => $row['designation'],
            'search_name' => PhPlaceName::fold($row['name']),
        ]);

        $this->load('provinces', 'ph_provinces', ['code'], fn (array $row): array => [
            'code' => $row['code'],
            'name' => $row['name'],
            'search_name' => PhPlaceName::fold($row['name']),
            'region_code' => $row['region_code'],
        ]);

        $this->load('cities', 'ph_cities', ['code'], fn (array $row): array => [
            'code' => $row['code'],
            'name' => $row['name'],
            'search_name' => PhPlaceName::fold($row['name']),
            'region_code' => $row['region_code'],
            'province_code' => $row['province_code'] === '' ? null : $row['province_code'],
            'is_city' => $row['is_city'] === '1',
        ]);

        $this->load('barangays', 'ph_barangays', ['code'], fn (array $row): array => [
            'code' => $row['code'],
            'name' => $row['name'],
            'search_name' => PhPlaceName::fold($row['name']),
            'city_code' => $row['city_code'],
        ]);

        $this->load('postal_codes', 'ph_postal_codes', ['city_code', 'postal_code'], fn (array $row): array => [
            'postal_code' => $row['postal_code'],
            'city_code' => $row['city_code'],
        ]);
    }

    /**
     * Upsert one CSV file into its table, a chunk at a time.
     *
     * @param  non-empty-list<non-empty-string>  $key
     * @param  callable(array<string, string>): array<string, mixed>  $map
     */
    private function load(string $file, string $table, array $key, callable $map): void
    {
        $path = database_path("seeders/data/psgc/{$file}.csv");
        $handle = fopen($path, 'r') ?: throw new RuntimeException("Cannot read {$path}.");

        try {
            $header = fgetcsv($handle, escape: '') ?: throw new RuntimeException("{$path} is empty.");
            $chunk = [];

            while (($values = fgetcsv($handle, escape: '')) !== false) {
                if ($values === [null]) {
                    continue;
                }

                $row = $map(array_combine($header, $values));
                $chunk[] = $row;

                if (count($chunk) === self::CHUNK) {
                    $this->upsert($table, $chunk, $key);
                    $chunk = [];
                }
            }

            if ($chunk !== []) {
                $this->upsert($table, $chunk, $key);
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  non-empty-list<non-empty-string>  $key
     */
    private function upsert(string $table, array $rows, array $key): void
    {
        $update = array_values(array_diff(array_keys($rows[0]), $key));

        DB::table($table)->upsert($rows, $key, $update === [] ? $key : $update);
    }
}
