<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * Test-only data for the Playwright suite in tests/e2e/inquiry. Runs after
 * DatabaseSeeder and only adds rows; it changes nothing the other seeders made.
 *
 * Two clients with no inquiries of their own, so a spec can count what it
 * sends; an unverified client for the verification guard; and one retired
 * service, so the server can be asked about a slug the page never offers.
 *
 * Writes what the specs need to storage/app/e2e/inquiry-fixtures.json.
 */
class E2EInquirySeeder extends Seeder
{
    public const RETIRED_SERVICE = 'e2e-retired-service';

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('The E2E seeder only runs with APP_ENV=local or testing.');
        }

        $clientA = $this->user('e2e.inquiry.a@example.com', 'Inquiry Client A');
        $clientB = $this->user('e2e.inquiry.b@example.com', 'Inquiry Client B');
        $unverified = $this->user('e2e.inquiry.unverified@example.com', 'Unverified Client', verified: false);

        Service::query()->updateOrCreate(
            ['slug' => self::RETIRED_SERVICE],
            [
                'name' => 'Retired Service',
                'image_path' => (string) Service::query()->orderBy('sort_order')->value('image_path'),
                'is_standalone' => false,
                'is_active' => false,
                'sort_order' => 999,
            ],
        );

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
            'retiredService' => self::RETIRED_SERVICE,
            'disk' => config('inquiry.disk'),
        ], JSON_PRETTY_PRINT));
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
