<?php

namespace App\Catalog\Lists;

use App\Catalog\CatalogListDefinition;
use App\Enums\DetailAnswerType;
use App\Enums\DetailFieldScope;
use App\Http\Requests\Service\StoreServiceRequest;
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
 * question's choices can change (DetailField::writeChoices() keeps the ones
 * past answers chose), unless they are the edge profiles or finishes.
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
                'type' => 'tags',
                'required' => true,
                'maxItems' => StoreServiceRequest::CHOICES_MAX,
                'maxLength' => StoreServiceRequest::CHOICE_MAX,
                'hint' => 'The answers the client picks from: type one, then Enter. A choice taken off stays on the inquiries that chose it.',
                'showWhen' => ['field' => 'answer_type', 'equals' => DetailAnswerType::Choice->value],
            ],
        ];
    }

    public function rules(?Model $item): array
    {
        $choices = [
            'choices' => ['nullable', 'array', 'max:'.StoreServiceRequest::CHOICES_MAX],
            'choices.*' => ['required', 'string', 'max:'.StoreServiceRequest::CHOICE_MAX, 'distinct:ignore_case'],
        ];

        return [
            'name' => ['required', 'string', 'max:'.StoreServiceRequest::QUESTION_NAME_MAX, $this->uniqueIgnoringCase('name', $item, self::say('Another question, maybe an archived one, already reads that way.'))],
            ...($item === null ? ['answer_type' => ['required', Rule::enum(DetailAnswerType::class)], ...$choices] : []),
            ...($item?->hasOwnChoices() ? $choices : []),
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
            'choices.*.max' => __('Each choice can be :max characters at most.'),
            'choices.*.distinct' => __('That choice is already listed.'),
        ];
    }

    /** A list question takes at least two choices. */
    public function after(): \Closure
    {
        return function (Validator $validator, ?Model $item): void {
            $data = $validator->getData();
            $isList = $item instanceof DetailField
                ? $item->hasOwnChoices()
                : ($data['answer_type'] ?? null) === DetailAnswerType::Choice->value;
            $choices = $data['choices'] ?? [];

            if ($isList && ! $validator->errors()->has('choices') && (! is_array($choices) || count($choices) < 2)) {
                $validator->errors()->add('choices', self::say('List at least two choices.'));
            }
        };
    }

    public function prepare(array $input): array
    {
        $input = parent::prepare($input);

        if (is_array($input['choices'] ?? null)) {
            $input['choices'] = array_values(array_map(fn (mixed $choice): mixed => is_string($choice) ? trim($choice) : $choice, $input['choices']));
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
            'values' => ['name' => $item->name, 'answer_type' => $item->answer_type->value, 'choices' => self::choices($item)],
            'fixed' => $item->hasOwnChoices() ? ['answer_type'] : ['answer_type', 'choices'],
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
    }

    public function sync(Model $item, array $data): void
    {
        if ($item->hasOwnChoices() && is_array($data['choices'] ?? null)) {
            $item->writeChoices(array_values(array_map(strval(...), $data['choices'])));
        }
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
            'archived_at' => $item->archived_at?->toIso8601String(),
        ];
    }

    /**
     * Its own choices, as the form edits them; none for a question that
     * answers from another list.
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
