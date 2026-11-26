<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * One choice of the library the list questions pick from (the Choices page
 * under Services): what the client sees (its name, a description shown once
 * it is picked, a picture), its kind (an ordinary choice, a custom one that
 * asks the client to describe theirs, "Not sure yet, advise me", or "Same as
 * stone"), how a table shape listing it asks the size, and the services it is
 * offered for (none: every service that asks a question listing it).
 *
 * A question lists it through one of its own options (`detail_field_options`,
 * `choice_id`), which keeps the question's order and the answers that chose
 * it. Saving a choice writes what the client sees onto every option listing
 * it (mirror()), so the Inquire page and sent answers read the options alone.
 * Archiving (`archived_at`) takes it off every question; its options stay for
 * the answers that chose them.
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string|null $description
 * @property string|null $thumbnail_path
 * @property bool $is_custom
 * @property bool $is_advisory
 * @property bool $links_to_stone
 * @property string|null $size_kind
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon|null $archived_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, Service> $services
 * @property-read Collection<int, DetailFieldOption> $options
 * @property-read Collection<int, DetailField> $questions
 */
#[Fillable(['slug', 'name', 'description', 'thumbnail_path', 'is_custom', 'is_advisory', 'links_to_stone', 'size_kind', 'is_active', 'sort_order'])]
class Choice extends Model
{
    /** choices.slug's length. */
    public const SLUG_MAX = 100;

    protected static function booted(): void
    {
        static::saved(fn (Choice $choice) => $choice->mirror());
    }

    protected function casts(): array
    {
        return [
            'is_custom' => 'boolean',
            'is_advisory' => 'boolean',
            'links_to_stone' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'archived_at' => 'datetime',
        ];
    }

    /** @param  Builder<static>  $query */
    #[Scope]
    protected function notArchived(Builder $query): void
    {
        $query->whereNull($query->qualifyColumn('archived_at'));
    }

    /** @return BelongsToMany<Service, $this> The services it is offered for; none is every one. */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class);
    }

    /** @return HasMany<DetailFieldOption, $this> Its listings on the questions, taken off ones included. */
    public function options(): HasMany
    {
        return $this->hasMany(DetailFieldOption::class);
    }

    /** @return BelongsToMany<DetailField, $this> The questions that list it now. */
    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(DetailField::class, 'detail_field_options')
            ->wherePivotNull('archived_at')
            ->whereNull('detail_fields.archived_at')
            ->orderBy('detail_fields.sort_order');
    }

    /** Its kind, as the Choices page names it: choice, custom, advisory or stone. */
    public function kind(): string
    {
        return match (true) {
            $this->is_custom => 'custom',
            $this->is_advisory => 'advisory',
            $this->links_to_stone => 'stone',
            default => 'choice',
        };
    }

    /**
     * Write what the client sees onto every option listing it: its name,
     * description, picture, kind, whether it is offered, and its services.
     * How a table shape asks the size goes onto a Table shape option alone.
     * Archived, it is taken off every question.
     */
    public function mirror(): void
    {
        $options = fn (): Builder => DetailFieldOption::query()->where('choice_id', $this->id);
        $shape = DetailField::query()->where('slug', DetailField::TABLE_SHAPE)->value('id');

        $options()->update([
            'name' => $this->name,
            'description' => $this->description,
            'thumbnail_path' => $this->thumbnail_path,
            'is_custom' => $this->is_custom,
            'is_advisory' => $this->is_advisory,
            'links_to_stone' => $this->links_to_stone,
            'is_active' => $this->is_active && $this->archived_at === null,
            'size_kind' => null,
        ]);

        if ($shape !== null) {
            $options()->where('detail_field_id', $shape)->update(['size_kind' => $this->size_kind]);
        }

        if ($this->archived_at !== null) {
            $options()->whereNull('archived_at')->update(['archived_at' => now()]);
        }
    }

    /**
     * Offer it for these services, and so every option listing it.
     *
     * @param  list<int>  $serviceIds
     */
    public function offerFor(array $serviceIds): void
    {
        $this->services()->sync($serviceIds);

        foreach (DetailFieldOption::query()->where('choice_id', $this->id)->get() as $option) {
            $option->services()->sync($serviceIds);
        }
    }

    /** A slug the library doesn't use yet, archived choices included. */
    public static function uniqueSlug(string $name): string
    {
        $taken = self::query()->pluck('slug')->all();
        $base = Str::limit(Str::slug($name), self::SLUG_MAX - 4, '') ?: 'choice';
        $slug = $base;

        for ($number = 2; in_array($slug, $taken, true); $number++) {
            $slug = "{$base}-{$number}";
        }

        return $slug;
    }
}
