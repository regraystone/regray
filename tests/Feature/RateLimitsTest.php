<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;

/*
 * The limits on the app's own writes and heavy reads, and on Fortify's guest
 * forms: per user a minute (per IP for a guest), with the numbers in
 * config/rate-limits.php. The Message page's are in MessageRateLimitsTest.
 *
 * The cache is the array store, which starts empty with every test, so no
 * count carries over from one test to the next.
 */

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

it('starts from the agreed limits', function (): void {
    expect(config('rate-limits.per_minute'))->toMatchArray([
        'inquiry-submit' => 5,
        'inquiry-edit' => 10,
        'booking' => 10,
        'saved-stone' => 60,
        'group-create' => 10,
        'heavy-reads' => 120,
        'register' => 5,
        'password-reset' => 5,
    ]);
});

it('puts each write and heavy read behind its limiter', function (string $route, string $limiter): void {
    expect(Route::getRoutes()->getByName($route)?->gatherMiddleware())->toContain("throttle:{$limiter}");
})->with([
    ['quote.inquire.store', 'inquiry-submit'],
    ['inquiry.inquire.store', 'inquiry-submit'],
    ['quote.update', 'inquiry-edit'],
    ['quote.cancel', 'inquiry-edit'],
    ['schedule.book.store', 'booking'],
    ['saved.store', 'saved-stone'],
    ['saved.destroy', 'saved-stone'],
    ['message.groups.store', 'group-create'],
    ['home.index', 'heavy-reads'],
    ['saved.index', 'heavy-reads'],
    ['register.store', 'register'],
    ['password.email', 'password-reset'],
    ['password.update', 'password-reset'],
]);

/*
 * Every request is sent empty or for a reference that does not exist: the
 * limiter counts it before validation or the lookup refuses it, which is what
 * a stuck retry loop looks like. The two highest limits are lowered here so
 * the test does not make 121 page loads; that also proves they are read from
 * config.
 */
it('answers 429 past the limit, says when to retry, and lets the user back in a minute later', function (string $limiter, ?Role $role, string $method, string $route, array $parameters, ?int $lowered = null): void {
    if ($lowered !== null) {
        config(["rate-limits.per_minute.{$limiter}" => $lowered]);
    }

    $limit = config("rate-limits.per_minute.{$limiter}");
    $user = $role === null ? null : User::factory()->role($role)->create();
    $send = fn (): TestResponse => ($user === null ? $this : $this->actingAs($user))->json($method, route($route, $parameters));

    foreach (range(1, $limit) as $attempt) {
        expect($send()->status())->not->toBe(429);
    }

    $send()->assertTooManyRequests()->assertHeader('Retry-After');

    $this->travel(61)->seconds();

    expect($send()->status())->not->toBe(429);
})->with([
    'sending an inquiry from Quote' => ['inquiry-submit', Role::Client, 'POST', 'quote.inquire.store', []],
    'sending an inquiry from Inquiry' => ['inquiry-submit', Role::Marketing, 'POST', 'inquiry.inquire.store', []],
    'editing an inquiry' => ['inquiry-edit', Role::Client, 'PATCH', 'quote.update', ['reference' => 'INQ-0000']],
    'cancelling an inquiry' => ['inquiry-edit', Role::Client, 'PATCH', 'quote.cancel', ['reference' => 'INQ-0000']],
    'booking an appointment' => ['booking', Role::Client, 'POST', 'schedule.book.store', []],
    'saving a stone' => ['saved-stone', Role::Client, 'PUT', 'saved.store', ['stone' => 'no-such-stone'], 3],
    'unsaving a stone' => ['saved-stone', Role::Client, 'DELETE', 'saved.destroy', ['stone' => 'no-such-stone'], 3],
    'creating a group' => ['group-create', Role::Marketing, 'POST', 'message.groups.store', []],
    'browsing Home' => ['heavy-reads', Role::Client, 'GET', 'home.index', [], 3],
    'browsing Saved' => ['heavy-reads', Role::Client, 'GET', 'saved.index', [], 3],
    'registering, as a guest' => ['register', null, 'POST', 'register.store', []],
    'asking for a reset link, as a guest' => ['password-reset', null, 'POST', 'password.email', []],
]);

it('counts each user on their own', function (): void {
    $first = inquiryClient();
    $second = inquiryClient();

    foreach (range(1, 10) as $attempt) {
        $this->actingAs($first)->postJson(route('schedule.book.store'));
    }

    $this->actingAs($first)->postJson(route('schedule.book.store'))->assertTooManyRequests();
    $this->actingAs($second)->postJson(route('schedule.book.store'))->assertUnprocessable();
});

it('counts both inquiry doors against one budget', function (): void {
    config(['rate-limits.per_minute.inquiry-submit' => 2]);
    $admin = User::factory()->role(Role::Admin)->create();

    $this->actingAs($admin)->postJson(route('quote.inquire.store'));
    $this->actingAs($admin)->postJson(route('inquiry.inquire.store'));

    $this->actingAs($admin)->postJson(route('quote.inquire.store'))->assertTooManyRequests();
});
