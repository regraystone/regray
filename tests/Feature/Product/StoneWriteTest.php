<?php

use App\Enums\AuditEvent;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Size;
use App\Models\Stone;
use App\Models\StoneType;
use App\Models\Thickness;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
 * Add and Edit stone on the Products page: the form's rules, its types and
 * sizes, and its images, stored under random names and deleted once replaced
 * or taken off; turning a stone on or off; archiving it.
 */

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->role(Role::Admin)->create();
    $this->disk = Storage::fake(config('filesystems.public_disk'));
    $this->brand = Brand::factory()->create(['name' => 'Cucine Lube']);
    $this->marble = StoneType::factory()->create(['name' => 'Marble', 'slug' => 'marble']);
    $this->quartz = StoneType::factory()->create(['name' => 'Quartz', 'slug' => 'quartz']);
    $this->slab = Size::factory()->create(['width' => '160', 'length' => '320']);
    $this->tile = Size::factory()->create(['width' => '60', 'length' => '120']);
    $this->thin = Thickness::factory()->create(['value' => '12']);
    $this->thick = Thickness::factory()->create(['value' => '20']);
});

/** @return array<string, mixed> */
function stoneForm(array $overrides = []): array
{
    return [
        'brand_id' => test()->brand->id,
        'stone_type_id' => test()->marble->id,
        'types' => [test()->quartz->id],
        'name' => 'Calacatta Oro',
        'slug' => 'calacatta-oro',
        'origin' => 'Italy',
        'size_note' => '',
        'is_active' => '1',
        'variants' => [
            ['size_id' => test()->slab->id, 'thickness_id' => test()->thin->id],
            ['size_id' => test()->slab->id, 'thickness_id' => test()->thick->id],
            ['size_id' => test()->tile->id, 'thickness_id' => test()->thick->id],
        ],
        'thumbnail' => UploadedFile::fake()->image('cover.jpg', 800, 1000),
        'gallery' => [
            ['file' => UploadedFile::fake()->image('kitchen.png', 800, 600)],
            ['file' => UploadedFile::fake()->image('bath.webp', 800, 600)],
        ],
        ...$overrides,
    ];
}

/** @return list<string> */
function stoneFiles(): array
{
    return Storage::disk(config('filesystems.public_disk'))->allFiles('stones');
}

/** @return list<string> The stone's variants as "size x thickness" ids, in order. */
function variantsOf(Stone $stone): array
{
    return $stone->variants()->get()->map(fn ($variant): string => ($variant->size_id ?? 'none').'x'.$variant->thickness_id)->all();
}

describe('adding a stone', function (): void {
    it('saves it with its types, sizes in order and images under random names', function (): void {
        $this->actingAs($this->admin)
            ->post(route('dashboard.products.stones.store'), stoneForm())
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Calacatta Oro is added.']);

        $stone = Stone::query()->where('slug', 'calacatta-oro')->sole();
        $gallery = $stone->images()->orderBy('sort_order')->pluck('path')->all();

        expect($stone->brand_id)->toBe($this->brand->id)
            ->and($stone->stone_type_id)->toBe($this->marble->id)
            ->and($stone->types()->orderBy('stone_types.id')->pluck('stone_types.id')->all())->toBe([$this->marble->id, $this->quartz->id])
            ->and(variantsOf($stone))->toBe([
                "{$this->slab->id}x{$this->thin->id}",
                "{$this->slab->id}x{$this->thick->id}",
                "{$this->tile->id}x{$this->thick->id}",
            ])
            ->and($stone->origin)->toBe('Italy')
            ->and($stone->size_note)->toBeNull()
            ->and($stone->is_active)->toBeTrue()
            ->and($stone->thumbnail_path)->toStartWith('stones/')->not->toContain('cover')
            ->and($gallery)->toHaveCount(2)
            ->and(stoneFiles())->toHaveCount(3);

        expect(AuditLog::query()->where('auditable_type', Stone::class)->where('auditable_id', $stone->id)->sole()->event)->toBe(AuditEvent::Created);
    });

    it('goes after the brand\'s last stone', function (): void {
        Stone::factory()->for($this->brand)->create(['sort_order' => 7]);

        $this->actingAs($this->admin)->post(route('dashboard.products.stones.store'), stoneForm())->assertSessionHasNoErrors();

        expect(Stone::query()->where('slug', 'calacatta-oro')->value('sort_order'))->toBe(8);
    });

    it('takes a stone sold without set sizes, with its note', function (): void {
        $this->actingAs($this->admin)->post(route('dashboard.products.stones.store'), stoneForm([
            'size_note' => 'Diameter 15–40 cm',
            'variants' => [['size_id' => '', 'thickness_id' => $this->thin->id], ['size_id' => '', 'thickness_id' => $this->thick->id]],
        ]))->assertSessionHasNoErrors();

        $stone = Stone::query()->where('slug', 'calacatta-oro')->sole();

        expect(variantsOf($stone))->toBe(["nonex{$this->thin->id}", "nonex{$this->thick->id}"])
            ->and($stone->size_note)->toBe('Diameter 15–40 cm');
    });

    it('refuses what the form does not allow, and stores nothing', function (array $overrides, string $field): void {
        Stone::factory()->for($this->brand)->create(['name' => 'Taken Name', 'slug' => 'taken']);
        Stone::factory()->for($this->brand)->create(['slug' => 'archived-one'])->delete();
        makeShowcase(['slug' => 'a-showcase']);

        $this->actingAs($this->admin)
            ->post(route('dashboard.products.stones.store'), stoneForm(value($overrides)))
            ->assertSessionHasErrors($field);

        expect(Stone::query()->where('slug', 'calacatta-oro')->exists())->toBeFalse()
            ->and(stoneFiles())->toBe([]);
    })->with([
        'no name' => [['name' => ' '], 'name'],
        'a name taken, in another case' => [['name' => 'taken name'], 'name'],
        'a bad slug' => [['slug' => 'Not A Slug'], 'slug'],
        'a reserved slug' => [['slug' => 'dashboard'], 'slug'],
        'a slug taken' => [['slug' => 'taken'], 'slug'],
        'an archived stone\'s slug' => [['slug' => 'archived-one'], 'slug'],
        'a showcase\'s slug' => [['slug' => 'a-showcase'], 'slug'],
        'no origin' => [['origin' => ''], 'origin'],
        'no thumbnail' => [['thumbnail' => null], 'thumbnail'],
        'a thumbnail not an image' => [fn () => ['thumbnail' => UploadedFile::fake()->create('cover.pdf', 10, 'application/pdf')], 'thumbnail'],
        'an archived brand' => [fn () => ['brand_id' => Brand::factory()->create(['archived_at' => now()])->id], 'brand_id'],
        'an inactive type' => [fn () => ['stone_type_id' => StoneType::factory()->create(['is_active' => false])->id], 'stone_type_id'],
        'no sizes' => [['variants' => []], 'variants'],
        'a size and thickness twice' => [fn () => ['variants' => [['size_id' => test()->slab->id, 'thickness_id' => test()->thin->id], ['size_id' => test()->slab->id, 'thickness_id' => test()->thin->id]]], 'variants.1.thickness_id'],
        '"no set size" and a thickness twice' => [fn () => ['variants' => [['size_id' => '', 'thickness_id' => test()->thin->id], ['size_id' => '', 'thickness_id' => test()->thin->id]]], 'variants.1.thickness_id'],
        'a size without a thickness' => [fn () => ['variants' => [['size_id' => test()->slab->id, 'thickness_id' => '']]], 'variants.0.thickness_id'],
        'an archived thickness' => [fn () => ['variants' => [['size_id' => test()->slab->id, 'thickness_id' => tap(Thickness::factory()->create(['value' => '30']))->delete()->id]]], 'variants.0.thickness_id'],
        'too many photos' => [fn () => ['gallery' => array_fill(0, 13, ['file' => UploadedFile::fake()->image('a.jpg')])], 'gallery'],
    ]);
});

describe('changing a stone', function (): void {
    beforeEach(function (): void {
        $this->actingAs($this->admin)->post(route('dashboard.products.stones.store'), stoneForm())->assertSessionHasNoErrors();
        $this->stone = Stone::query()->where('slug', 'calacatta-oro')->sole();
    });

    it('keeps the thumbnail unless a new one is sent, and reorders, keeps and takes off photos', function (): void {
        [$first, $second] = $this->stone->images()->orderBy('sort_order')->get()->all();
        $thumbnail = $this->stone->thumbnail_path;

        $this->actingAs($this->admin)->post(route('dashboard.products.stones.update', $this->stone->id), [
            ...stoneForm(['thumbnail' => null, 'name' => 'Calacatta Gold', 'gallery' => [
                ['id' => $second->id],
                ['file' => UploadedFile::fake()->image('new.jpg')],
            ]]),
            '_method' => 'put',
        ])->assertSessionHasNoErrors()->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Calacatta Gold is updated.']);

        $this->stone->refresh();
        $paths = $this->stone->images()->orderBy('sort_order')->pluck('path')->all();

        expect($this->stone->name)->toBe('Calacatta Gold')
            ->and($this->stone->thumbnail_path)->toBe($thumbnail)
            ->and($paths)->toHaveCount(2)
            ->and($paths[0])->toBe($second->path)
            ->and(stoneFiles())->not->toContain($first->path)
            ->and(stoneFiles())->toHaveCount(3);
    });

    it('replaces the thumbnail and deletes the old file', function (): void {
        $old = $this->stone->thumbnail_path;

        $this->actingAs($this->admin)->post(route('dashboard.products.stones.update', $this->stone->id), [
            ...stoneForm(['gallery' => $this->stone->images()->orderBy('sort_order')->get()->map(fn ($image) => ['id' => $image->id])->all()]),
            '_method' => 'put',
        ])->assertSessionHasNoErrors();

        expect($this->stone->refresh()->thumbnail_path)->not->toBe($old)
            ->and(stoneFiles())->not->toContain($old)
            ->and(stoneFiles())->toHaveCount(3);
    });

    it('keeps a file the stone still uses elsewhere', function (): void {
        // A seeded stone's thumbnail could also be a gallery photo.
        $image = $this->stone->images()->orderBy('sort_order')->first();
        $this->stone->forceFill(['thumbnail_path' => $image->path])->save();

        $this->actingAs($this->admin)->post(route('dashboard.products.stones.update', $this->stone->id), [
            ...stoneForm(['gallery' => [['id' => $image->id]]]),
            '_method' => 'put',
        ])->assertSessionHasNoErrors();

        expect(stoneFiles())->toContain($image->path);
    });

    it('rewrites its sizes and types as the form lists them', function (): void {
        $this->actingAs($this->admin)->post(route('dashboard.products.stones.update', $this->stone->id), [
            ...stoneForm(['thumbnail' => null, 'gallery' => [], 'types' => [], 'variants' => [
                ['size_id' => $this->tile->id, 'thickness_id' => $this->thin->id],
            ]]),
            '_method' => 'put',
        ])->assertSessionHasNoErrors();

        expect(variantsOf($this->stone))->toBe(["{$this->tile->id}x{$this->thin->id}"])
            ->and($this->stone->types()->pluck('stone_types.id')->all())->toBe([$this->marble->id]);
    });

    it('keeps its own name and slug, and refuses a photo of another stone', function (): void {
        $other = Stone::factory()->for($this->brand)->create();
        $foreign = $other->images()->create(['path' => 'stones/other.jpg', 'sort_order' => 0]);

        $this->actingAs($this->admin)->post(route('dashboard.products.stones.update', $this->stone->id), [
            ...stoneForm(['thumbnail' => null, 'gallery' => []]),
            '_method' => 'put',
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->post(route('dashboard.products.stones.update', $this->stone->id), [
            ...stoneForm(['thumbnail' => null, 'gallery' => [['id' => $foreign->id]]]),
            '_method' => 'put',
        ])->assertSessionHasErrors('gallery.0.id');
    });

    it('turns it off and on, and audits each', function (): void {
        $this->actingAs($this->admin)->patch(route('dashboard.products.stones.status', $this->stone->id), ['active' => false])
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Calacatta Oro is hidden from Regray.']);

        expect($this->stone->refresh()->is_active)->toBeFalse();

        $this->actingAs($this->admin)->patch(route('dashboard.products.stones.status', $this->stone->id), ['active' => true]);

        expect($this->stone->refresh()->is_active)->toBeTrue()
            ->and(AuditLog::query()->where('auditable_id', $this->stone->id)->where('event', AuditEvent::StatusChanged->value)->count())->toBe(2);
    });

    it('archives it: it leaves the page, its page goes offline, its files stay', function (): void {
        $files = stoneFiles();

        $this->actingAs($this->admin)->post(route('dashboard.products.stones.archive', $this->stone->id))
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Calacatta Oro is archived.']);

        expect($this->stone->refresh()->trashed())->toBeTrue()
            ->and(stoneFiles())->toBe($files);

        auth()->logout();
        $this->get(route('brands.stones.show', ['brand' => $this->brand->slug, 'stone' => 'calacatta-oro']))->assertNotFound();
        $this->actingAs($this->admin)->post(route('dashboard.products.stones.archive', $this->stone->id))->assertNotFound();
    });
});

it('lets only those allowed add, change or archive', function (): void {
    $stone = Stone::factory()->for($this->brand)->create();
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(Permission::ProductsView->value);

    $this->actingAs($viewer)->post(route('dashboard.products.stones.store'), stoneForm())->assertForbidden();
    $this->actingAs($viewer)->post(route('dashboard.products.stones.update', $stone->id), [...stoneForm(), '_method' => 'put'])->assertForbidden();
    $this->actingAs($viewer)->patch(route('dashboard.products.stones.status', $stone->id), ['active' => false])->assertForbidden();
    $this->actingAs($viewer)->post(route('dashboard.products.stones.archive', $stone->id))->assertForbidden();
    $this->actingAs(User::factory()->role(Role::Owner)->create())->post(route('dashboard.products.stones.store'), stoneForm())->assertForbidden();

    expect(Stone::query()->count())->toBe(1)
        ->and($stone->refresh()->is_active)->toBeTrue()
        ->and(stoneFiles())->toBe([]);
});

it('is a 404 for an id that is not a stone on the page', function (): void {
    $this->actingAs($this->admin)->patch(route('dashboard.products.stones.status', 'abc'), ['active' => false])->assertNotFound();
    $this->actingAs($this->admin)->patch(route('dashboard.products.stones.status', 999999), ['active' => false])->assertNotFound();
});
