<?php

namespace App\Catalog\Lists;

use App\Catalog\CatalogListDefinition;
use App\Enums\DetailAnswerType;
use App\Enums\DetailFieldScope;
use App\Http\Requests\Service\StoreServiceRequest;
use App\Models\Choice;
use App\Models\DetailField;
use App\Models\DetailFieldOption;
use App\Models\Service;
use App\Support\Slugs;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The questions a service can ask about a project on the Inquire page, such
 * as "How many seaters?". Which services ask one, in what order and whether
 * it must be answered, is set on each service's form.
 *
 * A question added here is answered once for the project, as one written on
 * a service's form is: a stone question has a column of its own on each
 * stone row, so those stay the seeded five. What kind of answer a question
 * takes is fixed once it is added, as its answers are kept by kind. A list
 * question picks its choices from the library (the Choices page, Choice), in
 * its order, and they can change (DetailField::writeChoices() keeps the ones
 * past answers chose), unless they are the edge profiles or finishes. One
 * not in the library yet is added there: the field links to the Choices
 * page, which brings the admin back with it picked (`addLink`).
 *
 * A written or number question may list suggested answers for the client to
 * pick from, each with an optional note after a bar ("2 | approx. 0.6 sqm"
 * reads "2 – approx. 0.6 sqm", and the answer stays 2), and says what the
 * entry for the client's own answer reads ("Custom size"; none: Other) and
 * the line under the box it opens. A stone question's sizes and thicknesses
 * are its stone's own, so it lists no suggestions; How many pieces is typed
 * in, so it has no own answer to name either.
 *
 * Archiving sets `archived_at`, once no service asks it; a stone question is
 * never archived. The row stays, as sent inquiries' answers reference it.
 *
 * @extends CatalogListDefinition<DetailField>
 */
class QuestionList extends CatalogListDefinition
{
    /** What each kind of answer is called, in the order the form offers them. */
    private const ANSWER_TYPES = [
        'text' => 'Written answer',
        'number' => 'Number',
        'boolean' => 'Yes or no',
        'choice' => 'Choose from a list',
    ];

    /** detail_fields.slug's length. */
    private const SLUG_MAX = 50;

    /** Shown for a question that takes a free entry, a written answer or a number. */
    private const FREE_ENTRY = ['field' => 'answer_type', 'in' => [DetailAnswerType::Text->value, DetailAnswerType::Number->value]];

    /** The most suggested answers a question lists. */
    private const SUGGESTIONS_MAX = 20;

    /** A suggestion's length, its value and its note together. */
    private const SUGGESTION_MAX = 150;

    /** A suggestion's value, as detail_field_options.name holds it. */
    private const SUGGESTION_VALUE_MAX = 100;

    /** detail_fields.custom_label's and custom_hint's lengths. */
    private const CUSTOM_LABEL_MAX = 50;

    private const CUSTOM_HINT_MAX = 150;

    /** What parts a suggestion's value from its note. */
    private const NOTE_BAR = '|';

    public function title(): string
    {
        return 'Questions';
    }

    public function singular(): string
    {
        return 'question';
    }

    public function subtitle(): string
    {
        return 'What a service can ask about a project on the Inquire page';
    }

    public function model(): string
    {
        return DetailField::class;
    }

    public function live(): Builder
    {
        return DetailField::query()->notArchived();
    }

    public function archiveItem(Model $item): void
    {
        $item->forceFill(['archived_at' => now()])->save();
    }

    public function query(?string $escapedSearch): Builder
    {
        return $this->live()
            ->when($escapedSearch !== null, fn (Builder $query) => $query->where('name', 'ILIKE', "%{$escapedSearch}%"))
            ->withCount(['services' => fn (Builder $services) => $services->whereNull('services.archived_at')])
            ->with('options')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function columns(): array
    {
        return [['key' => 'answer', 'header' => 'Answer'], ['key' => 'asked', 'header' => 'Asked'], ['key' => 'services', 'header' => 'Services']];
    }

    public function fields(): array
    {
        return [
            ['name' => 'name', 'label' => 'Question', 'type' => 'text', 'required' => true, 'maxLength' => StoreServiceRequest::QUESTION_NAME_MAX, 'hint' => 'As the client reads it, such as How many seaters?'],
            [
                'name' => 'answer_type',
                'label' => 'Answer',
                'type' => 'select',
                'required' => true,
                'hint' => 'What kind of answer it takes. It stays once the question is added.',
                'options' => array_map(fn (string $value, string $label): array => ['value' => $value, 'label' => $label], array_keys(self::ANSWER_TYPES), self::ANSWER_TYPES),
            ],
            [
                'name' => 'choices',
                'label' => 'Choices',
                'type' => 'multiselect',
                'required' => true,
                'maxItems' => StoreServiceRequest::CHOICES_MAX,
                'hint' => 'The answers the client picks from, in this order, from the Choices page. A choice taken off stays on the inquiries that chose it.',
                'options' => array_values(Choice::query()->notArchived()->orderBy('sort_order')->orderBy('id')->get(['id', 'name'])
                    ->map(fn (Choice $choice): array => ['id' => $choice->id, 'label' => $choice->name])->all()),
                'addLink' => ['list' => 'choices', 'label' => 'Add a new choice'],
                'showWhen' => ['field' => 'answer_type', 'equals' => DetailAnswerType::Choice->value],
            ],
            [
                'name' => 'suggestions',
                'label' => 'Suggested answers',
                'type' => 'tags',
                'maxItems' => self::SUGGESTIONS_MAX,
                'maxLength' => self::SUGGESTION_MAX,
                'hint' => 'Listed for the client to pick from, in this order, beside their own answer. Add a note after a bar: "2 | approx. 0.6 sqm" reads "2 – approx. 0.6 sqm", and the answer stays 2.',
                'showWhen' => self::FREE_ENTRY,
            ],
            ['name' => 'custom_label', 'label' => 'Own answer reads', 'type' => 'text', 'maxLength' => self::CUSTOM_LABEL_MAX, 'hint' => 'The list\'s entry for the client\'s own answer, such as Custom size. Left empty, it reads Other.', 'showWhen' => self::FREE_ENTRY],
            ['name' => 'custom_hint', 'label' => 'Line under the own answer', 'type' => 'text', 'maxLength' => self::CUSTOM_HINT_MAX, 'hint' => 'Shown under the box the client types their own answer in, such as Enter your desired size.', 'showWhen' => self::FREE_ENTRY],
        ];
    }

    public function rules(?Model $item): array
    {
        $choices = [
            'choices' => ['nullable', 'array', 'max:'.StoreServiceRequest::CHOICES_MAX],
            'choices.*' => ['required', 'integer', 'distinct', Rule::exists('choices', 'id')->whereNull('archived_at')],
        ];

        return [
            'name' => ['required', 'string', 'max:'.StoreServiceRequest::QUESTION_NAME_MAX, $this->uniqueIgnoringCase('name', $item, self::say('Another question, maybe an archived one, already reads that way.'))],
            ...($item === null ? ['answer_type' => ['required', Rule::enum(DetailAnswerType::class)], ...$choices] : []),
            ...($item?->hasOwnChoices() ? $choices : []),
            'suggestions' => ['nullable', 'array', 'max:'.self::SUGGESTIONS_MAX],
            'suggestions.*' => ['required', 'string', 'max:'.self::SUGGESTION_MAX, 'distinct:ignore_case'],
            'custom_label' => ['nullable', 'string', 'max:'.self::CUSTOM_LABEL_MAX],
            'custom_hint' => ['nullable', 'string', 'max:'.self::CUSTOM_HINT_MAX],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => __('Write the question.'),
            'name.max' => __('A question can be :max characters at most.'),
            'answer_type.required' => __('Choose what kind of answer it takes.'),
            'answer_type.enum' => __('Choose what kind of answer it takes.'),
            'choices.max' => __('A question can list :max choices at most.'),
            'choices.*.required' => __('Remove the empty choice.'),
            'choices.*.integer' => __('Choose the choices from the list.'),
            'choices.*.exists' => __('One of those choices is no longer in the library. Remove it.'),
            'choices.*.distinct' => __('That choice is already listed.'),
            'suggestions.max' => __('A question can list :max suggested answers at most.'),
            'suggestions.*.required' => __('Remove the empty answer.'),
            'suggestions.*.max' => __('Each suggested answer can be :max characters at most.'),
            'suggestions.*.distinct' => __('That answer is already listed.'),
            'custom_label.max' => __('Keep it to :max characters or fewer.'),
            'custom_hint.max' => __('Keep it to :max characters or fewer.'),
        ];
    }

    /**
     * A list question takes at least two choices, with one custom and one
     * "advise me" at most. A number question's suggested answers are
     * numbers, and every suggested answer has a value before its note.
     */
    public function after(): \Closure
    {
        return function (Validator $validator, ?Model $item): void {
            $data = $validator->getData();
            $type = $item instanceof DetailField ? $item->answer_type->value : ($data['answer_type'] ?? null);

            if (is_array($data['suggestions'] ?? null) && ! $validator->errors()->has('suggestions.*')) {
                foreach (self::suggestions($data['suggestions']) as $index => $suggestion) {
                    if ($suggestion['name'] === '') {
                        $validator->errors()->add("suggestions.{$index}", self::say('Write the answer before the bar.'));
                    } elseif (mb_strlen($suggestion['name']) > self::SUGGESTION_VALUE_MAX) {
                        $validator->errors()->add("suggestions.{$index}", self::say('An answer can be :max characters at most, before its note.', ['max' => (string) self::SUGGESTION_VALUE_MAX]));
                    } elseif ($type === DetailAnswerType::Number->value && (! is_numeric($suggestion['name']) || (float) $suggestion['name'] <= 0)) {
                        $validator->errors()->add("suggestions.{$index}", self::say('A number question\'s answers are numbers above 0, such as 4.'));
                    }
                }
            }

            $isList = $item instanceof DetailField
                ? $item->hasOwnChoices()
                : ($data['answer_type'] ?? null) === DetailAnswerType::Choice->value;
            $choices = $data['choices'] ?? [];

            if (! $isList || $validator->errors()->has('choices') || $validator->errors()->has('choices.*')) {
                return;
            }

            if (! is_array($choices) || count($choices) < 2) {
                $validator->errors()->add('choices', self::say('List at least two choices.'));

                return;
            }

            $picked = Choice::query()->whereIn('id', $choices)->get(['is_custom', 'is_advisory']);

            if ($picked->where('is_custom', true)->count() > 1) {
                $validator->errors()->add('choices', self::say('List one custom choice at most.'));
            } elseif ($picked->where('is_advisory', true)->count() > 1) {
                $validator->errors()->add('choices', self::say('List one "Not sure yet, advise me" choice at most.'));
            }
        };
    }

    public function prepare(array $input): array
    {
        $input = parent::prepare($input);

        if (is_array($input['choices'] ?? null)) {
            $input['choices'] = array_values(array_map(fn (mixed $choice): mixed => is_string($choice) && ctype_digit($choice) ? (int) $choice : $choice, $input['choices']));
        }

        return $input;
    }

    public function archiveDescription(): string
    {
        return self::say('It leaves this list and can no longer be asked; past inquiries keep their answers. A question a service still asks can\'t be archived.');
    }

    public function emptyDescription(): string
    {
        return self::say('Add a question for services to ask.');
    }

    public function name(Model $item): string
    {
        return $item->name;
    }

    public function row(Model $item): array
    {
        return [
            'cells' => [
                'answer' => self::ANSWER_TYPES[$item->answer_type->value],
                'asked' => $item->scope === DetailFieldScope::Stone ? self::say('On each stone') : self::say('Once for the project'),
                'services' => self::count((int) $item->getAttribute('services_count'), 'service', 'services'),
            ],
            'values' => [
                'name' => $item->name,
                'answer_type' => $item->answer_type->value,
                'choices' => self::choiceIds($item),
                'suggestions' => array_values($item->suggestions()->map(fn (DetailFieldOption $option): string => filled($option->description)
                    ? $option->name.' '.self::NOTE_BAR.' '.$option->description
                    : $option->name)->all()),
                'custom_label' => (string) $item->custom_label,
                'custom_hint' => (string) $item->custom_hint,
            ],
            'fixed' => array_values(array_filter([
                'answer_type',
                $item->hasOwnChoices() ? null : 'choices',
                // A stone row lists its own stone's sizes and thicknesses.
                $item->scope === DetailFieldScope::Stone && $item->takesFreeEntry() ? 'suggestions' : null,
                // How many pieces is only typed in.
                $item->slug === DetailField::QUANTITY ? 'custom_label' : null,
                $item->slug === DetailField::QUANTITY ? 'custom_hint' : null,
            ])),
        ];
    }

    public function fill(Model $item, array $data): void
    {
        $item->fill(['name' => $data['name']]);

        if (! $item->exists) {
            $type = DetailAnswerType::from((string) $data['answer_type']);
            $item->fill([
                'slug' => Slugs::unique(DetailField::query(), $data['name'], self::SLUG_MAX, '_'),
                'scope' => DetailFieldScope::Project,
                'answer_type' => $type,
                // A written or number answer takes what the client types; a list only its choices.
                'allows_custom' => in_array($type, [DetailAnswerType::Text, DetailAnswerType::Number], true),
                'sort_order' => ((int) DetailField::query()->max('sort_order')) + 1,
            ]);
        }

        if ($item->takesFreeEntry() && $item->slug !== DetailField::QUANTITY) {
            $item->fill([
                'custom_label' => filled($data['custom_label'] ?? null) ? (string) $data['custom_label'] : null,
                'custom_hint' => filled($data['custom_hint'] ?? null) ? (string) $data['custom_hint'] : null,
            ]);
        }
    }

    public function sync(Model $item, array $data): void
    {
        if ($item->hasOwnChoices() && is_array($data['choices'] ?? null)) {
            $item->writeChoices(array_values(array_map(intval(...), $data['choices'])));
        }

        if ($item->takesFreeEntry() && $item->scope === DetailFieldScope::Project && is_array($data['suggestions'] ?? null)) {
            $item->writeSuggestions(self::suggestions($data['suggestions']));
        }
    }

    /**
     * The suggested answers as typed, each split at its first bar into its
     * value and its note.
     *
     * @param  array<int|string, mixed>  $typed
     * @return list<array{name: string, note: string|null}>
     */
    private static function suggestions(array $typed): array
    {
        return array_values(array_map(function (mixed $line): array {
            [$name, $note] = array_pad(explode(self::NOTE_BAR, (string) $line, 2), 2, '');

            return ['name' => trim($name), 'note' => trim($note) === '' ? null : trim($note)];
        }, $typed));
    }

    public function usage(Model $item): ?string
    {
        if ($item->scope === DetailFieldScope::Stone) {
            return self::say('Every stone row has a place for its answer, so it stays on the list.');
        }

        $services = Service::query()->notArchived()->whereHas('detailFields', fn (Builder $fields) => $fields->whereKey($item->getKey()))->count();

        return $services === 0 ? null : self::say('Asked by :count. Take it off their questions first.', ['count' => self::count($services, 'service', 'services')]);
    }

    public function snapshot(Model $item): array
    {
        return [
            'name' => $item->name,
            'slug' => $item->slug,
            'scope' => $item->scope->value,
            'answer_type' => $item->answer_type->value,
            'choices' => self::choices($item),
            'suggestions' => array_values($item->suggestions()->map(fn (DetailFieldOption $option): array => ['name' => $option->name, 'note' => $option->description])->all()),
            'custom_label' => $item->custom_label,
            'custom_hint' => $item->custom_hint,
            'archived_at' => $item->archived_at?->toIso8601String(),
        ];
    }

    /**
     * Its library choices, by id, as the form edits them; none for a question
     * that answers from another list.
     *
     * @return list<int>
     */
    private static function choiceIds(DetailField $item): array
    {
        if (! $item->hasOwnChoices()) {
            return [];
        }

        return array_values($item->options
            ->filter(fn (DetailFieldOption $option): bool => ! $option->is_suggestion && $option->archived_at === null && $option->choice_id !== null)
            ->map(fn (DetailFieldOption $option): int => (int) $option->choice_id)
            ->all());
    }

    /**
     * Its own choices' names, as the audit keeps them; none for a question
     * that answers from another list.
     *
     * @return list<string>
     */
    private static function choices(DetailField $item): array
    {
        if (! $item->hasOwnChoices()) {
            return [];
        }

        return array_values($item->closedOptions()->map(fn (DetailFieldOption $option): string => $option->name)->all());
    }
}
