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

it('opens the Pulse and Horizon dashboards to the owner and the admin', function (Role $role): void {
    $user = User::factory()->role($role)->create();

    expect(Gate::forUser($user)->allows('viewPulse'))->toBeTrue()
        ->and(Gate::forUser($user)->allows('viewHorizon'))->toBeTrue();
})->with([Role::Owner, Role::Admin]);

it('keeps every other role out of the Pulse and Horizon dashboards', function (Role $role): void {
    $user = User::factory()->role($role)->create();

    expect(Gate::forUser($user)->allows('viewPulse'))->toBeFalse()
        ->and(Gate::forUser($user)->allows('viewHorizon'))->toBeFalse();
})->with([Role::Client, Role::Marketing, Role::Sales, Role::Accounting, Role::Technical, Role::Logistics]);

it('keeps a guest out of the Pulse and Horizon dashboards', function (): void {
    expect(Gate::allows('viewPulse'))->toBeFalse()
        ->and(Gate::allows('viewHorizon'))->toBeFalse();
});

it('answers 403 at /pulse and /horizon for anyone but the owner and the admin', function (): void {
    $marketing = User::factory()->role(Role::Marketing)->create();

    $this->get('/pulse')->assertForbidden();
    $this->get('/horizon')->assertForbidden();
    $this->actingAs($marketing)->get('/pulse')->assertForbidden();
    $this->actingAs($marketing)->get('/horizon')->assertForbidden();
});

it('opens /pulse and /horizon for the owner', function (): void {
    $owner = User::factory()->role(Role::Owner)->create();

    $this->actingAs($owner)->get('/pulse')->assertOk();
    $this->actingAs($owner)->get('/horizon')->assertOk();
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
