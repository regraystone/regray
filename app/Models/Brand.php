<?php

namespace App\Models;

use Database\Factories\BrandFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A brand on the home page, with its own page and collection at `/brands/{slug}`.
 *
 * `logo_path` and `thumbnail_path` are paths on the public disk
 * (`config('filesystems.public_disk')`), resolved to URLs through
 * ProjectCardResource::resolveThumbnailUrl. An uploaded image is stored under
 * a random name (StoredFileName); its own name, type and size are kept beside
 * the path, to show to people only.
 *
 * A hidden brand (`is_active` false) keeps its products but has no page. An
 * archived one (`archived_at`) is gone everywhere a brand is read, and its
 * stones and furniture with it (Stone::active(), Furniture::active()).
 *
 * Its facts, what it makes and its social links are rows of their own
 * (BrandFact, BrandMake, BrandSocial), each in its page's order.
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string $logo_path
 * @property string|null $thumbnail_path
 * @property string|null $tagline
 * @property string|null $description
 * @property string|null $website_url
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon|null $archived_at
 * @property string|null $logo_original_name
 * @property string|null $logo_mime_type
 * @property int|null $logo_size_bytes
 * @property string|null $thumbnail_original_name
 * @property string|null $thumbnail_mime_type
 * @property int|null $thumbnail_size_bytes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, Stone> $stones
 * @property-read Collection<int, Furniture> $furniture
 * @property-read Collection<int, BrandFact> $facts
 * @property-read Collection<int, BrandMake> $makes
 * @property-read Collection<int, BrandSocial> $socials
 */
#[Fillable(['slug', 'name', 'logo_path', 'thumbnail_path', 'tagline', 'description', 'website_url', 'is_active', 'sort_order'])]
class Brand extends Model
{
    /** @use HasFactory<BrandFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'archived_at' => 'datetime',
            'logo_size_bytes' => 'integer',
            'thumbnail_size_bytes' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Only an active brand has a page, on either the guest or the signed-in route.
     *
     * @param  mixed  $value
     * @param  string|null  $field
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        return self::query()->active()->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    /**
     * A product resolves only under its own brand and only while it is active.
     * The relation already leaves soft-deleted stones out.
     *
     * @param  string  $childType
     * @param  mixed  $value
     * @param  string|null  $field
     * @return EloquentModel|null
     */
    public function resolveChildRouteBinding($childType, $value, $field)
    {
        return $this->resolveChildRouteBindingQuery($childType, $value, $field)->where('is_active', true)->first();
    }

    /**
     * The brands shown on Regray: visible, and not archived.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where($query->qualifyColumn('is_active'), true)
            ->whereNull($query->qualifyColumn('archived_at'));
    }

    /**
     * The brands still on the admin's Brands page: hidden or not, but not
     * archived.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function notArchived(Builder $query): void
    {
        $query->whereNull($query->qualifyColumn('archived_at'));
    }

    /** The relations details() reads, to load with the brand. */
    public const DETAILS = ['facts', 'makes', 'socials'];

    /**
     * Its facts, what it makes and its social links as plain lists, in its
     * page's order: what the pages and the audit log read. Expects them loaded
     * (DETAILS).
     *
     * @return array{facts: list<array{label: string, value: string}>, makes: list<string>, socials: list<array{platform: string, url: string}>}
     */
    public function details(): array
    {
        return [
            'facts' => array_values($this->facts->map(fn (BrandFact $fact): array => ['label' => $fact->label, 'value' => $fact->value])->all()),
            'makes' => array_values($this->makes->map(fn (BrandMake $make): string => $make->name)->all()),
            'socials' => array_values($this->socials->map(fn (BrandSocial $social): array => ['platform' => $social->platform->value, 'url' => $social->url])->all()),
        ];
    }

    /**
     * Swap its facts, what it makes and its social links for these, numbered
     * in the order given, and load the new ones. Run it in the transaction
     * that saves the brand.
     *
     * @param  array{facts: list<array{label: string, value: string}>, makes: list<string>, socials: list<array{platform: string, url: string}>}  $details
     */
    public function replaceDetails(array $details): void
    {
        foreach (self::DETAILS as $relation) {
            $this->{$relation}()->delete();
        }

        $numbered = fn (array $rows, \Closure $row): array => array_map(
            fn (int $order, mixed $item): array => [...$row($item), 'sort_order' => $order],
            array_keys($rows), $rows,
        );

        $this->facts()->createMany($numbered($details['facts'], fn (array $fact): array => ['label' => $fact['label'], 'value' => $fact['value']]));
        $this->makes()->createMany($numbered($details['makes'], fn (string $name): array => ['name' => $name]));
        $this->socials()->createMany($numbered($details['socials'], fn (array $social): array => ['platform' => $social['platform'], 'url' => $social['url']]));

        $this->load(self::DETAILS);
    }

    /** @return HasMany<BrandFact, $this> */
    public function facts(): HasMany
    {
        return $this->hasMany(BrandFact::class)->orderBy('sort_order');
    }

    /** @return HasMany<BrandMake, $this> */
    public function makes(): HasMany
    {
        return $this->hasMany(BrandMake::class)->orderBy('sort_order');
    }

    /** @return HasMany<BrandSocial, $this> */
    public function socials(): HasMany
    {
        return $this->hasMany(BrandSocial::class)->orderBy('sort_order');
    }

    /** @return HasMany<Stone, $this> */
    public function stones(): HasMany
    {
        return $this->hasMany(Stone::class);
    }

    /** @return HasMany<Furniture, $this> */
    public function furniture(): HasMany
    {
        return $this->hasMany(Furniture::class);
    }
}
