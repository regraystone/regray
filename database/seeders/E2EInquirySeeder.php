<?php

namespace Database\Seeders;

use App\Data\Measurement;
use App\Enums\Role;
use App\Enums\SizeUnit;
use App\Enums\ThicknessUnit;
use App\Models\Service;
use App\Models\Size;
use App\Models\Stone;
use App\Models\Thickness;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * Test-only data for the Playwright suite in tests/e2e/inquiry. Runs after
 * DatabaseSeeder and only adds rows; it changes nothing the other seeders made.
 *
 * Two clients with no inquiries of their own, so a spec can count what it
 * sends; and an unverified client for the verification guard.
 *
 * The two catalogue stones the specs pick (CATALOG.stones in
 * tests/e2e/support/inquiry-flow.ts) get fixed slabs (size and thickness
 * combinations), so a spec can name what each row lists whatever StoneSeeder
 * generates for them; and every service the specs walk through has to offer
 * at least one of the two.
 *
 * Writes what the specs need to storage/app/e2e/inquiry-fixtures.json.
 */
class E2EInquirySeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('The E2E seeder only runs with APP_ENV=local or testing.');
        }

        // The helpers' default, 160 x 320 cm at 20 mm, is a slab of both.
        $this->fixStoneVariants('cosmic-black', [
            [['3.15', '1.98', 'm'], [[20, 'mm'], [0.75, 'in']]],
            [[160, 320, 'cm'], [[20, 'mm'], [0.75, 'in']]],
            [[60, 120, 'cm'], [[20, 'mm']]],
        ]);
        $this->fixStoneVariants('arabescato-white', [
            [['3.07', '2.01', 'm'], [[18, 'mm'], [20, 'mm']]],
            [[160, 320, 'cm'], [[18, 'mm'], [20, 'mm']]],
            [[120, 240, 'cm'], [[18, 'mm']]],
        ]);
        $this->assertCatalogStonesOffered(['countertop-backsplash', 'center-tables', 'side-tables', 'dining-tables', 'pedestal', 'other-customized-furniture']);

        $clientA = $this->user('e2e.inquiry.a@example.com', 'Inquiry Client A');
        $clientB = $this->user('e2e.inquiry.b@example.com', 'Inquiry Client B');
        $unverified = $this->user('e2e.inquiry.unverified@example.com', 'Unverified Client', verified: false);

        $path = storage_path('app/e2e/inquiry-fixtures.json');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode([
            'users' => [
                'clientA' => $clientA->email,
                'clientB' => $clientB->email,
                'unverified' => $unverified->email,
                'marketing' => 'marketing@example.com',
                'sales' => 'sales@example.com',
            ],
            'clientAId' => $clientA->id,
            'clientBId' => $clientB->id,
            'disk' => config('inquiry.disk'),
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }

    /**
     * The stone comes in exactly these slabs: each size in the thicknesses
     * listed with it, in this order.
     *
     * @param  list<array{0: array{0: int|float|string, 1: int|float|string, 2: string}, 1: list<array{0: int|float|string, 1: string}>}>  $slabs
     */
    private function fixStoneVariants(string $slug, array $slabs): void
    {
        $stone = Stone::query()->where('slug', $slug)->firstOrFail();
        $stone->variants()->delete();
        $stone->forceFill(['size_note' => null])->save();
        $order = 0;

        foreach ($slabs as [$size, $thicknesses]) {
            $sizeUnit = SizeUnit::from($size[2]);
            $sizeRow = Size::query()->firstOrCreate(
                ['width' => Measurement::number($size[0]), 'length' => Measurement::number($size[1]), 'unit' => $sizeUnit],
                ['sort_order' => $sizeUnit->position()],
            );

            foreach ($thicknesses as $thickness) {
                $unit = ThicknessUnit::from($thickness[1]);

                $stone->variants()->create([
                    'size_id' => $sizeRow->id,
                    'thickness_id' => Thickness::query()->firstOrCreate(
                        ['value' => Measurement::number($thickness[0]), 'unit' => $unit],
                        ['sort_order' => $unit->position()],
                    )->id,
                    'sort_order' => $order++,
                ]);
            }
        }
    }

    /**
     * Every one of these services offers Cosmic Black or Arabescato White, so a
     * spec walking through it has a stone to pick.
     *
     * @param  list<string>  $services
     */
    private function assertCatalogStonesOffered(array $services): void
    {
        foreach ($services as $slug) {
            $service = Service::query()->where('slug', $slug)->firstOrFail();

            if (! Stone::query()->offeredFor($service)->whereIn('slug', ['cosmic-black', 'arabescato-white'])->exists()) {
                throw new \RuntimeException("{$slug} offers neither catalogue stone the E2E specs pick.");
            }
        }
    }

    private function user(string $email, string $name, bool $verified = true): User
    {
        $factory = User::factory()->role(Role::Client);

        if (! $verified) {
            $factory = $factory->unverified();
        }

        return $factory->create(['email' => $email, 'name' => $name]);
    }
}
