<?php

use App\Enums\AuditEvent;
use App\Enums\CatalogList;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\CareTip;
use App\Models\EdgeProfile;
use App\Models\Finish;
use App\Models\Furniture;
use App\Models\FurnitureType;
use App\Models\InquiryStone;
use App\Models\Size;
use App\Models\Stone;
use App\Models\StoneType;
use App\Models\StoneVariant;
use App\Models\Thickness;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The lists under Products (CatalogList), one page for each: a page of its
 * items, searched; adding, changing, turning on or off and archiving one,
 * audited; archiving refused while a product or stone type uses it; and the
 * same permissions as products.
 */

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->role(Role::Admin)->create();
    $this->disk = Storage::fake(config('filesystems.public_disk'));
});

function listUrl(CatalogList $list, ?int $id = null, string $action = ''): string
{
    return '/dashboard/products/'.$list->value.($id === null ? '' : '/'.$id).($action === '' ? '' : '/'.$action);
}

it('shows each list on its own page', function (CatalogList $list): void {
    $this->actingAs($this->admin)->get(listUrl($list))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('catalog-list')
            ->where('list.slug', $list->value)
            ->where('list.title', $list->definition()->title())
            ->has('items.data')
            ->where('canCreate', true));
})->with(CatalogList::cases());

it('is a 404 for a list it does not know', function (): void {
    $this->actingAs($this->admin)->get('/dashboard/products/colours')->assertNotFound();
});

it('lists the items with what uses them, searched', function (): void {
    $marble = StoneType::factory()->create(['name' => 'Marble', 'slug' => 'marble']);
    StoneType::factory()->create(['name' => 'Granite', 'slug' => 'granite']);
    Stone::factory()->count(2)->for(Brand::factory())->create(['stone_type_id' => $marble->id]);

    $this->actingAs($this->admin)->get(listUrl(CatalogList::StoneTypes).'?search=marb')->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('items.data', 1)
            ->where('items.data.0.name', 'Marble')
            ->where('items.data.0.cells.stones', '2 stones'));
});

it('lists a size by its label, how many stones come in it', function (): void {
    $size = Size::factory()->create(['width' => '160', 'length' => '320', 'unit' => 'cm']);
    $stone = Stone::factory()->for(Brand::factory())->create();
    StoneVariant::query()->create(['stone_id' => $stone->id, 'size_id' => $size->id, 'thickness_id' => Thickness::factory()->create()->id, 'sort_order' => 0]);

    $this->actingAs($this->admin)->get(listUrl(CatalogList::Sizes).'?search=160 x')->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('items.data', 1)
            ->where('items.data.0.name', '160 x 320 cm')
            ->where('items.data.0.cells.stones', '1 stone')
            ->where('items.data.0.values', ['width' => '160', 'length' => '320', 'unit' => 'cm']));
});

it('adds an item to a list, audited', function (CatalogList $list, array $form, string $model, array $saved): void {
    $this->actingAs($this->admin)->post(listUrl($list), $form)->assertRedirect()->assertSessionHasNoErrors();

    $item = $model::query()->latest('id')->firstOrFail();
    expect($item->only(array_keys($saved)))->toEqual($saved)
        ->and(AuditLog::query()->where('auditable_id', $item->id)->where('event', AuditEvent::Created)->exists())->toBeTrue();
})->with([
    'size' => [CatalogList::Sizes, ['width' => '60', 'length' => '120.5', 'unit' => 'cm'], Size::class, ['width' => '60.00', 'length' => '120.50']],
    'thickness' => [CatalogList::Thicknesses, ['value' => '0.75', 'unit' => 'in'], Thickness::class, ['value' => '0.75']],
    'finish' => [CatalogList::Finishes, ['name' => ' Bush-hammered ', 'is_advisory' => '0', 'is_active' => '1', 'sort_order' => ''], Finish::class, ['name' => 'Bush-hammered', 'is_advisory' => false, 'is_active' => true]],
    'edge profile' => [CatalogList::EdgeProfiles, ['name' => 'Ogee', 'is_advisory' => '0', 'is_active' => '1', 'sort_order' => '4'], EdgeProfile::class, ['name' => 'Ogee', 'sort_order' => 4]],
    'furniture type' => [CatalogList::FurnitureTypes, ['name' => 'Sofas', 'slug' => 'sofas', 'is_active' => '1', 'sort_order' => ''], FurnitureType::class, ['name' => 'Sofas', 'slug' => 'sofas']],
    'application' => [CatalogList::Applications, ['name' => 'Kitchen countertops'], Application::class, ['name' => 'Kitchen countertops']],
    'care tip' => [CatalogList::CareTips, ['tip' => 'Seal it once a year.'], CareTip::class, ['tip' => 'Seal it once a year.']],
]);

it('adds a stone type with its thumbnail, finishes, applications and care tips in order', function (): void {
    $honed = Finish::query()->create(['name' => 'Honed', 'is_advisory' => false, 'is_active' => true, 'sort_order' => 0]);
    $polished = Finish::query()->create(['name' => 'Polished', 'is_advisory' => false, 'is_active' => true, 'sort_order' => 1]);
    $floors = Application::query()->create(['name' => 'Floors']);
    $tip = CareTip::query()->create(['tip' => 'Wipe spills at once.']);

    $this->actingAs($this->admin)->post(listUrl(CatalogList::StoneTypes), [
        'name' => 'Onyx',
        'slug' => 'Onyx',
        'description' => 'A translucent stone.',
        'thumbnail' => UploadedFile::fake()->image('onyx.jpg', 600, 600),
        'finishes' => [$polished->id, $honed->id],
        'applications' => [$floors->id],
        'care_tips' => [$tip->id],
        'sort_order' => '',
        'is_active' => '1',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $type = StoneType::query()->where('slug', 'onyx')->firstOrFail();
    expect($type->finishes->modelKeys())->toBe([$polished->id, $honed->id])
        ->and($type->applications->modelKeys())->toBe([$floors->id])
        ->and($type->careTips->modelKeys())->toBe([$tip->id])
        ->and($type->thumbnail_path)->toStartWith('stone-types/');
    $this->disk->assertExists($type->thumbnail_path);
});

it('sets a stone type\'s edge profiles in order, and refuses an "advise me" one', function (): void {
    $type = StoneType::factory()->create(['thumbnail_path' => 'stone-types/a.jpg']);
    $ogee = EdgeProfile::query()->create(['name' => 'Ogee', 'is_advisory' => false, 'is_active' => true, 'sort_order' => 0]);
    $bevel = EdgeProfile::query()->create(['name' => 'Bevel', 'is_advisory' => false, 'is_active' => true, 'sort_order' => 1]);
    $advise = EdgeProfile::query()->create(['name' => 'Not sure yet', 'is_advisory' => true, 'is_active' => true, 'sort_order' => 2]);
    $form = ['name' => $type->name, 'slug' => $type->slug, 'description' => 'A stone.', 'is_active' => '1'];

    $this->actingAs($this->admin)->put(listUrl(CatalogList::StoneTypes, $type->id), [...$form, 'edge_profiles' => [$bevel->id, $ogee->id]])
        ->assertSessionHasNoErrors();
    expect($type->edgeProfiles()->get()->modelKeys())->toBe([$bevel->id, $ogee->id]);

    $this->actingAs($this->admin)->put(listUrl(CatalogList::StoneTypes, $type->id), [...$form, 'edge_profiles' => [$advise->id]])
        ->assertSessionHasErrors('edge_profiles.0');

    $this->actingAs($this->admin)->get(listUrl(CatalogList::StoneTypes))
        ->assertInertia(fn (Assert $page) => $page->where('list.fields', fn ($fields) => collect(collect($fields)->firstWhere('name', 'edge_profiles')['options'])->pluck('label')->all() === ['Ogee', 'Bevel']));
});

it('needs a stone type\'s thumbnail when adding it, not when changing it', function (): void {
    $this->actingAs($this->admin)->post(listUrl(CatalogList::StoneTypes), ['name' => 'Onyx', 'slug' => 'onyx', 'description' => 'A stone.', 'is_active' => '1'])
        ->assertSessionHasErrors(['thumbnail' => 'Add the stone type\'s thumbnail.']);

    $type = StoneType::factory()->create(['thumbnail_path' => 'stone-types/old.jpg']);
    $this->disk->put('stone-types/old.jpg', 'x');

    $this->actingAs($this->admin)->put(listUrl(CatalogList::StoneTypes, $type->id), ['name' => 'Onyx', 'slug' => 'onyx', 'description' => 'A stone.', 'is_active' => '1'])
        ->assertSessionHasNoErrors();
    expect($type->fresh()->thumbnail_path)->toBe('stone-types/old.jpg');

    $this->actingAs($this->admin)->put(listUrl(CatalogList::StoneTypes, $type->id), ['name' => 'Onyx', 'slug' => 'onyx', 'description' => 'A stone.', 'is_active' => '1', 'thumbnail' => UploadedFile::fake()->image('new.png')])
        ->assertSessionHasNoErrors();
    $this->disk->assertMissing('stone-types/old.jpg');
});

it('keeps a replaced thumbnail a stone still shows', function (): void {
    $type = StoneType::factory()->create(['thumbnail_path' => 'stones/shared.png']);
    $this->disk->put('stones/shared.png', 'x');
    Stone::factory()->for(Brand::factory())->create(['thumbnail_path' => 'stones/shared.png']);

    $this->actingAs($this->admin)->put(listUrl(CatalogList::StoneTypes, $type->id), ['name' => $type->name, 'slug' => $type->slug, 'description' => 'A stone.', 'is_active' => '1', 'thumbnail' => UploadedFile::fake()->image('new.png')])
        ->assertSessionHasNoErrors();

    $this->disk->assertExists('stones/shared.png');
});

it('refuses a size or thickness already on the list, archived or not', function (): void {
    Size::factory()->create(['width' => '160', 'length' => '320', 'unit' => 'cm']);
    $archived = Thickness::factory()->create(['value' => '20', 'unit' => 'mm']);
    $archived->delete();

    $this->actingAs($this->admin)->post(listUrl(CatalogList::Sizes), ['width' => '160.0', 'length' => '320', 'unit' => 'cm'])
        ->assertSessionHasErrors(['width' => 'That size is already on the list.']);
    $this->actingAs($this->admin)->post(listUrl(CatalogList::Thicknesses), ['value' => '20', 'unit' => 'mm'])
        ->assertSessionHasErrors(['value' => 'That thickness was archived and can\'t be added again.']);
    $this->actingAs($this->admin)->post(listUrl(CatalogList::Sizes), ['width' => '160', 'length' => '320', 'unit' => 'in'])
        ->assertSessionHasNoErrors();
});

it('refuses a name already on the list', function (): void {
    Application::query()->create(['name' => 'Floors']);

    $this->actingAs($this->admin)->post(listUrl(CatalogList::Applications), ['name' => 'Floors'])
        ->assertSessionHasErrors(['name' => 'That application, maybe an archived one, is already on the list.']);
});

it('changes an item, audited with what it was', function (): void {
    $finish = Finish::query()->create(['name' => 'Honed', 'is_advisory' => false, 'is_active' => true, 'sort_order' => 3]);

    $this->actingAs($this->admin)->put(listUrl(CatalogList::Finishes, $finish->id), ['name' => 'Honed Matte', 'is_advisory' => '0', 'is_active' => '1', 'sort_order' => ''])
        ->assertSessionHasNoErrors();

    expect($finish->fresh()->only(['name', 'sort_order']))->toBe(['name' => 'Honed Matte', 'sort_order' => 3])
        ->and(AuditLog::query()->where('auditable_id', $finish->id)->where('event', AuditEvent::Updated)->exists())->toBeTrue();
});

it('turns an item on or off where the list has a switch', function (): void {
    $type = FurnitureType::factory()->create(['is_active' => true]);
    $application = Application::query()->create(['name' => 'Floors']);

    $this->actingAs($this->admin)->patch(listUrl(CatalogList::FurnitureTypes, $type->id, 'status'), ['active' => false])->assertRedirect();
    expect($type->fresh()->is_active)->toBeFalse();

    $this->actingAs($this->admin)->patch(listUrl(CatalogList::Applications, $application->id, 'status'), ['active' => false])->assertNotFound();
});

it('archives an item nothing uses', function (): void {
    $edge = EdgeProfile::query()->create(['name' => 'Ogee', 'is_advisory' => false, 'is_active' => true, 'sort_order' => 0]);

    $this->actingAs($this->admin)->post(listUrl(CatalogList::EdgeProfiles, $edge->id, 'archive'))->assertSessionHasNoErrors();

    expect($edge->fresh()->trashed())->toBeTrue()
        ->and(AuditLog::query()->where('auditable_id', $edge->id)->where('event', AuditEvent::Archived)->exists())->toBeTrue();
    $this->actingAs($this->admin)->get(listUrl(CatalogList::EdgeProfiles))
        ->assertInertia(fn (Assert $page) => $page->has('items.data', 0));
});

it('refuses to archive an item still in use, saying why', function (Closure $make, CatalogList $list, string $why): void {
    $item = $make();

    $this->actingAs($this->admin)->post(listUrl($list, $item->id, 'archive'))
        ->assertSessionHasErrors(['archive' => $why]);
    expect($item->fresh()->trashed())->toBeFalse();
})->with([
    'stone type' => [fn () => tap(StoneType::factory()->create(['name' => 'Marble']), fn ($type) => Stone::factory()->for(Brand::factory())->create(['stone_type_id' => $type->id])), CatalogList::StoneTypes, 'Marble can\'t be archived. Used by 1 stone as their type. Give them another type first.'],
    'size' => [function () {
        $size = Size::factory()->create(['width' => '60', 'length' => '60']);
        StoneVariant::query()->create(['stone_id' => Stone::factory()->for(Brand::factory())->create()->id, 'size_id' => $size->id, 'thickness_id' => Thickness::factory()->create()->id, 'sort_order' => 0]);

        return $size;
    }, CatalogList::Sizes, '60 x 60 cm can\'t be archived. Used by 1 stone. Take it off their sizes first.'],
    'finish' => [function () {
        $finish = Finish::query()->create(['name' => 'Honed', 'is_advisory' => false, 'is_active' => true, 'sort_order' => 0]);
        StoneType::factory()->create()->finishes()->attach($finish, ['sort_order' => 0]);

        return $finish;
    }, CatalogList::Finishes, 'Honed can\'t be archived. Used by 1 stone type. Take it off their finishes first.'],
    'edge profile' => [function () {
        $edge = EdgeProfile::query()->create(['name' => 'Ogee', 'is_advisory' => false, 'is_active' => true, 'sort_order' => 0]);
        StoneType::factory()->create()->edgeProfiles()->attach($edge, ['sort_order' => 0]);

        return $edge;
    }, CatalogList::EdgeProfiles, 'Ogee can\'t be archived. Used by 1 stone type. Take it off their edge profiles first.'],
    'furniture type' => [fn () => tap(FurnitureType::factory()->create(['name' => 'Sofas']), fn ($type) => Furniture::factory()->for(Brand::factory())->create(['furniture_type_id' => $type->id])), CatalogList::FurnitureTypes, 'Sofas can\'t be archived. Used by 1 piece. Give them another type first.'],
]);

it('keeps an archived finish and edge profile on a past inquiry', function (): void {
    $finish = Finish::query()->create(['name' => 'Honed', 'is_advisory' => false, 'is_active' => true, 'sort_order' => 0]);
    $edge = EdgeProfile::query()->create(['name' => 'Ogee', 'is_advisory' => false, 'is_active' => true, 'sort_order' => 0]);
    $row = new InquiryStone;
    $row->forceFill(['finish_id' => $finish->id, 'edge_profile_id' => $edge->id]);
    $finish->delete();
    $edge->delete();

    expect($row->finish?->name)->toBe('Honed')
        ->and($row->edgeProfile?->name)->toBe('Ogee');
});

it('leaves an archived item off a product form', function (): void {
    $size = Size::factory()->create();
    $size->delete();

    $this->actingAs($this->admin)->get(route('products.index'))
        ->assertInertia(fn (Assert $page) => $page->where('options.sizes', []));
});

it('has the same permissions as products', function (): void {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(Permission::ProductsView->value);
    $application = Application::query()->create(['name' => 'Floors']);

    $this->actingAs($viewer)->get(listUrl(CatalogList::Applications))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canCreate', false)->where('items.data.0.actions', [])->where('items.data.0.canToggle', false));
    $this->actingAs($viewer)->post(listUrl(CatalogList::Applications), ['name' => 'Walls'])->assertForbidden();
    $this->actingAs($viewer)->put(listUrl(CatalogList::Applications, $application->id), ['name' => 'Walls'])->assertForbidden();
    $this->actingAs($viewer)->post(listUrl(CatalogList::Applications, $application->id, 'archive'))->assertForbidden();

    $this->actingAs(User::factory()->role(Role::Sales)->create())->get(listUrl(CatalogList::Applications))->assertForbidden();
});
