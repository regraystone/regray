<?php

namespace App\Models;

use App\Enums\DetailAnswerType;
use App\Enums\DetailFieldScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * One question a service can ask about a project, from the catalog
 * DetailFieldSeeder fills; which services ask it, and in what order, is
 * `service_detail_fields`.
 *
 * A stone-scope question is answered on each stone row, in its own column of
 * `inquiry_stones` (STONE_INPUT_KEYS names them); a project-scope one once,
 * in `inquiry_detail_answers`. Edge profile and finish take their answers
 * from their own tables; slab size and thickness list the stone's own
 * sizes and thicknesses (isMeasurement()) and are kept as numbers and a unit;
 * every other listed answer is a DetailFieldOption.
 *
 * The Questions page under Services manages them. An archived one
 * (`archived_at`) is asked by no service and can't be chosen for one; its row
 * stays, as sent inquiries' answers reference it.
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property DetailFieldScope $scope
 * @property DetailAnswerType $answer_type
 * @property bool $allows_custom Whether a value besides the listed options is accepted.
 * @property string|null $custom_label What a free entry's Other reads ("Custom size"); none reads Other.
 * @property string|null $custom_hint The line under the box Other opens.
 * @property int $sort_order
 * @property Carbon|null $archived_at
 * @property-read Collection<int, DetailFieldOption> $options
 */
#[Fillable(['slug', 'name', 'scope', 'answer_type', 'allows_custom', 'custom_label', 'custom_hint', 'sort_order'])]
class DetailField extends Model
{
    public const QUANTITY = 'quantity';

    public const SLAB_SIZE = 'slab_size';

    public const THICKNESS = 'thickness';

    /**
     * The thickness a stone row answers when its stone lists none: Regray
     * confirms it in the quote. Stored as no thickness. inquiry-flow.ts holds
     * the same words (THICKNESS_ADVICE).
     */
    public const THICKNESS_ADVICE = 'To be advised by Regray';

    public const EDGE_PROFILE = 'edge_profile';

    public const FINISH = 'finish';

    /** The furniture size, asked by the table shape's `size_kind` where a service asks both. */
    public const SIZE = 'size';

    /** The table shape, whose choice says how the size is asked. */
    public const TABLE_SHAPE = 'table_shape';

    /**
     * The table base design questions, by this prefix: each table service asks
     * its own (table_base_design_center, _side, _dining), listing only the
     * bases made for that table.
     */
    public const TABLE_BASE_DESIGN = 'table_base_design';

    /**
     * The stone-scope questions, by slug: the key a stone row is sent under,
     * which is also the request's error path.
     */
    public const STONE_INPUT_KEYS = [
        self::QUANTITY => 'quantity',
        self::SLAB_SIZE => 'slabSize',
        self::THICKNESS => 'thickness',
        self::EDGE_PROFILE => 'edge',
        self::FINISH => 'finish',
    ];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'scope' => DetailFieldScope::class,
            'answer_type' => DetailAnswerType::class,
            'allows_custom' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    /**
     * The questions still on the Questions page, which a service may ask.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function notArchived(Builder $query): void
    {
        $query->whereNull($query->qualifyColumn('archived_at'));
    }

    /** @return BelongsToMany<Service, $this> The services that ask it. */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'service_detail_fields');
    }

    /** @return HasMany<DetailFieldOption, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(DetailFieldOption::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Whether the service it was loaded through requires an answer to it
     * (`service_detail_fields.is_required`). Read outside a service, it is.
     */
    public function isRequiredByService(): bool
    {
        $asked = $this->relationLoaded('pivot') ? $this->getRelation('pivot') : null;

        return ! $asked instanceof ServiceDetailField || $asked->is_required;
    }

    /** Where it comes in the order of the service it was loaded through. */
    public function serviceSortOrder(): int
    {
        $asked = $this->relationLoaded('pivot') ? $this->getRelation('pivot') : null;

        return $asked instanceof ServiceDetailField ? $asked->sort_order : $this->sort_order;
    }

    /**
     * Whether its choices are its own list, which the Questions page edits: a
     * list question that doesn't answer from edge_profiles or finishes.
     */
    public function hasOwnChoices(): bool
    {
        return $this->answer_type === DetailAnswerType::Choice && ! $this->answersFromCatalog();
    }

    /**
     * Make its closed options (the ones a client picks from) these library
     * choices, by id, in this order. A choice it listed before keeps its
     * option, taken off or not, so the answers that chose it keep it; a new
     * one gets an option of its own, with a slug the question doesn't use
     * yet; one left out is taken off (`archived_at`) rather than deleted, as
     * a sent inquiry's answer may be it. Each option then mirrors its choice
     * (Choice::mirror()). Its suggestions are left as they are.
     *
     * @param  list<int>  $choiceIds
     */
    public function writeChoices(array $choiceIds): void
    {
        /** @var Collection<int, DetailFieldOption> $existing */
        $existing = DetailFieldOption::query()->where('detail_field_id', $this->id)->get();
        $slugs = $existing->pluck('slug')->all();
        $choices = Choice::query()->whereIn('id', $choiceIds)->with('services:services.id')->get()->keyBy('id');
        $kept = [];

        foreach ($choiceIds as $index => $choiceId) {
            $choice = $choices->get($choiceId);

            if ($choice === null) {
                continue;
            }

            // Its listing here, or one of its name from before the library, which it takes over.
            $option = $existing->firstWhere('choice_id', $choice->id)
                ?? $existing->first(fn (DetailFieldOption $option): bool => $option->choice_id === null && ! $option->is_suggestion && mb_strtolower($option->name) === mb_strtolower($choice->name));

            if ($option === null) {
                $base = Str::limit($choice->slug, 96, '');
                $slug = $base;

                for ($number = 2; in_array($slug, $slugs, true); $number++) {
                    $slug = "{$base}-{$number}";
                }

                $slugs[] = $slug;
                $option = new DetailFieldOption(['detail_field_id' => $this->id, 'choice_id' => $choice->id, 'slug' => $slug, 'name' => $choice->name, 'is_suggestion' => false, 'links_to_stone' => false]);
            }

            $option->forceFill(['choice_id' => $choice->id, 'sort_order' => $index, 'archived_at' => null])->save();
            $option->services()->sync($choice->services->modelKeys());
            $kept[] = $option->id;
        }

        DetailFieldOption::query()
            ->where('detail_field_id', $this->id)
            ->where('is_suggestion', false)
            ->whereNotIn('id', $kept)
            ->whereNull('archived_at')
            ->update(['archived_at' => now()]);

        $choices->each(fn (Choice $choice) => $choice->mirror());
        $this->unsetRelation('options');
    }

    /**
     * Make its suggestions (the sample values beside a free entry) these, in
     * this order, each a value and an optional note ("approx. 0.6 sqm", kept
     * as its description). One of the same value, ignoring case, keeps its
     * row; a new one gets a slug the question doesn't use yet; one left out
     * is switched off. Its closed options are left as they are.
     *
     * @param  list<array{name: string, note: string|null}>  $suggestions
     */
    public function writeSuggestions(array $suggestions): void
    {
        /** @var Collection<int, DetailFieldOption> $existing */
        $existing = DetailFieldOption::query()->where('detail_field_id', $this->id)->get();
        $slugs = $existing->pluck('slug')->all();
        $kept = [];

        foreach ($suggestions as $index => $suggestion) {
            $option = $existing->first(fn (DetailFieldOption $option): bool => $option->is_suggestion && mb_strtolower($option->name) === mb_strtolower($suggestion['name']));

            if ($option === null) {
                $base = Str::limit(Str::slug($suggestion['name']), 96, '') ?: 'answer';
                $slug = $base;

                for ($number = 2; in_array($slug, $slugs, true); $number++) {
                    $slug = "{$base}-{$number}";
                }

                $slugs[] = $slug;
                $option = new DetailFieldOption(['detail_field_id' => $this->id, 'slug' => $slug, 'is_suggestion' => true, 'links_to_stone' => false]);
            }

            $option->forceFill(['name' => $suggestion['name'], 'description' => $suggestion['note'], 'sort_order' => $index, 'is_active' => true])->save();
            $kept[] = $option->id;
        }

        DetailFieldOption::query()
            ->where('detail_field_id', $this->id)
            ->where('is_suggestion', true)
            ->whereNotIn('id', $kept)
            ->update(['is_active' => false]);

        $this->unsetRelation('options');
    }

    /** Whether it takes a free entry, a written answer or a number, which may list suggestions. */
    public function takesFreeEntry(): bool
    {
        return in_array($this->answer_type, [DetailAnswerType::Text, DetailAnswerType::Number], true);
    }

    /** Whether its answers are the rows of `edge_profiles` or `finishes`, by name. */
    public function answersFromCatalog(): bool
    {
        return in_array($this->slug, [self::EDGE_PROFILE, self::FINISH], true);
    }

    /**
     * Whether it asks a measurement of the stone: a slab size or thickness,
     * answered from the stone's own options or as a number and unit under
     * Other, and stored in numeric columns rather than as text.
     */
    public function isMeasurement(): bool
    {
        return in_array($this->slug, [self::SLAB_SIZE, self::THICKNESS], true);
    }

    /** The key a stone row sends its answer under, or null for a project-scope question. */
    public function stoneInputKey(): ?string
    {
        return self::STONE_INPUT_KEYS[$this->slug] ?? null;
    }

    /**
     * A project-scope answer as its `inquiry_detail_answers` row: the value in
     * the one column its answer type uses, a closed option by its id. A
     * custom choice ("Custom shape") also keeps the client's description of
     * it, `$custom`, in `text_value`. Expects the options loaded and the value
     * already validated.
     *
     * @return array{detail_field_id: int, text_value: string|null, number_value: string|null, option_id: int|null, boolean_value: bool|null}
     *
     * @throws InvalidArgumentException When a choice names no closed option.
     */
    public function answerAttributes(string|int|float|bool $value, ?string $custom = null): array
    {
        $attributes = ['detail_field_id' => $this->id, 'text_value' => null, 'number_value' => null, 'option_id' => null, 'boolean_value' => null];

        if ($this->answer_type === DetailAnswerType::Choice) {
            $option = $this->closedOption((string) $value) ?? throw new InvalidArgumentException("{$this->slug} has no option {$value}.");
            $described = $option->is_custom && $custom !== null && trim($custom) !== '' ? trim($custom) : null;

            return [...$attributes, 'option_id' => $option->id, 'text_value' => $described];
        }

        return match ($this->answer_type) {
            DetailAnswerType::Text => [...$attributes, 'text_value' => trim((string) $value)],
            DetailAnswerType::Number => [...$attributes, 'number_value' => (string) (float) $value],
            DetailAnswerType::Boolean => [...$attributes, 'boolean_value' => filter_var($value, FILTER_VALIDATE_BOOLEAN)],
        };
    }

    /**
     * The closed option with this slug: an offered one, or one no longer
     * offered only when an edit keeps the answer it had. Null when there is
     * none.
     */
    public function closedOption(string $slug): ?DetailFieldOption
    {
        return $this->closedOptions()->firstWhere('slug', $slug)
            ?? $this->options->first(fn (DetailFieldOption $option): bool => ! $option->is_suggestion && $option->slug === $slug);
    }

    /**
     * The active options a client must pick from (not suggestions), loaded:
     * every one, or only those offered for this service (a choice that lists
     * services is offered for those alone). Filtering by service loads the
     * options' `services` once when they aren't loaded.
     *
     * @return Collection<int, DetailFieldOption>
     */
    public function closedOptions(?int $serviceId = null): Collection
    {
        if ($serviceId !== null) {
            // Once for the whole list, where a caller loaded the options alone.
            $this->options->loadMissing('services:services.id');
        }

        return $this->options
            ->filter(fn (DetailFieldOption $option): bool => $option->is_active && $option->archived_at === null && ! $option->is_suggestion && $option->isOfferedFor($serviceId))
            ->values();
    }

    /** Whether it is one of the table services' base design questions (TABLE_BASE_DESIGN). */
    public function isTableBaseDesign(): bool
    {
        return $this->slug === self::TABLE_BASE_DESIGN || str_starts_with($this->slug, self::TABLE_BASE_DESIGN.'_');
    }

    /**
     * Whether its choices are asked with picture cards: a list question one
     * of whose choices has a picture.
     */
    public function asksWithPictures(): bool
    {
        return $this->hasOwnChoices() && $this->closedOptions()->contains(fn (DetailFieldOption $option): bool => filled($option->thumbnail_path));
    }

    /**
     * The active sample values shown beside a free entry, loaded.
     *
     * @return Collection<int, DetailFieldOption>
     */
    public function suggestions(): Collection
    {
        return $this->options->filter(fn (DetailFieldOption $option): bool => $option->is_active && $option->is_suggestion)->values();
    }
}
