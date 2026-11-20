<?php

use App\Enums\AuditEvent;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\BrandFact;
use App\Models\BrandMake;
use App\Models\BrandSocial;
use App\Models\User;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
 * Add and Edit brand: the form's rules, and the images, which follow the
 * upload rule every upload in Regray does: a random name, the uploader's own
 * name, type and size kept on the row, the old file deleted when it is
 * replaced or taken off, and nothing left behind when a write fails.
 */

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->role(Role::Admin)->create();
    $this->disk = Storage::fake(config('filesystems.public_disk'));
});

/** @return array<string, mixed> */
function brandForm(array $overrides = []): array
{
    return [
        'name' => 'Cucine Lube',
        'slug' => 'cucine-lube',
        'tagline' => 'Italian kitchens from Treia.',
        'description' => 'Italy\'s largest kitchen maker.',
        'website_url' => 'www.cucinelube.it',
        'logo' => UploadedFile::fake()->image('Lube Logo.png', 300, 120),
        'facts' => [['label' => 'Made in', 'value' => 'Treia, Italy'], ['label' => 'Founded', 'value' => '1967']],
        'makes' => ['Kitchens', 'Wardrobes'],
        'socials' => [
            ['platform' => 'instagram', 'url' => 'instagram.com/cucinelube'],
            ['platform' => 'x', 'url' => 'https://twitter.com/cucinelube'],
        ],
        'is_active' => '1',
        'sort_order' => '3',
        ...$overrides,
    ];
}

/** @return list<string> */
function brandFiles(): array
{
    return Storage::disk(config('filesystems.public_disk'))->allFiles('brands');
}

describe('adding a brand', function (): void {
    it('saves it, with the logo under a random name and its own name, type and size kept', function (): void {
        $this->actingAs($this->admin)
            ->post(route('dashboard.brands.store'), brandForm())
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Cucine Lube is added.']);

        $brand = Brand::query()->where('slug', 'cucine-lube')->sole();

        expect($brand->name)->toBe('Cucine Lube')
            ->and($brand->website_url)->toBe('https://www.cucinelube.it')
            ->and($brand->load(Brand::DETAILS)->details()['facts'])->toBe([['label' => 'Made in', 'value' => 'Treia, Italy'], ['label' => 'Founded', 'value' => '1967']])
            ->and($brand->details()['makes'])->toBe(['Kitchens', 'Wardrobes'])
            ->and($brand->details()['socials'])->toBe([
                ['platform' => 'instagram', 'url' => 'https://instagram.com/cucinelube'],
                ['platform' => 'x', 'url' => 'https://twitter.com/cucinelube'],
            ])
            ->and($brand->sort_order)->toBe(3)
            ->and($brand->is_active)->toBeTrue()
            ->and($brand->logo_path)->toMatch('#^brands/[0-9a-f-]{36}\.png$#')
            ->and($brand->logo_original_name)->toBe('Lube Logo.png')
            ->and($brand->logo_mime_type)->toBe('image/png')
            ->and($brand->logo_size_bytes)->toBeGreaterThan(0)
            ->and($brand->thumbnail_path)->toBeNull()
            ->and(brandFiles())->toBe([$brand->logo_path]);

        $audit = AuditLog::query()->where('auditable_type', $brand->getMorphClass())->where('auditable_id', $brand->id)->sole();

        expect($audit->event)->toBe(AuditEvent::Created)
            ->and($audit->user_id)->toBe($this->admin->id)
            ->and($audit->new_values['slug'])->toBe('cucine-lube');
    });

    it('stores a thumbnail beside the logo, and takes an SVG, named by its bytes', function (): void {
        $svg = UploadedFile::fake()->createWithContent('mark.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><rect width="10" height="10"/></svg>');

        $this->actingAs($this->admin)
            ->post(route('dashboard.brands.store'), brandForm([
                'logo' => $svg,
                'thumbnail' => UploadedFile::fake()->image('cover photo.jpg', 1600, 900),
            ]))
            ->assertSessionHasNoErrors();

        $brand = Brand::query()->sole();

        expect($brand->logo_path)->toEndWith('.svg')
            ->and($brand->logo_mime_type)->toBe('image/svg+xml')
            ->and($brand->thumbnail_path)->toMatch('#^brands/[0-9a-f-]{36}\.jpg$#')
            ->and($brand->thumbnail_original_name)->toBe('cover photo.jpg')
            ->and(brandFiles())->toEqualCanonicalizing([$brand->logo_path, $brand->thumbnail_path]);
    });

    it('refuses what the form would not send, and stores nothing', function (array $overrides, string $field, string $message): void {
        Brand::factory()->create(['slug' => 'taken']);
        Brand::factory()->create(['slug' => 'gone'])->forceFill(['archived_at' => now()])->save();

        $this->actingAs($this->admin)
            ->post(route('dashboard.brands.store'), brandForm($overrides))
            ->assertSessionHasErrors([$field => $message]);

        expect(Brand::query()->where('name', 'Cucine Lube')->exists())->toBeFalse()
            ->and(brandFiles())->toBe([]);
    })->with([
        'no name' => [['name' => ''], 'name', 'Enter the brand\'s name.'],
        'no logo' => [['logo' => null], 'logo', 'Add the brand\'s logo.'],
        'a slug in capitals and spaces' => [['slug' => 'Cucine Lube'], 'slug', 'Use lower-case letters, numbers and single hyphens, such as cucine-lube.'],
        'a slug with a double hyphen' => [['slug' => 'cucine--lube'], 'slug', 'Use lower-case letters, numbers and single hyphens, such as cucine-lube.'],
        'a reserved slug' => [['slug' => 'dashboard'], 'slug', 'That slug is kept for one of Regray\'s own pages. Choose another.'],
        'a slug in use' => [['slug' => 'taken'], 'slug', 'Another brand already uses that slug.'],
        'an archived brand\'s slug' => [['slug' => 'gone'], 'slug', 'That slug belonged to an archived brand and can\'t be used again.'],
        'a logo that is not an image' => [['logo' => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf')], 'logo', 'The logo has to be a PNG, JPG, WebP or SVG.'],
        'a logo too large' => [['logo' => UploadedFile::fake()->image('logo.png')->size(3000)], 'logo', 'The logo can be 2048 KB at most.'],
        'a website that is not one' => [['website_url' => 'not a site'], 'website_url', 'Enter a full web address, such as https://www.cucinelube.it.'],
        'seven facts' => [['facts' => array_fill(0, 7, ['label' => 'A', 'value' => 'B'])], 'facts', 'A brand can have 6 facts at most.'],
        'a fact without a value' => [['facts' => [['label' => 'Made in', 'value' => '']]], 'facts.0.value', 'Give this fact a value, or remove it.'],
        'a fact label too long' => [['facts' => [['label' => str_repeat('a', 41), 'value' => 'B']]], 'facts.0.label', 'A fact\'s label can be 40 characters at most.'],
        'nine tags' => [['makes' => ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i']], 'makes', 'A brand can list 8 things it makes at most.'],
        'a repeated tag' => [['makes' => ['Kitchens', 'kitchens']], 'makes.1', 'That tag is already listed.'],
        'a tag too long' => [['makes' => [str_repeat('a', 41)]], 'makes.0', 'Each tag can be 40 characters at most.'],
        'a negative order' => [['sort_order' => '-1'], 'sort_order', 'Enter 0 or more.'],
        'a network not offered' => [['socials' => [['platform' => 'linkedin', 'url' => 'https://linkedin.com/company/lube']]], 'socials.0.platform', 'Choose Facebook, Twitter, X, Instagram or TikTok.'],
        'two links for one network' => [['socials' => [['platform' => 'facebook', 'url' => 'facebook.com/a'], ['platform' => 'facebook', 'url' => 'facebook.com/b']]], 'socials.1.platform', 'That network already has a link.'],
        'a link with no address' => [['socials' => [['platform' => 'tiktok', 'url' => '']]], 'socials.0.url', 'Enter the link, or remove it.'],
        'a link on another site' => [['socials' => [['platform' => 'instagram', 'url' => 'https://www.facebook.com/lube']]], 'socials.0.url', 'That link isn\'t on Instagram. Use one on instagram.com.'],
        'a link on a look-alike site' => [['socials' => [['platform' => 'facebook', 'url' => 'https://facebook.com.evil.test/lube']]], 'socials.0.url', 'That link isn\'t on Facebook. Use one on facebook.com.'],
        'a link that is no address' => [['socials' => [['platform' => 'x', 'url' => 'javascript:alert(1)']]], 'socials.0.url', 'Enter a full web address, such as https://www.instagram.com/regraystoneph.'],
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

        expect(fn () => $this->actingAs($this->admin)->post(route('dashboard.brands.store'), brandForm([
            'thumbnail' => UploadedFile::fake()->image('cover.jpg', 1600, 900),
        ])))->toThrow(RuntimeException::class, 'The audit log is down.');

        expect(Brand::query()->count())->toBe(0)
            ->and(brandFiles())->toBe([]);
    });
});

describe('editing a brand', function (): void {
    beforeEach(function (): void {
        $this->actingAs($this->admin)->post(route('dashboard.brands.store'), brandForm([
            'thumbnail' => UploadedFile::fake()->image('cover.jpg', 1600, 900),
        ]))->assertSessionHasNoErrors();

        $this->brand = Brand::query()->sole();
        $this->oldLogo = $this->brand->logo_path;
        $this->oldThumbnail = $this->brand->thumbnail_path;
        // As the page sends it: a POST that says it is a PUT, since it may carry files.
        $this->edit = fn (array $overrides = []) => $this->actingAs($this->admin)->post(
            route('dashboard.brands.update', $this->brand->id),
            [...brandForm(['logo' => null, ...$overrides]), '_method' => 'put'],
        );
    });

    it('changes its fields and keeps its images when none are sent', function (): void {
        ($this->edit)(['name' => 'Lube', 'slug' => 'lube', 'facts' => [], 'makes' => [], 'socials' => [], 'is_active' => '0'])
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Lube is updated.']);

        $brand = $this->brand->refresh();

        expect($brand->name)->toBe('Lube')
            ->and($brand->slug)->toBe('lube')
            ->and($brand->load(Brand::DETAILS)->details())->toBe(['facts' => [], 'makes' => [], 'socials' => []])
            ->and(BrandFact::query()->count() + BrandMake::query()->count() + BrandSocial::query()->count())->toBe(0)
            ->and($brand->is_active)->toBeFalse()
            ->and($brand->logo_path)->toBe($this->oldLogo)
            ->and($brand->thumbnail_path)->toBe($this->oldThumbnail)
            ->and(brandFiles())->toEqualCanonicalizing([$this->oldLogo, $this->oldThumbnail])
            ->and(AuditLog::query()->where('event', AuditEvent::Updated)->sole()->old_values['slug'])->toBe('cucine-lube');
    });

    it('keeps its own slug, but not another brand\'s', function (): void {
        Brand::factory()->create(['slug' => 'ascale']);

        ($this->edit)()->assertSessionHasNoErrors();
        ($this->edit)(['slug' => 'ascale'])->assertSessionHasErrors(['slug' => 'Another brand already uses that slug.']);
    });

    it('deletes the old logo and keeps only the new one when it is replaced', function (): void {
        ($this->edit)(['logo' => UploadedFile::fake()->image('New Logo.webp', 300, 120)])->assertSessionHasNoErrors();

        $brand = $this->brand->refresh();

        expect($brand->logo_path)->not->toBe($this->oldLogo)
            ->and($brand->logo_path)->toEndWith('.webp')
            ->and($brand->logo_original_name)->toBe('New Logo.webp')
            ->and($brand->logo_mime_type)->toBe('image/webp')
            ->and(brandFiles())->toEqualCanonicalizing([$brand->logo_path, $this->oldThumbnail]);

        $this->disk->assertMissing($this->oldLogo);
    });

    it('deletes the old thumbnail when it is replaced, and the file and path when it is taken off', function (): void {
        ($this->edit)(['thumbnail' => UploadedFile::fake()->image('new cover.png', 1600, 900)])->assertSessionHasNoErrors();
        $replaced = $this->brand->refresh()->thumbnail_path;

        expect($replaced)->not->toBe($this->oldThumbnail)
            ->and(brandFiles())->toEqualCanonicalizing([$this->oldLogo, $replaced]);

        ($this->edit)(['remove_thumbnail' => '1'])->assertSessionHasNoErrors();
        $brand = $this->brand->refresh();

        expect($brand->thumbnail_path)->toBeNull()
            ->and($brand->thumbnail_original_name)->toBeNull()
            ->and($brand->thumbnail_size_bytes)->toBeNull()
            ->and(brandFiles())->toBe([$this->oldLogo]);
    });

    it('deletes nothing when the new image is refused', function (): void {
        ($this->edit)(['logo' => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors('logo');

        expect($this->brand->refresh()->logo_path)->toBe($this->oldLogo)
            ->and(brandFiles())->toEqualCanonicalizing([$this->oldLogo, $this->oldThumbnail]);
    });

    it('deletes the new image again when the write fails after storing it', function (): void {
        $this->app->bind(AuditLogRepositoryInterface::class, fn () => new class implements AuditLogRepositoryInterface
        {
            public function record(?int $userId, AuditEvent $event, Model $auditable, ?array $oldValues, ?array $newValues, ?string $ipAddress, ?string $userAgent): AuditLog
            {
                throw new RuntimeException('The audit log is down.');
            }
        });

        $this->withoutExceptionHandling();

        expect(fn () => ($this->edit)(['name' => 'Lube', 'thumbnail' => UploadedFile::fake()->image('new.jpg', 1600, 900)]))
            ->toThrow(RuntimeException::class);

        // The row is as it was; the new file is gone, and so is the old one, deleted first.
        expect($this->brand->refresh()->name)->toBe('Cucine Lube')
            ->and(brandFiles())->toBe([$this->oldLogo]);
    });

    it('does not find an archived brand, or anything but an id', function (): void {
        $this->brand->forceFill(['archived_at' => now()])->save();

        ($this->edit)()->assertNotFound();
        $this->actingAs($this->admin)->post(route('dashboard.brands.update', 'cucine-lube'), ['_method' => 'put'])->assertNotFound();
    });
});

it('is the admin\'s alone', function (Role $role): void {
    $brand = Brand::factory()->create();
    $user = User::factory()->role($role)->create();

    $this->actingAs($user)->post(route('dashboard.brands.store'), brandForm())->assertForbidden();
    $this->actingAs($user)->post(route('dashboard.brands.update', $brand->id), [...brandForm(), '_method' => 'put'])->assertForbidden();
    $this->actingAs($user)->post(route('dashboard.brands.archive', $brand->id))->assertForbidden();

    expect(brandFiles())->toBe([])
        ->and($brand->refresh()->archived_at)->toBeNull();
})->with([Role::Owner, Role::Marketing, Role::Sales, Role::Client]);
