<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Routing\RedirectController;
use Illuminate\Support\Facades\Route;

/*
 * Route::redirect answers every method. Uncached, a later route on the same
 * address replaces it for its own method; cached (`artisan optimize`, as the
 * containers run), the first route wins and the redirect takes the request.
 * So no redirect may share its address with another route.
 */
it('gives no redirect an address another route also answers', function (): void {
    $routes = collect(Route::getRoutes()->getRoutes());
    $redirects = $routes->filter(fn ($route): bool => ltrim($route->getActionName(), '\\') === RedirectController::class);

    $shared = $redirects
        ->map(fn ($redirect): string => $redirect->uri())
        ->filter(fn (string $uri): bool => $routes->filter(fn ($route): bool => $route->uri() === $uri)->count() > 1)
        ->values()
        ->all();

    expect($redirects)->not->toBeEmpty()
        ->and($shared)->toBe([]);
});

it('sends the stone profile\'s old saved address to the new one', function (): void {
    $this->actingAs(User::factory()->role(Role::Client)->create())
        ->get('/dashboard/saved/cosmic-black')
        ->assertRedirect('/dashboard/saved/stones/cosmic-black')
        ->assertStatus(301);
});
