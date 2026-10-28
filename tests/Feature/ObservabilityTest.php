<?php

use App\Enums\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Sentry\Event;
use Sentry\SentrySdk;
use Sentry\State\Scope;

/*
 * Who may open the Pulse and Horizon dashboards, and what Sentry is told about
 * the signed-in user.
 */

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

/** Sentry's hub outlives the app between tests, so its scope is emptied after each. */
afterEach(function (): void {
    SentrySdk::getCurrentHub()->configureScope(fn (Scope $scope) => $scope->clear());
});

it('opens Pulse and Horizon to the owner and the admin, and to nobody else', function (string $role): void {
    $user = User::factory()->role($role)->create();
    $allowed = in_array($role, [Role::Owner->value, Role::Admin->value], true);

    expect(Gate::forUser($user)->allows('viewPulse'))->toBe($allowed)
        ->and(Gate::forUser($user)->allows('viewHorizon'))->toBe($allowed);

    $this->actingAs($user)->get('/pulse')->assertStatus($allowed ? 200 : 403);
    $this->actingAs($user)->get('/horizon')->assertStatus($allowed ? 200 : 403);
})->with(Role::names());

it('keeps a guest out of Pulse and Horizon', function (): void {
    $this->get('/pulse')->assertForbidden();
    $this->get('/horizon')->assertForbidden();
});

it('reads back the rows a Pulse card caches from a serializing cache store', function (): void {
    $store = Cache::store('file');
    $rows = collect([(object) [
        'latest' => CarbonImmutable::now(),
        'graph' => collect([1, 2]),
    ]]);

    $store->put('pulse-card', [$rows, 1.5, '2026-10-26 00:00:00'], 60);

    [$cachedRows] = $store->get('pulse-card');

    expect($cachedRows->first()->latest)->toBeInstanceOf(CarbonImmutable::class)
        ->and($cachedRows->first()->graph->sum())->toBe(3);

    $store->forget('pulse-card');
});

/** The user and tags the current Sentry scope would put on an event. */
function sentryScopeSnapshot(): Event
{
    $event = Event::createEvent();

    SentrySdk::getCurrentHub()->configureScope(function (Scope $scope) use (&$event): void {
        $event = $scope->applyToEvent($event);
    });

    return $event;
}

it('tells Sentry only the user id and role', function (): void {
    config(['sentry.dsn' => 'https://public@sentry.example.invalid/1']);
    $user = User::factory()->role(Role::Marketing)->create();

    event(new Authenticated('web', $user));

    $event = sentryScopeSnapshot();

    expect($event->getUser()?->getId())->toBe((string) $user->id)
        ->and($event->getUser()?->getEmail())->toBeNull()
        ->and($event->getUser()?->getUsername())->toBeNull()
        ->and($event->getUser()?->getIpAddress())->toBeNull()
        ->and($event->getTags())->toBe(['role' => 'marketing']);
});

it('tells Sentry nothing while no DSN is set', function (): void {
    config(['sentry.dsn' => null]);

    event(new Authenticated('web', User::factory()->role(Role::Client)->create()));

    expect(sentryScopeSnapshot()->getUser())->toBeNull();
});
