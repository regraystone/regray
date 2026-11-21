<?php

namespace Database\Seeders;

use App\Data\PhAddress;
use App\Services\PhAddressService;
use RuntimeException;

/**
 * An address from data/places.php by its short name, checked as the app
 * checks one and with the names the `ph_*` tables hold, for the demo, E2E and
 * load seeders. PhAddressSeeder must have run.
 */
final class DemoPlaces
{
    /** @var array<string, PhAddress> */
    private static array $resolved = [];

    public static function address(string $place): PhAddress
    {
        if (isset(self::$resolved[$place])) {
            return self::$resolved[$place];
        }

        /** @var array<string, array<string, mixed>> $places */
        $places = require database_path('seeders/data/places.php');
        $input = $places[$place] ?? throw new RuntimeException("There is no demo place called {$place}.");
        $result = app(PhAddressService::class)->check($input);

        if ($result['address'] === null) {
            throw new RuntimeException("The demo place {$place} is not a valid address: ".implode(' ', $result['errors']).' Has PhAddressSeeder run?');
        }

        return self::$resolved[$place] = $result['address'];
    }

    /** Forget what was read, for a seeder run after a fresh database in the same process (tests). */
    public static function flush(): void
    {
        self::$resolved = [];
    }
}
