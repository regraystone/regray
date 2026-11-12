<?php

namespace App\Http\Requests\Inquiry;

use App\Enums\DeliveryMethod;
use App\Enums\DetailAnswerType;
use App\Enums\DetailFieldScope;
use App\Models\DetailField;
use App\Models\EdgeProfile;
use App\Models\Finish;
use App\Models\Service;
use App\Models\Stone;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Mirrors `inquirySchema` in resources/js/lib/inquiry-schema.ts, field for
 * field: the keys are the form's own (`serviceId`, `stones.*.stoneId`,
 * `neededBy`), so an error at `projects.0.stones.1.quantity` is the same path
 * the page already knows how to point at.
 *
 * What else a project is asked depends on its service
 * (`service_detail_fields`), so those rules are built per project from the
 * service it names: a stone row answers only the stone-scope questions its
 * service lists (`quantity`, `slabSize`, `edge`, `finish`) and must leave out
 * the rest (only the slab services ask how many pieces),
 * and the project-scope answers arrive as `answers.{slug}`, each checked
 * against its answer type, whether it is required and, for a closed list, its
 * options. A suggestion is never checked: a free entry is accepted only where
 * the question allows one, and then only by its type. A service that asks for
 * no stones (Installation Only) takes none, and no Help me choose either. The
 * messages are worded from the question's name, as the page words them.
 *
 * @phpstan-import-type ProjectInput from \App\Services\InquiryService
 */
class StoreInquiryRequest extends FormRequest
{
    /** The largest number an answer's `number_value` holds (decimal 12,2). */
    private const NUMBER_MAX = '9999999999.99';

    /** The longest free text an answer's `text_value` holds. */
    private const TEXT_MAX = 255;

    /** @var Collection<string, Service>|null */
    private ?Collection $services = null;

    /** @var Collection<int, DetailField>|null */
    private ?Collection $questions = null;

    /** The route middleware decides access before this request is resolved. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $limits = config('inquiry.limits');
        $rules = [
            'projects' => ['required', 'array', 'min:1', 'max:'.$limits['projects']],
            'projects.*.name' => ['required', 'string', 'max:'.$limits['name']],

            // Whether it is still offered is after()'s, since an edit may keep its own.
            'projects.*.serviceId' => ['required', 'string', Rule::exists('services', 'slug')],

            'projects.*.stoneAdvice' => ['nullable', 'boolean'],
            'projects.*.stones' => ['nullable', 'array', 'max:'.$limits['stone_rows']],
            'projects.*.stones.*.stoneId' => [
                'required', 'string',
                Rule::exists('stones', 'slug')->where('is_active', true)->whereNull('deleted_at'),
            ],
            'projects.*.answers' => ['nullable', 'array'],

            'projects.*.deliveryMethod' => ['required', Rule::enum(DeliveryMethod::class)],
            'projects.*.location' => ['nullable', 'required_if:projects.*.deliveryMethod,'.DeliveryMethod::Delivery->value, 'string', Rule::in(config('inquiry.locations'))],
            'projects.*.neededBy' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'projects.*.notes' => ['nullable', 'string', 'max:'.$limits['notes']],

            'projects.*.attachments' => ['nullable', 'array', 'max:'.$limits['files']],
            'projects.*.attachments.*' => [
                'file',
                'mimes:'.implode(',', $limits['file_extensions']),
                'max:'.$limits['file_kilobytes'],
            ],
        ];

        foreach ($this->projectsInput() as $index => $project) {
            $service = $this->serviceOf($project);

            if ($service !== null) {
                $rules = [...$rules, ...$this->serviceRules("projects.{$index}", $service)];
            }
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        $limits = config('inquiry.limits');
        $messages = [
            'projects.required' => 'Add at least one project.',
            'projects.min' => 'Add at least one project.',
            'projects.max' => 'An inquiry can only have one project.',
            'projects.*.name.required' => 'Give this project a name.',
            'projects.*.name.max' => "Keep the project name to {$limits['name']} characters or fewer.",
            'projects.*.serviceId.required' => 'Choose what you need.',
            'projects.*.serviceId.exists' => 'That choice is no longer available.',
            'projects.*.stones.max' => "A project can have up to {$limits['stone_rows']} stone rows.",
            'projects.*.stones.prohibited' => 'This service needs no stones.',
            'projects.*.stones.*.stoneId.exists' => 'That stone is no longer available.',
            'projects.*.stones.*.quantity.required' => 'Enter how many pieces you need.',
            'projects.*.stones.*.quantity.integer' => 'Quantity has to be a whole number of pieces.',
            'projects.*.stones.*.quantity.min' => "Enter at least {$limits['quantity_min']} piece.",
            'projects.*.stones.*.quantity.max' => "Enter {$limits['quantity_max']} pieces or fewer.",
            'projects.*.stones.*.quantity.prohibited' => 'This service does not ask how many pieces.',
            'projects.*.answers.array' => 'This service does not ask that.',
            'projects.*.answers.prohibited' => 'This service does not ask that.',
            'projects.*.deliveryMethod.required' => 'Choose how you would like to get it.',
            'projects.*.deliveryMethod.Illuminate\Validation\Rules\Enum' => 'Choose how you would like to get it.',
            'projects.*.location.required_if' => 'Choose where the site is.',
            'projects.*.location.in' => 'Choose a city or municipality from the list.',
            'projects.*.neededBy.after_or_equal' => 'Target deadline cannot be in the past.',
            'projects.*.notes.max' => "Keep the notes to {$limits['notes']} characters or fewer.",
            'projects.*.attachments.max' => "You can attach up to {$limits['files']} files.",
            'projects.*.attachments.*.mimes' => 'Attachments must be PDF, PNG, JPG or JPEG.',
            'projects.*.attachments.*.max' => 'Attachments must be 10 MB or smaller.',
        ];

        // How many pieces keeps its own words, above.
        foreach ($this->questions()->reject(fn (DetailField $field): bool => $field->slug === DetailField::QUANTITY) as $field) {
            $key = $field->scope === DetailFieldScope::Stone
                ? 'projects.*.stones.*.'.$field->stoneInputKey()
                : "projects.*.answers.{$field->slug}";

            foreach ($this->questionMessages($field) as $rule => $message) {
                $messages["{$key}.{$rule}"] = $message;
            }
        }

        return $messages;
    }

    /**
     * The rules that depend on what the project is for.
     *
     * A project needs a stone, or a stone left to Regray to recommend, unless
     * its service asks for none; then it may not ask for a recommendation
     * either. A service no longer offered, or whose service type is not, is
     * refused unless this inquiry already has it (heldServiceSlugs()).
     *
     * Every stone has to be offered for the project's service: its primary type
     * linked to the service. The error is on the stone's row. This holds for an
     * edit too, for the stones the inquiry already has as much as for new ones,
     * since the page removes the stones that no longer fit before it sends.
     *
     * An answer that means "same as the stone" (`links_to_stone`) needs a stone
     * row to be the same as, so it is refused while Regray is to recommend one.
     *
     * Every project needs a date, because sending the inquiry books it as a
     * schedule, for 10:00, when the showroom opens.
     *
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $projects = $this->projectsInput();
                $held = $this->heldServiceSlugs();
                $stoneTypeIds = $this->stoneTypeIdsFor($projects);

                foreach ($projects as $index => $project) {
                    /** @var Service $service */
                    $service = $this->serviceOf($project);
                    $asksForAdvice = filter_var($project['stoneAdvice'] ?? false, FILTER_VALIDATE_BOOLEAN);

                    if (! $service->isOffered() && ! in_array($service->slug, $held, true)) {
                        $validator->errors()->add("projects.{$index}.serviceId", 'That choice is no longer available.');
                    }

                    $linked = $service->stoneTypes->modelKeys();

                    foreach ($project['stones'] ?? [] as $row => $stone) {
                        if (! in_array($stoneTypeIds[$stone['stoneId']] ?? null, $linked, true)) {
                            $validator->errors()->add("projects.{$index}.stones.{$row}.stoneId", 'That stone is not offered for this service.');
                        }
                    }

                    if (empty($project['neededBy'])) {
                        $validator->errors()->add("projects.{$index}.neededBy", 'Say when this is needed.');
                    }

                    if (! $service->requires_stones && $asksForAdvice) {
                        $validator->errors()->add("projects.{$index}.stoneAdvice", 'This service needs no stones.');
                    }

                    if ($service->requires_stones && empty($project['stones']) && ! $asksForAdvice) {
                        $validator->errors()->add("projects.{$index}.stones", 'Select at least one stone.');
                    }

                    foreach ($this->projectQuestions($service) as $field) {
                        $option = $field->closedOptions()->firstWhere('slug', $project['answers'][$field->slug] ?? null);

                        if ($option?->links_to_stone && empty($project['stones'])) {
                            $validator->errors()->add("projects.{$index}.answers.{$field->slug}", "{$option->name} needs a stone. Choose another ".Str::lower($field->name).'.');
                        }
                    }
                }
            },
        ];
    }

    /**
     * The services a project may keep although they are no longer offered: an edit
     * answers with the ones its inquiry already has. A new inquiry has none.
     *
     * @return list<string>
     */
    protected function heldServiceSlugs(): array
    {
        return [];
    }

    /**
     * The projects as sent, before validation has had its say: only the
     * entries that are arrays, by their index.
     *
     * @return array<int, array<string, mixed>>
     */
    private function projectsInput(): array
    {
        $projects = $this->input('projects', []);

        return is_array($projects) ? array_filter($projects, 'is_array') : [];
    }

    /**
     * The service a project names, with what its rules need, or null when it
     * names none that exists.
     *
     * @param  array<string, mixed>  $project
     */
    private function serviceOf(array $project): ?Service
    {
        $this->services ??= Service::query()
            ->with(['serviceType', 'stoneTypes:stone_types.id', 'detailFields.options'])
            ->whereIn('slug', array_values(array_unique(array_filter(
                array_column($this->projectsInput(), 'serviceId'),
                'is_string',
            ))))
            ->get()
            ->keyBy('slug');

        $slug = $project['serviceId'] ?? null;

        return is_string($slug) ? $this->services->get($slug) : null;
    }

    /**
     * Every question a service can ask, for the messages.
     *
     * @return Collection<int, DetailField>
     */
    private function questions(): Collection
    {
        return $this->questions ??= DetailField::query()->orderBy('sort_order')->get();
    }

    /**
     * The project-scope questions a service asks, in order.
     *
     * @return Collection<int, DetailField>
     */
    private function projectQuestions(Service $service): Collection
    {
        return $service->detailFields
            ->filter(fn (DetailField $field): bool => $field->scope === DetailFieldScope::Project)
            ->values();
    }

    /**
     * The rules a project's service adds: which stone-row answers it takes,
     * whether it takes stones at all, and its project answers.
     *
     * @return array<string, array<int, mixed>>
     */
    private function serviceRules(string $prefix, Service $service): array
    {
        $rules = [];
        $asked = $service->detailFields->keyBy('slug');

        if (! $service->requires_stones) {
            $rules["{$prefix}.stones"] = ['prohibited'];
        }

        foreach (DetailField::STONE_INPUT_KEYS as $slug => $key) {
            $field = $asked->get($slug);

            $rules["{$prefix}.stones.*.{$key}"] = $field === null
                ? ['prohibited']
                : [$field->isRequiredByService() ? 'required' : 'nullable', ...$this->answerRules($field)];
        }

        $questions = $this->projectQuestions($service);

        $rules["{$prefix}.answers"] = $questions->isEmpty()
            ? ['prohibited']
            : ['nullable', 'array:'.$questions->pluck('slug')->implode(',')];

        foreach ($questions as $field) {
            $rules["{$prefix}.answers.{$field->slug}"] = [$field->isRequiredByService() ? 'required' : 'nullable', ...$this->answerRules($field)];
        }

        return $rules;
    }

    /**
     * What one answer has to be, by its question's answer type. A closed list
     * is checked against its active options (edge profile and finish against
     * their own tables, by name); a free entry only by its type.
     *
     * @return list<mixed>
     */
    private function answerRules(DetailField $field): array
    {
        if ($field->slug === DetailField::QUANTITY) {
            $limits = config('inquiry.limits');

            return ['integer', 'min:'.$limits['quantity_min'], 'max:'.$limits['quantity_max']];
        }

        if ($field->answersFromCatalog()) {
            $table = $field->slug === DetailField::EDGE_PROFILE ? 'edge_profiles' : 'finishes';

            return ['string', Rule::exists($table, 'name')->where('is_active', true)];
        }

        $listed = $field->allows_custom ? [] : [Rule::in($field->suggestions()->pluck('name')->all())];

        return match ($field->answer_type) {
            DetailAnswerType::Text => ['string', 'max:'.$this->textMax($field), ...$listed],
            DetailAnswerType::Number => ['numeric', 'gt:0', 'max:'.self::NUMBER_MAX, ...$listed],
            DetailAnswerType::Choice => ['string', Rule::in($field->closedOptions()->pluck('slug')->all())],
            DetailAnswerType::Boolean => ['boolean'],
        };
    }

    /**
     * What the page says when an answer is refused, worded from the
     * question's name. inquiry-schema.ts words them the same way.
     *
     * @return array<string, string>
     */
    private function questionMessages(DetailField $field): array
    {
        $name = Str::lower($field->name);
        $fromList = "Choose the {$name} from the list.";
        $number = 'Enter a number greater than 0.';

        return [
            'required' => in_array($field->answer_type, [DetailAnswerType::Text, DetailAnswerType::Number], true)
                ? "Enter the {$name}."
                : "Choose the {$name}.",
            'prohibited' => "This service does not ask for the {$name}.",
            'string' => $field->answer_type === DetailAnswerType::Text ? "Enter the {$name}." : $fromList,
            'max' => $field->answer_type === DetailAnswerType::Number
                ? 'Enter a smaller number.'
                : "Keep the {$name} to {$this->textMax($field)} characters or fewer.",
            'numeric' => $number,
            'gt' => $number,
            'in' => $fromList,
            'exists' => $fromList,
            'boolean' => 'Choose yes or no.',
        ];
    }

    /** The longest free text an answer may be: a slab size is stored in 50. */
    private function textMax(DetailField $field): int
    {
        return $field->slug === DetailField::SLAB_SIZE ? (int) config('inquiry.limits.slab_size') : self::TEXT_MAX;
    }

    /**
     * The primary stone type of every stone the projects name, by slug.
     *
     * @param  array<int, array<string, mixed>>  $projects
     * @return array<string, int>
     */
    private function stoneTypeIdsFor(array $projects): array
    {
        $slugs = [];

        foreach ($projects as $project) {
            foreach ($project['stones'] ?? [] as $stone) {
                $slugs[] = $stone['stoneId'];
            }
        }

        /** @var array<string, int> */
        return Stone::query()->whereIn('slug', array_unique($slugs))->pluck('stone_type_id', 'slug')->all();
    }

    /**
     * The validated request in the shape the service writes: slugs and names
     * already turned into ids, a stone row holding only the answers its
     * service asks, the project answers as their rows, and the files collected
     * under each project.
     *
     * @return array{projects: list<ProjectInput>}
     */
    public function inquiryInput(): array
    {
        /** @var list<array<string, mixed>> $projects */
        $projects = array_values($this->validated('projects'));

        $stoneSlugs = [];

        foreach ($projects as $project) {
            foreach ($project['stones'] ?? [] as $stone) {
                $stoneSlugs[] = $stone['stoneId'];
            }
        }

        $stoneIds = Stone::query()->withTrashed()->whereIn('slug', array_unique($stoneSlugs))->pluck('id', 'slug');
        $edgeProfileIds = EdgeProfile::query()->pluck('id', 'name');
        $finishIds = Finish::query()->pluck('id', 'name');
        $quantityLimit = (int) config('inquiry.limits.quantity_max');

        $input = [];

        foreach ($projects as $project) {
            /** @var Service $service */
            $service = $this->serviceOf($project);
            $asked = $service->detailFields->keyBy('slug');
            $deliveryMethod = DeliveryMethod::from($project['deliveryMethod']);
            $isDelivery = $deliveryMethod === DeliveryMethod::Delivery;

            $stones = [];

            // A stone holds one row per set of answers, so a repeated row adds to it.
            foreach ($project['stones'] ?? [] as $stone) {
                $slabSize = $asked->has(DetailField::SLAB_SIZE) && filled($stone['slabSize'] ?? null) ? trim($stone['slabSize']) : null;
                $edgeProfileId = $asked->has(DetailField::EDGE_PROFILE) && filled($stone['edge'] ?? null) ? $edgeProfileIds[$stone['edge']] : null;
                $finishId = $asked->has(DetailField::FINISH) && filled($stone['finish'] ?? null) ? $finishIds[$stone['finish']] : null;
                $row = "{$stone['stoneId']}::{$edgeProfileId}::{$finishId}::{$slabSize}";

                $stones[$row] = [
                    'stone_id' => $stoneIds[$stone['stoneId']],
                    'edge_profile_id' => $edgeProfileId,
                    'finish_id' => $finishId,
                    // Only the slab services ask how many pieces; elsewhere the area says how much.
                    'quantity' => $asked->has(DetailField::QUANTITY)
                        ? min(($stones[$row]['quantity'] ?? 0) + (int) $stone['quantity'], $quantityLimit)
                        : null,
                    'slab_size' => $slabSize,
                ];
            }

            $answers = [];

            foreach ($this->projectQuestions($service) as $field) {
                $value = $project['answers'][$field->slug] ?? null;

                if ($value !== null && $value !== '') {
                    $answers[] = $field->answerAttributes($value);
                }
            }

            $files = [];

            foreach ($project['attachments'] ?? [] as $file) {
                if ($file instanceof UploadedFile) {
                    $files[] = $file;
                }
            }

            $input[] = [
                'name' => $project['name'],
                'service_id' => $service->id,
                'stones' => array_values($stones),
                'answers' => $answers,
                'delivery_method' => $deliveryMethod,
                // A pickup has no site to name.
                'location' => $isDelivery ? $project['location'] : null,
                'deadline_date' => $project['neededBy'],
                'notes' => $project['notes'] ?? null,
                // Choosing a stone answers the question, so the two never stand together.
                'stone_advice_requested' => $service->requires_stones && $stones === [] && filter_var($project['stoneAdvice'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'files' => $files,
            ];
        }

        return ['projects' => $input];
    }
}
