<?php

use App\Models\Service;
use Database\Seeders\ServiceSeeder;

/** The migration that has the delivery line say how long a delivery takes. */
function deliveryNoteMigration(): object
{
    return require database_path('migrations/2026_11_27_000004_delivery_note_says_how_long.php');
}

it('says how long a furniture delivery takes, drops the fee and date lines, and keeps an admin\'s own', function (): void {
    $this->seed(ServiceSeeder::class);
    $fee = 'Delivery within Metro Manila is charged at a standard flat rate. For addresses outside Metro Manila, the fee depends on the location and is included in your quotation.';
    $schedule = 'Ready-made pieces are delivered within 5 to 7 days. Custom designs take longer; we will confirm the timeline in your quotation.';
    // As a database in use has them, one rewritten on the service form.
    Service::query()->whereIn('slug', ['slab-supply-only', 'center-tables', 'dining-tables'])->update(['delivery_note' => $fee, 'schedule_note' => $schedule]);
    Service::query()->where('slug', 'side-tables')->update(['delivery_note' => 'Free delivery in Makati.', 'schedule_note' => 'Ready in a week.']);

    deliveryNoteMigration()->up();

    $notes = fn (string $slug): array => Service::query()->where('slug', $slug)->sole()->only(['delivery_note', 'schedule_note']);

    expect($notes('dining-tables'))->toBe(['delivery_note' => 'Ready-made pieces are delivered within 5 to 7 days. Custom designs take longer; we will confirm the timeline in your quotation.', 'schedule_note' => null])
        ->and($notes('center-tables')['delivery_note'])->toContain('5 to 7 days')
        ->and($notes('slab-supply-only'))->toBe(['delivery_note' => null, 'schedule_note' => null])
        ->and($notes('side-tables'))->toBe(['delivery_note' => 'Free delivery in Makati.', 'schedule_note' => 'Ready in a week.']);
});
