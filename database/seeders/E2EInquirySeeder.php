<?php

namespace Database\Seeders;

use App\Enums\Role;
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
 * Writes what the specs need to storage/app/e2e/inquiry-fixtures.json.
 */
class E2EInquirySeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('The E2E seeder only runs with APP_ENV=local or testing.');
        }

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

    private function user(string $email, string $name, bool $verified = true): User
    {
        $factory = User::factory()->role(Role::Client);

        if (! $verified) {
            $factory = $factory->unverified();
        }

        return $factory->create(['email' => $email, 'name' => $name]);
    }
}
