<?php

namespace App\Concerns;

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
 * A question is chosen from the Questions page's list (`id`), not archived;
 * new ones are written there (QuestionList), not on this form. Its limits
 * are kept here, as that page uses them.
 */
trait ServiceValidationRules
{
    public const DESCRIPTION_MAX = 500;

    /** The lines under the delivery method and the date (services.delivery_note, schedule_note). */
    public const NOTE_MAX = 500;

    /** The most days ahead a service may hold its date to: a year. */
    public const LEAD_DAYS_MAX = 365;

    public const FEATURES_MAX = 8;

    public const FEATURE_MAX = 150;

    public const QUESTION_NAME_MAX = 100;

    // Table base design lists every base a center, side or dining table may have, with Custom and Not sure.
    public const CHOICES_MAX = 20;

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
            'delivery_note' => ['nullable', 'string', 'max:'.self::NOTE_MAX],
            'schedule_note' => ['nullable', 'string', 'max:'.self::NOTE_MAX],
            'lead_days' => ['nullable', 'integer', 'min:0', 'max:'.self::LEAD_DAYS_MAX],
            'questions' => ['nullable', 'array'],
            'questions.*' => ['array:id,required'],
            'questions.*.id' => ['required', 'integer', 'distinct', Rule::exists('detail_fields', 'id')->whereNull('archived_at')],
            'questions.*.required' => ['required', 'boolean'],
            // An application (Flooring…) is asked under a service that has applications, so it cannot have its own.
            'is_application' => ['sometimes', 'boolean'],
            'has_applications' => ['sometimes', 'boolean', 'declined_if:is_application,true,1'],
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
            'delivery_note' => $text($this->input('delivery_note')),
            'schedule_note' => $text($this->input('schedule_note')),
            'features' => is_array($features) ? array_values(array_map($text, $features)) : $features,
            'stone_type_ids' => is_array($stoneTypes) ? array_values($stoneTypes) : $stoneTypes,
            'questions' => is_array($questions) ? array_values($questions) : $questions,
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
            'delivery_note.max' => __('The delivery line can be :max characters at most.'),
            'schedule_note.max' => __('The date line can be :max characters at most.'),
            'lead_days.integer' => __('Enter a whole number of days.'),
            'lead_days.min' => __('Enter 0 or more days.'),
            'lead_days.max' => __('Enter :max days or fewer.'),
            'questions.*.id.required' => __('Choose a question from the list.'),
            'questions.*.id.exists' => __('Choose from the questions listed.'),
            'questions.*.id.distinct' => __('That question is already asked.'),
            'questions.*.array' => __('Choose a question from the list.'),
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
        ];
    }

    /**
     * The service's fields, in the shape ServiceAdminService writes. A service
     * that needs no stones keeps no stone types.
     *
     * @return array{name: string, service_type_id: int, description: string, requires_stones: bool, fulfillment: Fulfillment, delivery_note: string|null, schedule_note: string|null, lead_days: int|null, is_application: bool, has_applications: bool, is_active: bool, sort_order: int, features: list<string>, stone_type_ids: list<int>, questions: list<array{id: int, required: bool}>}
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
            // An installed service asks no delivery method, so it has no line under one.
            'delivery_note' => filled($this->validated('delivery_note')) && $this->validated('fulfillment') !== Fulfillment::Installed->value ? (string) $this->validated('delivery_note') : null,
            'schedule_note' => filled($this->validated('schedule_note')) ? (string) $this->validated('schedule_note') : null,
            'lead_days' => filled($this->validated('lead_days')) && (int) $this->validated('lead_days') > 0 ? (int) $this->validated('lead_days') : null,
            // Left out, a service keeps what it is.
            'is_application' => $this->boolean('is_application', $this->service()->is_application ?? false),
            'has_applications' => $this->boolean('has_applications', $this->service()->has_applications ?? false),
            'is_active' => $this->boolean('is_active'),
            'sort_order' => (int) $this->validated('sort_order'),
            'features' => array_values(array_map(fn (mixed $feature): string => (string) $feature, $this->validated('features') ?? [])),
            'stone_type_ids' => $requiresStones
                ? array_values(array_map(fn (mixed $id): int => (int) $id, $this->validated('stone_type_ids') ?? []))
                : [],
            'questions' => array_values(array_map(
                fn (array $question): array => ['id' => (int) $question['id'], 'required' => (bool) $question['required']],
                $this->validated('questions') ?? [],
            )),
        ];
    }
}
