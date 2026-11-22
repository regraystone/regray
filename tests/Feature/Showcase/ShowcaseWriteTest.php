<?php

use App\Enums\AuditEvent;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Showcase;
use App\Models\ShowcaseImage;
use App\Models\Stone;
use App\Models\User;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
 * Add and Edit showcase: the form's rules, the stones, categories and gallery,
 * and the images, which follow the upload rule every upload in Regray does: a
 * random name, the uploader's own name, type and size kept on the row, the old
 * file deleted when it is replaced or taken off, and nothing left behind when
 * a write fails.
 */

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->role(Role::Admin)->create();
    $this->disk = Storage::fake(config('filesystems.public_disk'));
    $this->brand = Brand::factory()->create(['name' => 'Cucine Lube']);
    $this->marble = Stone::factory()->for($this->brand)->create(['slug' => 'calacatta', 'name' => 'Calacatta']);
    $this->agate = Stone::factory()->for($this->brand)->create(['slug' => 'blue-agate', 'name' => 'Blue Agate']);
    $this->kitchens = makeShowcaseCategory('Kitchens');
});

/** @return array<string, mixed> */
function showcaseForm(array $overrides = []): array
{
    return [
        'name' => 'Ginza One',
        'slug' => 'ginza-one',
        'location' => 'Makati City',
        'completed_year' => '2025',
        'overview' => "A kitchen in stone.\n\nAnd a bath.",
        'thumbnail' => UploadedFile::fake()->image('Ginza cover.jpg', 1600, 900),
        'gallery' => [
            ['file' => UploadedFile::fake()->image('island.png', 800, 600)],
            ['file' => UploadedFile::fake()->image('bath.webp', 800, 600)],
        ],
        'stones' => [test()->marble->id, test()->agate->id],
        'categories' => [test()->kitchens->id],
        'is_published' => '1',
        ...$overrides,
    ];
}

/** @return list<string> */
function showcaseFiles(): array
{
    return Storage::disk(config('filesystems.public_disk'))->allFiles('projects');
}

/** @return list<ShowcaseImage> */
function galleryOf(Showcase $showcase): array
{
    return array_values($showcase->images()->orderBy('sort_order')->get()->all());
}

describe('adding a showcase', function (): void {
    it('saves it, with its images under random names and their own names, types and sizes kept', function (): void {
        $this->actingAs($this->admin)
            ->post(route('dashboard.showcases.store'), showcaseForm())
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Ginza One is added.']);

        $showcase = Showcase::query()->where('slug', 'ginza-one')->sole();
        $photos = galleryOf($showcase);

        expect($showcase->name)->toBe('Ginza One')
            ->and($showcase->location)->toBe('Makati City')
            ->and($showcase->completed_year)->toBe(2025)
            ->and($showcase->overview)->toBe("A kitchen in stone.\n\nAnd a bath.")
            ->and($showcase->getAttributes())->not->toHaveKey('brand_id')
            ->and($showcase->is_published)->toBeTrue()
            ->and($showcase->published_at)->not->toBeNull()
            ->and($showcase->stones()->pluck('stones.id')->sort()->values()->all())->toBe(collect([$this->marble->id, $this->agate->id])->sort()->values()->all())
            ->and($showcase->categories()->pluck('showcase_categories.id')->all())->toBe([$this->kitchens->id])
            ->and($showcase->thumbnail_path)->toMatch('#^projects/[0-9a-f-]{36}\.jpg$#')
            ->and($showcase->thumbnail_original_name)->toBe('Ginza cover.jpg')
            ->and($showcase->thumbnail_mime_type)->toBe('image/jpeg')
            ->and($showcase->thumbnail_size_bytes)->toBeGreaterThan(0)
            ->and($photos)->toHaveCount(2)
            ->and($photos[0]->path)->toMatch('#^projects/[0-9a-f-]{36}\.png$#')
            ->and($photos[0]->original_name)->toBe('island.png')
            ->and($photos[0]->mime_type)->toBe('image/png')
            ->and($photos[0]->label())->toBe('island')
            ->and($photos[0]->sort_order)->toBe(0)
            ->and($photos[1]->original_name)->toBe('bath.webp')
            ->and($photos[1]->label())->toBe('bath')
            ->and($photos[1]->sort_order)->toBe(1)
            ->and(showcaseFiles())->toEqualCanonicalizing([$showcase->thumbnail_path, $photos[0]->path, $photos[1]->path]);

        $audit = AuditLog::query()->where('auditable_type', $showcase->getMorphClass())->where('auditable_id', $showcase->id)->sole();

        expect($audit->event)->toBe(AuditEvent::Created)
            ->and($audit->user_id)->toBe($this->admin->id)
            ->and($audit->new_values['slug'])->toBe('ginza-one')
            ->and($audit->new_values['gallery'])->toBe([$photos[0]->path, $photos[1]->path]);
    });

    it('saves a draft with no categories or gallery, and no publish date', function (): void {
        $this->actingAs($this->admin)
            ->post(route('dashboard.showcases.store'), showcaseForm([
                'gallery' => [],
                'categories' => [],
                'is_published' => '0',
            ]))
            ->assertSessionHasNoErrors();

        $showcase = Showcase::query()->sole();

        expect($showcase->is_published)->toBeFalse()
            ->and($showcase->published_at)->toBeNull()
            ->and($showcase->categories()->count())->toBe(0)
            ->and($showcase->images()->count())->toBe(0)
            ->and(showcaseFiles())->toBe([$showcase->thumbnail_path]);
    });

    // A dataset's closure runs before the test body, so it makes what it needs itself.
    it('refuses what the form would not send, and stores nothing', function (array $overrides, string $field, string $message): void {
        makeShowcase(['slug' => 'taken']);
        makeShowcase(['slug' => 'gone'])->delete();

        $this->actingAs($this->admin)
            ->post(route('dashboard.showcases.store'), showcaseForm($overrides))
            ->assertSessionHasErrors([$field => $message]);

        expect(Showcase::query()->where('name', 'Ginza One')->exists())->toBeFalse()
            ->and(showcaseFiles())->toBe([]);
    })->with([
        'no name' => [['name' => ''], 'name', 'Enter the showcase\'s name.'],
        'no thumbnail' => [['thumbnail' => null], 'thumbnail', 'Add the showcase\'s thumbnail.'],
        'a slug in capitals and spaces' => [['slug' => 'Ginza One'], 'slug', 'Use lower-case letters, numbers and single hyphens, such as ginza-one.'],
        'a reserved slug' => [['slug' => 'dashboard'], 'slug', 'That slug is kept for one of Regray\'s own pages. Choose another.'],
        'a slug in use' => [['slug' => 'taken'], 'slug', 'Another showcase already uses that slug.'],
        'an archived showcase\'s slug' => [['slug' => 'gone'], 'slug', 'That slug belonged to an archived showcase and can\'t be used again.'],
        'a stone\'s slug' => [['slug' => 'calacatta'], 'slug', 'A stone already uses that slug, and its link would open the stone. Choose another.'],
        'no location' => [['location' => ' '], 'location', 'Enter where the showcase is.'],
        'a year too early' => [['completed_year' => '1899'], 'completed_year', 'Enter a year from 1900 to 2100.'],
        'a year that is not one' => [['completed_year' => 'soon'], 'completed_year', 'Enter a year, such as 2026.'],
        'no overview' => [['overview' => ''], 'overview', 'Write a short overview of the showcase.'],
        'an overview too long' => [['overview' => str_repeat('a', 10001)], 'overview', 'Keep the overview to 10000 characters.'],
        'no stones' => [['stones' => []], 'stones', 'Choose at least one stone used in the showcase.'],
        'a stone out of the catalogue' => [fn () => ['stones' => [Stone::factory()->for(test()->brand)->create(['is_active' => false])->id]], 'stones.0', 'That stone is no longer in the catalogue. Remove it.'],
        'a category not offered' => [fn () => ['categories' => [makeShowcaseCategory('Retired', ['is_active' => false])->id]], 'categories.0', 'That category is no longer offered. Remove it.'],
        'a thumbnail that is not an image' => [['thumbnail' => UploadedFile::fake()->create('cover.pdf', 10, 'application/pdf')], 'thumbnail', 'The thumbnail has to be a PNG, JPG or WebP.'],
        'an SVG thumbnail' => [['thumbnail' => UploadedFile::fake()->createWithContent('cover.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>')], 'thumbnail', 'The thumbnail has to be a PNG, JPG or WebP.'],
        'a thumbnail too large' => [['thumbnail' => UploadedFile::fake()->image('cover.jpg')->size(6000)], 'thumbnail', 'The thumbnail can be 5120 KB at most.'],
        'a photo too large' => [['gallery' => [['file' => UploadedFile::fake()->image('big.jpg')->size(6000)]]], 'gallery.0.file', 'A photo can be 5120 KB at most.'],
        'a photo that is not an image' => [['gallery' => [['file' => UploadedFile::fake()->create('plan.pdf', 10, 'application/pdf')]]], 'gallery.0.file', 'A photo has to be a PNG, JPG or WebP.'],
        'a row with no photo' => [['gallery' => [[]]], 'gallery.0.file', 'Add a photo, or remove the row.'],
        'a saved photo of another showcase' => [fn () => ['gallery' => [['id' => makeShowcase()->images()->create(['path' => 'projects/x.jpg', 'sort_order' => 0])->id]]], 'gallery.0.id', 'That photo is no longer in the gallery. Reload the page and try again.'],
        'too many photos' => [fn () => ['gallery' => array_map(fn (int $n) => ['file' => UploadedFile::fake()->image("p{$n}.jpg", 10, 10)], range(1, 21))], 'gallery', 'A gallery can have 20 photos at most.'],
    ]);

    it('leaves no file behind when the write fails after storing it', function (): void {
        $this->app->bind(AuditLogRepositoryInterface::class, fn () => new class implements AuditLogRepositoryInterface
        {
            public function record(?int $userId, AuditEvent $event, Model $auditable, ?array $oldValues, ?array $newValues, ?string $ipAddress, ?string $userAgent): AuditLog
            {
                throw new RuntimeException('The audit log is down.');
            }
        });

        $this->withoutExceptionHandling();

        expect(fn () => $this->actingAs($this->admin)->post(route('dashboard.showcases.store'), showcaseForm()))
            ->toThrow(RuntimeException::class, 'The audit log is down.');

        expect(Showcase::query()->count())->toBe(0)
            ->and(ShowcaseImage::query()->count())->toBe(0)
            ->and(showcaseFiles())->toBe([]);
    });
});

describe('editing a showcase', function (): void {
    beforeEach(function (): void {
        $this->actingAs($this->admin)->post(route('dashboard.showcases.store'), showcaseForm())->assertSessionHasNoErrors();

        $this->showcase = Showcase::query()->sole();
        $this->oldThumbnail = $this->showcase->thumbnail_path;
        [$this->island, $this->bath] = galleryOf($this->showcase);
        // As the page sends it: a POST that says it is a PUT, since it may carry files.
        $this->edit = fn (array $overrides = []) => $this->actingAs($this->admin)->post(
            route('dashboard.showcases.update', $this->showcase->id),
            [...showcaseForm([
                'thumbnail' => null,
                'gallery' => [
                    ['id' => $this->island->id],
                    ['id' => $this->bath->id],
                ],
                ...$overrides,
            ]), '_method' => 'put'],
        );
    });

    it('changes its fields, stones and categories, and keeps its images when none are sent', function (): void {
        $baths = makeShowcaseCategory('Bathrooms');

        ($this->edit)(['name' => 'Ginza Two', 'slug' => 'ginza-two', 'location' => 'Taguig City', 'stones' => [$this->agate->id], 'categories' => [$baths->id]])
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Ginza Two is updated.']);

        $showcase = $this->showcase->refresh();

        expect($showcase->name)->toBe('Ginza Two')
            ->and($showcase->slug)->toBe('ginza-two')
            ->and($showcase->location)->toBe('Taguig City')
            ->and($showcase->stones()->pluck('stones.id')->all())->toBe([$this->agate->id])
            ->and($showcase->categories()->pluck('showcase_categories.id')->all())->toBe([$baths->id])
            ->and($showcase->thumbnail_path)->toBe($this->oldThumbnail)
            ->and(array_map(fn (ShowcaseImage $image) => $image->id, galleryOf($showcase)))->toBe([$this->island->id, $this->bath->id])
            ->and(showcaseFiles())->toEqualCanonicalizing([$this->oldThumbnail, $this->island->path, $this->bath->path])
            ->and(AuditLog::query()->where('event', AuditEvent::Updated)->sole()->old_values['slug'])->toBe('ginza-one');
    });

    it('keeps its own slug', function (): void {
        ($this->edit)()->assertSessionHasNoErrors();

        expect($this->showcase->refresh()->slug)->toBe('ginza-one');
    });

    it('replaces the thumbnail, deleting the old file', function (): void {
        ($this->edit)(['thumbnail' => UploadedFile::fake()->image('new cover.png', 1600, 900)])->assertSessionHasNoErrors();

        $showcase = $this->showcase->refresh();

        expect($showcase->thumbnail_path)->not->toBe($this->oldThumbnail)
            ->and($showcase->thumbnail_path)->toEndWith('.png')
            ->and($showcase->thumbnail_original_name)->toBe('new cover.png')
            ->and($showcase->thumbnail_mime_type)->toBe('image/png');

        $this->disk->assertMissing($this->oldThumbnail);
        $this->disk->assertExists($showcase->thumbnail_path);
    });

    it('reorders, adds to and takes from the gallery, deleting the files taken off', function (): void {
        ($this->edit)(['gallery' => [
            ['file' => UploadedFile::fake()->image('terrace.jpg', 800, 600)],
            ['id' => $this->bath->id],
        ]])->assertSessionHasNoErrors();

        $photos = galleryOf($this->showcase);

        expect($photos)->toHaveCount(2)
            ->and($photos[0]->original_name)->toBe('terrace.jpg')
            ->and($photos[0]->label())->toBe('terrace')
            ->and($photos[0]->sort_order)->toBe(0)
            ->and($photos[1]->id)->toBe($this->bath->id)
            ->and($photos[1]->sort_order)->toBe(1)
            ->and(ShowcaseImage::query()->whereKey($this->island->id)->exists())->toBeFalse()
            ->and(showcaseFiles())->toEqualCanonicalizing([$this->oldThumbnail, $photos[0]->path, $this->bath->path]);

        $this->disk->assertMissing($this->island->path);
    });

    it('swaps two photos without the order colliding', function (): void {
        ($this->edit)(['gallery' => [
            ['id' => $this->bath->id],
            ['id' => $this->island->id],
        ]])->assertSessionHasNoErrors();

        expect(array_map(fn (ShowcaseImage $image) => [$image->id, $image->sort_order], galleryOf($this->showcase)))
            ->toBe([[$this->bath->id, 0], [$this->island->id, 1]]);
    });

    it('empties the gallery when no photos are sent', function (): void {
        ($this->edit)(['gallery' => []])->assertSessionHasNoErrors();

        expect($this->showcase->images()->count())->toBe(0)
            ->and(showcaseFiles())->toBe([$this->oldThumbnail]);
    });

    it('keeps the first publish date when it goes back to a draft and out again', function (): void {
        $first = $this->showcase->published_at;

        $this->travel(1)->day();
        ($this->edit)(['is_published' => '0'])->assertSessionHasNoErrors();
        expect($this->showcase->refresh()->is_published)->toBeFalse()
            ->and($this->showcase->published_at?->equalTo($first))->toBeTrue();

        $this->travel(1)->day();
        ($this->edit)(['is_published' => '1'])->assertSessionHasNoErrors();
        expect($this->showcase->refresh()->published_at?->equalTo($first))->toBeTrue();
    });

    it('sets the publish date the first time a draft goes out', function (): void {
        $this->showcase->forceFill(['is_published' => false, 'published_at' => null])->save();

        ($this->edit)(['is_published' => '1'])->assertSessionHasNoErrors();

        expect($this->showcase->refresh()->published_at)->not->toBeNull();
    });

    it('keeps every file when the edit is refused', function (): void {
        ($this->edit)([
            'name' => '',
            'thumbnail' => UploadedFile::fake()->image('new.jpg'),
            'gallery' => [['file' => UploadedFile::fake()->image('more.jpg')]],
        ])->assertSessionHasErrors('name');

        expect(showcaseFiles())->toEqualCanonicalizing([$this->oldThumbnail, $this->island->path, $this->bath->path])
            ->and($this->showcase->refresh()->thumbnail_path)->toBe($this->oldThumbnail)
            ->and($this->showcase->images()->count())->toBe(2);
    });

    it('finds nothing to edit by slug, or once archived', function (): void {
        $this->actingAs($this->admin)
            ->post(route('dashboard.showcases.update', 'ginza-one'), ['_method' => 'put'])
            ->assertNotFound();

        $this->showcase->delete();

        ($this->edit)()->assertNotFound();
    });
});

it('lets only those allowed add or edit', function (): void {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(Permission::ShowcasesView->value);
    $showcase = makeShowcase();

    $this->actingAs($viewer)->post(route('dashboard.showcases.store'), showcaseForm())->assertForbidden();
    $this->actingAs($viewer)->post(route('dashboard.showcases.update', $showcase->id), [...showcaseForm(), '_method' => 'put'])->assertForbidden();

    $this->actingAs(User::factory()->role(Role::Owner)->create())
        ->post(route('dashboard.showcases.store'), showcaseForm())
        ->assertForbidden();

    expect(Showcase::query()->count())->toBe(1)
        ->and(showcaseFiles())->toBe([]);
});
