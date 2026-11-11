<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Route;

/*
 * Every limiter is defined by one loop in AppServiceProvider::configureRateLimits(),
 * per user a minute (per IP for a guest), with the numbers in
 * config/rate-limits.php. So the wiring is checked route by route, and the
 * behaviour once, on one limiter. Fortify defines `login` itself.
 *
 * The cache is the array store, which starts empty with every test.
 */

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

it('starts from the agreed limits', function (): void {
    expect(config('rate-limits.per_minute'))->toBe([
        'inquiry-submit' => 5,
        'inquiry-edit' => 10,
        'booking' => 10,
        'saved-stone' => 60,
        'group-create' => 10,
        'heavy-reads' => 120,
        'register' => 5,
        'password-reset' => 5,
        'messages' => 60,
        'message-receipts' => 120,
        'message-assignment' => 10,
        'message-people' => 60,
        'message-attachments' => 300,
        'notifications' => 120,
    ]);
});

it('puts each write and heavy read behind its limiter', function (string $route, string $limiter): void {
    expect(Route::getRoutes()->getByName($route)?->gatherMiddleware())->toContain("throttle:{$limiter}");
})->with([
    ['quote.inquire.store', 'inquiry-submit'],
    ['leads.inquiries.store', 'inquiry-submit'],
    ['quote.update', 'inquiry-edit'],
    ['quote.cancel', 'inquiry-edit'],
    ['leads.inquiries.status', 'inquiry-edit'],
    ['schedule.book.store', 'booking'],
    ['saved.store', 'saved-stone'],
    ['saved.destroy', 'saved-stone'],
    ['inbox.groups.store', 'group-create'],
    ['inbox.members.store', 'group-create'],
    ['home.index', 'heavy-reads'],
    ['saved.index', 'heavy-reads'],
    ['inbox.send', 'messages'],
    ['inbox.read', 'message-receipts'],
    ['inbox.delivered', 'message-receipts'],
    ['inbox.claim', 'message-assignment'],
    ['notifications.index', 'notifications'],
    ['notifications.open', 'notifications'],
    ['notifications.read-all', 'notifications'],
    ['inbox.people', 'message-people'],
    ['inbox.attachments.show', 'message-attachments'],
    ['register.store', 'register'],
    ['password.email', 'password-reset'],
    ['password.update', 'password-reset'],
    ['login.store', 'login'],
]);

it('answers 429 past the configured limit, says when to retry, counts each user on their own, and lets the user back in a minute later', function (): void {
    // Lowered, which also shows the number is read from config. Each request is
    // refused by validation, which is what a stuck retry loop looks like.
    config(['rate-limits.per_minute.booking' => 2]);
    $first = inquiryClient();
    $second = inquiryClient();

    $this->actingAs($first)->postJson(route('schedule.book.store'))->assertUnprocessable();
    $this->actingAs($first)->postJson(route('schedule.book.store'))->assertUnprocessable();
    $this->actingAs($first)->postJson(route('schedule.book.store'))->assertTooManyRequests()->assertHeader('Retry-After');
    $this->actingAs($second)->postJson(route('schedule.book.store'))->assertUnprocessable();

    $this->travel(61)->seconds();

    $this->actingAs($first)->postJson(route('schedule.book.store'))->assertUnprocessable();
});

it('counts both inquiry doors against one budget', function (): void {
    config(['rate-limits.per_minute.inquiry-submit' => 2]);
    $admin = User::factory()->role(Role::Admin)->create();

    $this->actingAs($admin)->postJson(route('quote.inquire.store'));
    $this->actingAs($admin)->postJson(route('leads.inquiries.store', 'LEAD-0000'));

    $this->actingAs($admin)->postJson(route('quote.inquire.store'))->assertTooManyRequests();
});
