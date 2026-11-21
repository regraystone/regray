<?php

use App\Enums\AuditEvent;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Furniture;
use App\Models\Stone;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Archiving a brand: it leaves the Brands page and goes offline everywhere a
 * brand is read, and its stones and furniture with it. Saved items and past
 * inquiries keep showing them, as history. Its row and files stay.
 */

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->role(Role::Admin)->create();
    $this->client = User::factory()->role(Role::Client)->create();
    $this->disk = Storage::fake(config('filesystems.public_disk'));
    $this->disk->put('brands/acme-logo.png', 'logo');

    $this->other = Brand::factory()->create(['slug' => 'other-house', 'name' => 'Other House', 'sort_order' => 0]);
    $this->brand = Brand::factory()->create(['slug' => 'archived-acme', 'name' => 'Archived Acme', 'logo_path' => 'brands/acme-logo.png', 'sort_order' => 1]);
    $this->stone = Stone::factory()->for($this->brand)->create(['slug' => 'archived-acme-stone']);
    $this->furniture = Furniture::factory()->for($this->brand)->create(['slug' => 'archived-acme-table']);
    $this->archive = fn () => $this->actingAs($this->admin)->post(route('dashboard.brands.archive', $this->brand->id));
});

/** Whether the page's props mention the archived brand at all. */
function mentionsArchivedBrand(TestResponse $response): bool
{
    return str_contains(json_encode($response->viewData('page')['props']), 'archived-acme');
}

it('takes it off the Brands page, keeps its row and files, and audits it', function (): void {
    ($this->archive)()
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Archived Acme is archived.']);

    $this->actingAs($this->admin)
        ->get(route('dashboard.brands.index'))
        ->assertInertia(fn (Assert $page) => $page->where('brands.meta.total', 1)->where('brands.data.0.slug', 'other-house'));

    expect($this->brand->refresh()->archived_at)->not->toBeNull()
        ->and($this->brand->logo_path)->toBe('brands/acme-logo.png')
        ->and(AuditLog::query()->where('event', AuditEvent::Archived)->sole()->auditable_id)->toBe($this->brand->id);

    $this->disk->assertExists('brands/acme-logo.png');

    // Gone from the page, so it is not found to archive or edit again.
    ($this->archive)()->assertNotFound();
});

it('takes its pages offline, its products\' pages with them', function (string $route, array $parameters): void {
    $this->get(route($route, $parameters))->assertOk();

    ($this->archive)();

    $this->get(route($route, $parameters))->assertNotFound();
    $this->actingAs($this->client)->get(route("dashboard.{$route}", $parameters))->assertNotFound();
})->with([
    'its page' => ['brands.show', ['brand' => 'archived-acme']],
    'a stone\'s page' => ['brands.stones.show', ['brand' => 'archived-acme', 'stone' => 'archived-acme-stone']],
    'a piece of furniture\'s page' => ['brands.furniture.show', ['brand' => 'archived-acme', 'furniture' => 'archived-acme-table']],
]);

it('takes it off every page that lists brands', function (string $route, array $parameters, bool $signedIn): void {
    $visit = fn () => $signedIn
        ? $this->actingAs($this->client)->get(route($route, $parameters))
        : $this->get(route($route, $parameters));

    expect(mentionsArchivedBrand($visit()))->toBeTrue();

    ($this->archive)();

    if (! $signedIn) {
        auth()->logout();
    }

    expect(mentionsArchivedBrand($visit()))->toBeFalse();
})->with([
    'the landing page' => ['home', [], false],
    'Our brands' => ['company.brands', [], false],
    'the home page' => ['home.index', [], true],
    'the Inquire brand question' => ['quote.inquire', [], true],
    'More from the Regray Group' => ['brands.show', ['brand' => 'other-house'], false],
]);

it('hides its stones and furniture from the catalogue, and the Inquire flow refuses them', function (): void {
    expect(Stone::query()->active()->pluck('slug')->all())->toContain('archived-acme-stone')
        ->and(Furniture::query()->active()->pluck('slug')->all())->toContain('archived-acme-table');

    ($this->archive)();

    expect(Stone::query()->active()->pluck('slug')->all())->not->toContain('archived-acme-stone')
        ->and(Furniture::query()->active()->pluck('slug')->all())->not->toContain('archived-acme-table')
        ->and(Brand::query()->active()->pluck('slug')->all())->toBe(['other-house']);

    // The old flat address of the stone finds nothing either.
    $this->get('/archived-acme-stone')->assertNotFound();
});

it('keeps a saved stone saved, as history', function (): void {
    $this->actingAs($this->client)->put(route('saved.store', 'archived-acme-stone'))->assertRedirect();

    ($this->archive)();

    $this->actingAs($this->client)
        ->get(route('saved.index'))
        ->assertInertia(fn (Assert $page) => $page->where('savedStones.data.0.slug', 'archived-acme-stone'));
});

it('keeps a past inquiry\'s stones on it', function (): void {
    seedInquiryCatalog();
    $stone = Stone::query()->active()->orderBy('id')->firstOrFail();
    makeInquiry($this->client, ['name' => 'Kitchen', 'stones' => [$stone->slug], 'service' => 'countertop-backsplash']);

    $this->actingAs($this->admin)->post(route('dashboard.brands.archive', $stone->brand_id))->assertRedirect();

    $card = $this->actingAs($this->client)->get(route('quote.index'))->viewData('page')['props']['inquiries']['data'][0];

    expect(Stone::query()->active()->whereKey($stone->id)->exists())->toBeFalse()
        ->and(collect($card['stones'])->pluck('slug')->all())->toBe([$stone->slug]);
});
