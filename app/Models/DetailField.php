<?php

namespace App\Models;

use App\Enums\DetailAnswerType;
use App\Enums\DetailFieldScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

/**
 * One question a service can ask about a project, from the catalog
 * DetailFieldSeeder fills; which services ask it, and in what order, is
 * `service_detail_fields`.
 *
 * A stone-scope question is answered on each stone row, in its own column of
 * `inquiry_stones` (STONE_INPUT_KEYS names them); a project-scope one once,
 * in `inquiry_detail_answers`. Edge profile and finish take their answers
 * from their own tables; every other listed answer is a
 * DetailFieldOption.
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property DetailFieldScope $scope
 * @property DetailAnswerType $answer_type
 * @property bool $allows_custom Whether a value besides the listed options is accepted.
 * @property int $sort_order
 * @property-read Collection<int, DetailFieldOption> $options
 */
#[Fillable(['slug', 'name', 'scope', 'answer_type', 'allows_custom', 'sort_order'])]
class DetailField extends Model
{
    public const QUANTITY = 'quantity';

    public const SLAB_SIZE = 'slab_size';

    public const EDGE_PROFILE = 'edge_profile';

    public const FINISH = 'finish';

    /**
     * The stone-scope questions, by slug: the key a stone row is sent under,
     * which is also the request's error path.
     */
    public const STONE_INPUT_KEYS = [
        self::QUANTITY => 'quantity',
        self::SLAB_SIZE => 'slabSize',
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
        ];
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

    /** Whether its answers are the rows of `edge_profiles` or `finishes`, by name. */
    public function answersFromCatalog(): bool
    {
        return in_array($this->slug, [self::EDGE_PROFILE, self::FINISH], true);
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
            DetailAnswerType::Choice => [...$attributes, 'option_id' => $this->closedOptions()->firstWhere('slug', (string) $value)->id
                ?? throw new InvalidArgumentException("{$this->slug} has no option {$value}.")],
            DetailAnswerType::Boolean => [...$attributes, 'boolean_value' => filter_var($value, FILTER_VALIDATE_BOOLEAN)],
        };
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
