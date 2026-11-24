<?php

use App\Enums\Role;
use App\Models\User;
use App\Support\ReservedSlugs;
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

/*
 * The public catch-all (`/{slug}`, a showcase or a stone's old address) leaves
 * every reserved name alone (ReservedSlugs), so the old workspace addresses
 * registered after it still redirect rather than look for a showcase.
 */
it('sends the old workspace addresses on, past the public catch-all', function (string $from, string $to): void {
    $this->get($from)->assertStatus(301)->assertRedirect($to);
})->with([
    ['/home', '/dashboard/home'],
    ['/home/ginza-one', '/dashboard/home/ginza-one'],
    ['/overview', '/dashboard/overview'],
    ['/saved', '/dashboard/saved'],
    ['/sales', '/dashboard/sales'],
    ['/quote', '/dashboard/quote'],
    ['/inquiry', '/dashboard/inquiry'],
    ['/leads', '/dashboard/leads'],
    ['/schedule', '/dashboard/schedule'],
    ['/message', '/dashboard/inbox'],
    ['/projects', '/dashboard/showcases'],
    ['/stones', '/dashboard/products'],
    ['/products', '/dashboard/products'],
    ['/services', '/dashboard/services'],
    ['/showcases', '/dashboard/showcases'],
    ['/accounts', '/dashboard/accounts'],
    ['/profile', '/dashboard/profile'],
]);

it('leaves the catch-all every name that is not reserved, a reserved word inside one included', function (): void {
    $pattern = '#^'.ReservedSlugs::catchAllPattern().'$#';

    expect(preg_match($pattern, 'home'))->toBe(0)
        ->and(preg_match($pattern, 'robots.txt'))->toBe(0)
        ->and(preg_match($pattern, 'homestead'))->toBe(1)
        ->and(preg_match($pattern, 'my-home'))->toBe(1)
        ->and(preg_match($pattern, 'ginza-one'))->toBe(1);
});

it('sends a stone\'s oldest workspace address to its profile under Home', function (): void {
    $this->actingAs(User::factory()->role(Role::Client)->create())
        ->get('/dashboard/home-stone/cosmic-black')
        ->assertStatus(301)
        ->assertRedirect('/dashboard/home/stones/cosmic-black');
});
