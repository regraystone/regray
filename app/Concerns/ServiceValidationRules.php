<?php

namespace App\Concerns;

use App\Enums\DetailAnswerType;
use App\Enums\DetailFieldScope;
use App\Enums\Fulfillment;
use App\Models\DetailField;
use App\Models\Service;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The Add and Edit service forms' rules, shared by StoreServiceRequest and
 * UpdateServiceRequest. The limits are the columns' (the services migration)
 * and the Inquire page's room for them.
 *
 * The slug is not on the form: a new service's is made from its name
 * (ServiceAdminService), and an existing one's never changes, because code
 * reaches some services by slug (FurnitureCustomizationService).
 *
 * A service that needs stones is made from at least one stone type, or the
 * Inquire page could not list a stone for it. One that needs none asks no
 * stone question, since those are answered on a stone row.
 *
 * A question is one from the catalog (`id`), or a new one written here
 * (`name`, `answer_type`, and its choices when it is chosen from a list),
 * which ServiceAdminService adds to the catalog as a project question. A new
 * one may not repeat a question the catalog has, so it is chosen instead.
 */
trait ServiceValidationRules
{
    public const DESCRIPTION_MAX = 500;

    public const FEATURES_MAX = 8;

    public const FEATURE_MAX = 150;

    public const QUESTION_NAME_MAX = 100;

    public const CHOICES_MAX = 10;

    public const CHOICE_MAX = 150;

    /** The service being changed, or null when one is being added. */
    abstract protected function service(): ?Service;

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:150',
                Rule::unique('services', 'name')
                    ->where('service_type_id', $this->input('service_type_id'))
                    ->whereNull('archived_at')
                    ->ignore($this->service()?->id),
            ],
            'service_type_id' => ['required', 'integer', Rule::exists('service_types', 'id')->whereNull('archived_at')],
            'description' => ['required', 'string', 'max:'.self::DESCRIPTION_MAX],
            'features' => ['nullable', 'array', 'max:'.self::FEATURES_MAX],
            'features.*' => ['required', 'string', 'max:'.self::FEATURE_MAX, 'distinct:ignore_case'],
            'requires_stones' => ['required', 'boolean'],
            'stone_type_ids' => ['nullable', 'array', 'required_if_accepted:requires_stones'],
            'stone_type_ids.*' => ['integer', 'distinct', Rule::exists('stone_types', 'id')->where('is_active', true)],
            'fulfillment' => ['required', Rule::enum(Fulfillment::class)],
            'questions' => ['nullable', 'array'],
            'questions.*' => ['array:id,name,answer_type,choices,required'],
            'questions.*.id' => ['nullable', 'required_without:questions.*.name', 'integer', 'distinct', Rule::exists('detail_fields', 'id')],
            'questions.*.name' => ['nullable', 'string', 'max:'.self::QUESTION_NAME_MAX, 'distinct:ignore_case'],
            'questions.*.answer_type' => ['nullable', 'required_with:questions.*.name', Rule::enum(DetailAnswerType::class)],
            'questions.*.choices' => ['nullable', 'array', 'max:'.self::CHOICES_MAX],
            'questions.*.choices.*' => ['required', 'string', 'max:'.self::CHOICE_MAX, 'distinct:ignore_case'],
            'questions.*.required' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:32767'],
        ];
    }

    /** Trim what was typed and number the lists from 0. */
    protected function prepareForValidation(): void
    {
        $text = fn (mixed $value): mixed => is_string($value) ? trim($value) : $value;
        $features = $this->input('features');
        $stoneTypes = $this->input('stone_type_ids');
        $questions = $this->input('questions');

        $this->merge([
            'name' => $text($this->input('name')),
            'description' => $text($this->input('description')),
            'features' => is_array($features) ? array_values(array_map($text, $features)) : $features,
            'stone_type_ids' => is_array($stoneTypes) ? array_values($stoneTypes) : $stoneTypes,
            'questions' => is_array($questions) ? array_values(array_map(
                fn (mixed $question): mixed => is_array($question) ? [
                    ...$question,
                    'name' => $text($question['name'] ?? null) ?: null,
                    'choices' => is_array($question['choices'] ?? null) ? array_values(array_map($text, $question['choices'])) : ($question['choices'] ?? null),
                ] : $question,
                $questions,
            )) : $questions,
        ]);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => __('Enter the service\'s name.'),
            'name.unique' => __('This service type already has a service by that name.'),
            'service_type_id.required' => __('Choose its service type.'),
            'service_type_id.exists' => __('Choose one of the service types.'),
            'description.required' => __('Describe the service in a line, as the Inquire page shows it.'),
            'description.max' => __('The description can be :max characters at most.'),
            'features.max' => __('A service can list :max things it includes at most.'),
            'features.*.required' => __('Write this line, or remove it.'),
            'features.*.max' => __('Each line can be :max characters at most.'),
            'features.*.distinct' => __('That line is already listed.'),
            'stone_type_ids.required_if_accepted' => __('Choose the stone types it is made from, or turn off "Needs stones".'),
            'stone_type_ids.*.exists' => __('Choose from the stone types listed.'),
            'fulfillment.required' => __('Choose how the work reaches the client.'),
            'fulfillment.enum' => __('Choose how the work reaches the client.'),
            'questions.*.id.required_without' => __('Choose a question, or write one.'),
            'questions.*.id.exists' => __('Choose from the questions listed.'),
            'questions.*.id.distinct' => __('That question is already asked.'),
            'questions.*.name.max' => __('A question can be :max characters at most.'),
            'questions.*.name.distinct' => __('That question is already asked.'),
            'questions.*.answer_type.required_with' => __('Choose what kind of answer it takes.'),
            'questions.*.answer_type.enum' => __('Choose what kind of answer it takes.'),
            'questions.*.choices.max' => __('A question can list :max choices at most.'),
            'questions.*.choices.*.required' => __('Remove the empty choice.'),
            'questions.*.choices.*.max' => __('Each choice can be :max characters at most.'),
            'questions.*.choices.*.distinct' => __('That choice is already listed.'),
            'sort_order.integer' => __('Enter a whole number.'),
            'sort_order.min' => __('Enter 0 or more.'),
        ];
    }

    /**
     * A service that needs no stones asks no question about a stone row.
     *
     * @return list<\Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->boolean('requires_stones') || $validator->errors()->has('questions.*')) {
                    return;
                }

                $questions = $this->input('questions');
                $ids = array_map(fn (mixed $question): int => (int) (is_array($question) ? ($question['id'] ?? 0) : 0), is_array($questions) ? $questions : []);
                $stoneQuestions = DetailField::query()->whereKey($ids)->where('scope', DetailFieldScope::Stone)->pluck('id')->all();

                foreach ($ids as $index => $id) {
                    if (in_array($id, $stoneQuestions, true)) {
                        $validator->errors()->add("questions.{$index}.id", __('This question is answered for each stone. Turn on "Needs stones", or remove it.'));
                    }
                }
            },
            function (Validator $validator): void {
                $questions = $this->input('questions');
                $existing = DetailField::query()->pluck('name')->map(fn (string $name): string => mb_strtolower($name))->all();

                foreach (is_array($questions) ? $questions : [] as $index => $question) {
                    if (! is_array($question) || isset($question['id']) || ! is_string($question['name'] ?? null)) {
                        continue;
                    }

                    if (! $validator->errors()->has("questions.{$index}.name") && in_array(mb_strtolower($question['name']), $existing, true)) {
                        $validator->errors()->add("questions.{$index}.name", __('That question already exists. Choose it from the list.'));
                    }

                    $choices = $question['choices'] ?? [];

                    if (($question['answer_type'] ?? null) === DetailAnswerType::Choice->value && (! is_array($choices) || count($choices) < 2)) {
                        $validator->errors()->add("questions.{$index}.choices", __('List at least two choices.'));
                    }
                }
            },
        ];
    }

    /**
     * The service's fields, in the shape ServiceAdminService writes. A service
     * that needs no stones keeps no stone types.
     *
     * @return array{name: string, service_type_id: int, description: string, requires_stones: bool, fulfillment: Fulfillment, is_active: bool, sort_order: int, features: list<string>, stone_type_ids: list<int>, questions: list<array{id: int|null, name: string|null, answer_type: DetailAnswerType|null, choices: list<string>, required: bool}>}
     */
    public function serviceData(): array
    {
        $requiresStones = $this->boolean('requires_stones');

        return [
            'name' => (string) $this->validated('name'),
            'service_type_id' => (int) $this->validated('service_type_id'),
            'description' => (string) $this->validated('description'),
            'requires_stones' => $requiresStones,
            'fulfillment' => Fulfillment::from((string) $this->validated('fulfillment')),
            'is_active' => $this->boolean('is_active'),
            'sort_order' => (int) $this->validated('sort_order'),
            'features' => array_values(array_map(fn (mixed $feature): string => (string) $feature, $this->validated('features') ?? [])),
            'stone_type_ids' => $requiresStones
                ? array_values(array_map(fn (mixed $id): int => (int) $id, $this->validated('stone_type_ids') ?? []))
                : [],
            'questions' => array_values(array_map(self::questionData(...), $this->validated('questions') ?? [])),
        ];
    }

    /**
     * One question row: one from the catalog by id, or a new one, whose
     * choices are kept only when it is answered from a list.
     *
     * @param  array<string, mixed>  $question
     * @return array{id: int|null, name: string|null, answer_type: DetailAnswerType|null, choices: list<string>, required: bool}
     */
    private static function questionData(array $question): array
    {
        $id = $question['id'] ?? null;

        if ($id !== null) {
            return ['id' => (int) $id, 'name' => null, 'answer_type' => null, 'choices' => [], 'required' => (bool) $question['required']];
        }

        $type = DetailAnswerType::from((string) $question['answer_type']);
        $choices = [];

        foreach ($type === DetailAnswerType::Choice ? ($question['choices'] ?? []) : [] as $choice) {
            $choices[] = (string) $choice;
        }

        return ['id' => null, 'name' => (string) $question['name'], 'answer_type' => $type, 'choices' => $choices, 'required' => (bool) $question['required']];
    }
}
