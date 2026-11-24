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
 * @property int $sort_order
 * @property Carbon|null $archived_at
 * @property-read Collection<int, DetailFieldOption> $options
 */
#[Fillable(['slug', 'name', 'scope', 'answer_type', 'allows_custom', 'sort_order'])]
class DetailField extends Model
{
    public const QUANTITY = 'quantity';

    public const SLAB_SIZE = 'slab_size';

    public const THICKNESS = 'thickness';

    public const EDGE_PROFILE = 'edge_profile';

    public const FINISH = 'finish';

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
     * Make its closed options (the ones a client picks from) these choices, in
     * this order. One already listed by that name, ignoring case, is kept, so
     * the answers that chose it keep it; a new one is added; one left out is
     * switched off rather than deleted, as a sent inquiry's answer may be it.
     * Its suggestions are left as they are.
     *
     * @param  list<string>  $choices
     */
    public function writeChoices(array $choices): void
    {
        /** @var Collection<int, DetailFieldOption> $existing */
        $existing = DetailFieldOption::query()->where('detail_field_id', $this->id)->get();
        $slugs = $existing->pluck('slug')->all();
        $kept = [];

        foreach ($choices as $index => $choice) {
            $option = $existing->first(fn (DetailFieldOption $option): bool => ! $option->is_suggestion && mb_strtolower($option->name) === mb_strtolower($choice));

            if ($option === null) {
                $base = Str::limit(Str::slug($choice), 96, '') ?: 'choice';
                $slug = $base;

                for ($number = 2; in_array($slug, $slugs, true); $number++) {
                    $slug = "{$base}-{$number}";
                }

                $slugs[] = $slug;
                $option = new DetailFieldOption(['detail_field_id' => $this->id, 'slug' => $slug, 'is_suggestion' => false, 'links_to_stone' => false]);
            }

            $option->fill(['name' => $choice, 'sort_order' => $index, 'is_active' => true])->save();
            $kept[] = $option->id;
        }

        DetailFieldOption::query()
            ->where('detail_field_id', $this->id)
            ->where('is_suggestion', false)
            ->whereNotIn('id', $kept)
            ->update(['is_active' => false]);

        $this->unsetRelation('options');
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
     * the one column its answer type uses, a closed option by its id. Expects
     * the options loaded and the value already validated.
     *
     * @return array{detail_field_id: int, text_value: string|null, number_value: string|null, option_id: int|null, boolean_value: bool|null}
     *
     * @throws InvalidArgumentException When a choice names no closed option.
     */
    public function answerAttributes(string|int|float|bool $value): array
    {
        $attributes = ['detail_field_id' => $this->id, 'text_value' => null, 'number_value' => null, 'option_id' => null, 'boolean_value' => null];

        return match ($this->answer_type) {
            DetailAnswerType::Text => [...$attributes, 'text_value' => trim((string) $value)],
            DetailAnswerType::Number => [...$attributes, 'number_value' => (string) (float) $value],
            DetailAnswerType::Choice => [...$attributes, 'option_id' => $this->closedOptionId((string) $value)],
            DetailAnswerType::Boolean => [...$attributes, 'boolean_value' => filter_var($value, FILTER_VALIDATE_BOOLEAN)],
        };
    }

    /**
     * The id of the closed option with this slug: an active one, or an
     * inactive one only when an edit keeps the answer it had.
     */
    private function closedOptionId(string $slug): int
    {
        $option = $this->closedOptions()->firstWhere('slug', $slug)
            ?? $this->options->first(fn (DetailFieldOption $option): bool => ! $option->is_suggestion && $option->slug === $slug);

        return $option->id ?? throw new InvalidArgumentException("{$this->slug} has no option {$slug}.");
    }

    /**
     * The active options a client must pick from (not suggestions), loaded.
     *
     * @return Collection<int, DetailFieldOption>
     */
    public function closedOptions(): Collection
    {
        return $this->options->filter(fn (DetailFieldOption $option): bool => $option->is_active && ! $option->is_suggestion)->values();
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
