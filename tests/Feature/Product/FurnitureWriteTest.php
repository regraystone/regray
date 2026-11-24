<?php

use App\Enums\AuditEvent;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Furniture;
use App\Models\FurnitureType;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
 * Add and Edit furniture on the Products page: the form's rules and its
 * images (the thumbnail optional), turning a piece on or off, archiving it.
 */

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->role(Role::Admin)->create();
    $this->disk = Storage::fake(config('filesystems.public_disk'));
    $this->brand = Brand::factory()->create(['name' => 'Regray Stone']);
    $this->tables = FurnitureType::factory()->create(['name' => 'Dining Tables']);
});

/** @return array<string, mixed> */
function furnitureForm(array $overrides = []): array
{
    return [
        'brand_id' => test()->brand->id,
        'furniture_type_id' => test()->tables->id,
        'name' => 'Alora',
        'slug' => 'alora',
        'description' => '  A table in stone.  ',
        'is_active' => '1',
        'thumbnail' => UploadedFile::fake()->image('alora.jpg', 1000, 1000),
        'gallery' => [['file' => UploadedFile::fake()->image('side.jpg', 800, 800)]],
        ...$overrides,
    ];
}

/** @return list<string> */
function furnitureFiles(): array
{
    return Storage::disk(config('filesystems.public_disk'))->allFiles('furniture');
}

it('adds a piece with its images under random names, and audits it', function (): void {
    $this->actingAs($this->admin)
        ->post(route('dashboard.products.furniture.store'), furnitureForm())
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Alora is added.']);

    $piece = Furniture::query()->where('slug', 'alora')->sole();

    expect($piece->description)->toBe('A table in stone.')
        ->and($piece->furniture_type_id)->toBe($this->tables->id)
        ->and($piece->thumbnail_path)->toStartWith('furniture/')->not->toContain('alora')
        ->and($piece->images()->count())->toBe(1)
        ->and(furnitureFiles())->toHaveCount(2)
        ->and(AuditLog::query()->where('auditable_type', Furniture::class)->sole()->event)->toBe(AuditEvent::Created);

    auth()->logout();
    $this->get(route('brands.furniture.show', ['brand' => $this->brand->slug, 'furniture' => 'alora']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('furniture.data.gallery', 1));
});

it('adds a piece without a thumbnail or description', function (): void {
    $this->actingAs($this->admin)
        ->post(route('dashboard.products.furniture.store'), furnitureForm(['thumbnail' => null, 'description' => '', 'gallery' => []]))
        ->assertSessionHasNoErrors();

    expect(Furniture::query()->where('slug', 'alora')->sole())
        ->thumbnail_path->toBeNull()
        ->description->toBeNull();
});

it('refuses what the form does not allow, and stores nothing', function (array $overrides, string $field): void {
    Furniture::factory()->for($this->brand)->create(['slug' => 'taken']);
    Furniture::factory()->for($this->brand)->create(['slug' => 'archived-one'])->delete();

    $this->actingAs($this->admin)
        ->post(route('dashboard.products.furniture.store'), furnitureForm(value($overrides)))
        ->assertSessionHasErrors($field);

    expect(Furniture::query()->where('slug', 'alora')->exists())->toBeFalse()
        ->and(furnitureFiles())->toBe([]);
})->with([
    'no name' => [['name' => ''], 'name'],
    'a slug taken' => [['slug' => 'taken'], 'slug'],
    'an archived piece\'s slug' => [['slug' => 'archived-one'], 'slug'],
    'a reserved slug' => [['slug' => 'dashboard'], 'slug'],
    'no type' => [['furniture_type_id' => ''], 'furniture_type_id'],
    'an inactive type' => [fn () => ['furniture_type_id' => FurnitureType::factory()->create(['is_active' => false])->id], 'furniture_type_id'],
    'a description too long' => [['description' => str_repeat('a', 2001)], 'description'],
    'a photo not an image' => [fn () => ['gallery' => [['file' => UploadedFile::fake()->create('a.pdf', 5, 'application/pdf')]]], 'gallery.0.file'],
]);

it('changes a piece: keeps the thumbnail unless a new one is sent, and takes off a photo and its file', function (): void {
    $this->actingAs($this->admin)->post(route('dashboard.products.furniture.store'), furnitureForm())->assertSessionHasNoErrors();
    $piece = Furniture::query()->where('slug', 'alora')->sole();
    $photo = $piece->images()->sole();
    $thumbnail = $piece->thumbnail_path;

    $this->actingAs($this->admin)->post(route('dashboard.products.furniture.update', $piece->id), [
        ...furnitureForm(['thumbnail' => null, 'gallery' => [], 'name' => 'Alora II', 'is_active' => '0']),
        '_method' => 'put',
    ])->assertSessionHasNoErrors();

    $piece->refresh();

    expect($piece->name)->toBe('Alora II')
        ->and($piece->is_active)->toBeFalse()
        ->and($piece->thumbnail_path)->toBe($thumbnail)
        ->and($piece->images()->count())->toBe(0)
        ->and(furnitureFiles())->toBe([$thumbnail]);

    expect(furnitureFiles())->not->toContain($photo->path);

    $audit = AuditLog::query()->where('auditable_type', Furniture::class)->where('auditable_id', $piece->id)->where('event', AuditEvent::Updated->value)->sole();
    expect($audit->old_values['name'])->toBe('Alora')
        ->and($audit->new_values['name'])->toBe('Alora II');
});

it('is a 404 for a piece of an archived brand, which the page no longer lists', function (): void {
    $piece = Furniture::factory()->for(Brand::factory()->create(['archived_at' => now()]))->create();

    $this->actingAs($this->admin)->patch(route('dashboard.products.furniture.status', $piece->id), ['active' => false])->assertNotFound();
    $this->actingAs($this->admin)->post(route('dashboard.products.furniture.update', $piece->id), [...furnitureForm(), '_method' => 'put'])->assertNotFound();
    $this->actingAs($this->admin)->post(route('dashboard.products.furniture.archive', $piece->id))->assertNotFound();

    expect($piece->refresh()->brand_id)->not->toBe($this->brand->id);
});

it('turns a piece off and archives it', function (): void {
    $piece = Furniture::factory()->for($this->brand)->create(['name' => 'Casa', 'slug' => 'casa']);

    $this->actingAs($this->admin)->patch(route('dashboard.products.furniture.status', $piece->id), ['active' => false])
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Casa is hidden from Regray.']);
    expect($piece->refresh()->is_active)->toBeFalse();

    $this->actingAs($this->admin)->post(route('dashboard.products.furniture.archive', $piece->id))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Casa is archived.']);

    expect($piece->refresh()->trashed())->toBeTrue()
        ->and(AuditLog::query()->where('auditable_type', Furniture::class)->pluck('event')->map->value->all())->toBe(['status_changed', 'archived']);

    auth()->logout();
    $this->get(route('brands.furniture.show', ['brand' => $this->brand->slug, 'furniture' => 'casa']))->assertNotFound();
});

it('lets only those allowed add, change or archive', function (): void {
    $piece = Furniture::factory()->for($this->brand)->create();
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(Permission::ProductsView->value);

    $this->actingAs($viewer)->post(route('dashboard.products.furniture.store'), furnitureForm())->assertForbidden();
    $this->actingAs($viewer)->post(route('dashboard.products.furniture.update', $piece->id), [...furnitureForm(), '_method' => 'put'])->assertForbidden();
    $this->actingAs($viewer)->patch(route('dashboard.products.furniture.status', $piece->id), ['active' => false])->assertForbidden();
    $this->actingAs($viewer)->post(route('dashboard.products.furniture.archive', $piece->id))->assertForbidden();

    expect(Furniture::query()->count())->toBe(1)
        ->and(furnitureFiles())->toBe([]);
});
