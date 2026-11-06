<?php

namespace App\Http\Requests\Inquiry;

use App\Enums\DeliveryMethod;
use App\Models\EdgeProfile;
use App\Models\Finish;
use App\Models\FurnitureType;
use App\Models\Service;
use App\Models\Stone;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Mirrors `inquirySchema` in resources/js/lib/inquiry-schema.ts, field for
 * field: the keys are the form's own (`work.*.serviceId`, `stones.*.stoneId`,
 * `neededBy`), so an error at `projects.0.stones.1.quantity` is the same path
 * the page already knows how to point at.
 *
 * @phpstan-import-type ProjectInput from \App\Services\InquiryService
 */
class StoreInquiryRequest extends FormRequest
{
    /** The route middleware decides access before this request is resolved. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $limits = config('inquiry.limits');

        return [
            'projects' => ['required', 'array', 'min:1', 'max:'.$limits['projects']],
            'projects.*.name' => ['required', 'string', 'max:'.$limits['name']],

            // What is wanted of each stone; after() checks they fit together.
            'projects.*.work' => ['required', 'array', 'min:1'],
            'projects.*.work.*.stoneId' => ['nullable', 'string', Rule::exists('stones', 'slug')],
            'projects.*.work.*.serviceId' => ['required', 'string', Rule::exists('services', 'slug')],
            'projects.*.work.*.furnitureType' => ['nullable', 'string', Rule::exists('furniture_types', 'slug')->where('is_active', true)],

            'projects.*.stoneAdvice' => ['nullable', 'boolean'],
            'projects.*.stones' => ['nullable', 'array', 'max:'.$limits['stone_rows']],
            'projects.*.stones.*.stoneId' => [
                'required', 'string',
                Rule::exists('stones', 'slug')->where('is_active', true)->whereNull('deleted_at'),
            ],
            'projects.*.stones.*.quantity' => ['required', 'integer', 'min:'.$limits['quantity_min'], 'max:'.$limits['quantity_max']],
            'projects.*.stones.*.edge' => ['required', 'string', Rule::exists('edge_profiles', 'name')->where('is_active', true)],
            'projects.*.stones.*.finish' => ['required', 'string', Rule::exists('finishes', 'name')->where('is_active', true)],

            'projects.*.deliveryMethod' => ['required', Rule::enum(DeliveryMethod::class)],
            'projects.*.location' => ['nullable', 'required_if:projects.*.deliveryMethod,'.DeliveryMethod::Delivery->value, 'string', Rule::in(config('inquiry.locations'))],
            'projects.*.landmark' => ['nullable', 'string', 'max:'.$limits['landmark']],
            'projects.*.areaSqm' => ['nullable', 'numeric', 'gt:0', 'max:99999999.99'],
            'projects.*.neededBy' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'projects.*.notes' => ['nullable', 'string', 'max:'.$limits['notes']],

            'projects.*.attachments' => ['nullable', 'array', 'max:'.$limits['files']],
            'projects.*.attachments.*' => [
                'file',
                'mimes:'.implode(',', $limits['file_extensions']),
                'max:'.$limits['file_kilobytes'],
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        $limits = config('inquiry.limits');

        return [
            'projects.required' => 'Add at least one project.',
            'projects.min' => 'Add at least one project.',
            'projects.max' => 'An inquiry can only have one project.',
            'projects.*.name.required' => 'Give this project a name.',
            'projects.*.name.max' => "Keep the project name to {$limits['name']} characters or fewer.",
            'projects.*.work.required' => 'Choose the work for each stone.',
            'projects.*.work.min' => 'Choose the work for each stone.',
            'projects.*.work.*.stoneId.exists' => 'That stone is no longer available.',
            'projects.*.work.*.serviceId.required' => 'Choose the work for each stone.',
            'projects.*.work.*.serviceId.exists' => 'That choice is no longer available.',
            'projects.*.work.*.furnitureType.exists' => 'That furniture type is no longer available.',
            'projects.*.stones.max' => "A project can have up to {$limits['stone_rows']} stone rows.",
            'projects.*.stones.*.stoneId.exists' => 'That stone is no longer available.',
            'projects.*.stones.*.quantity.required' => 'Enter how many pieces you need.',
            'projects.*.stones.*.quantity.integer' => 'Quantity has to be a whole number of pieces.',
            'projects.*.stones.*.quantity.min' => "Enter at least {$limits['quantity_min']} piece.",
            'projects.*.stones.*.quantity.max' => "Enter {$limits['quantity_max']} pieces or fewer.",
            'projects.*.stones.*.edge.required' => 'Choose an edge profile.',
            'projects.*.stones.*.edge.exists' => 'Choose an edge profile.',
            'projects.*.stones.*.finish.required' => 'Choose a finish.',
            'projects.*.stones.*.finish.exists' => 'Choose a finish.',
            'projects.*.deliveryMethod.required' => 'Choose how you would like to get it.',
            'projects.*.deliveryMethod.Illuminate\Validation\Rules\Enum' => 'Choose how you would like to get it.',
            'projects.*.location.required_if' => 'Choose where the site is.',
            'projects.*.location.in' => 'Choose a city or municipality from the list.',
            'projects.*.landmark.max' => "Keep the landmark to {$limits['landmark']} characters or fewer.",
            'projects.*.areaSqm.numeric' => 'Enter an area greater than 0.',
            'projects.*.areaSqm.gt' => 'Enter an area greater than 0.',
            'projects.*.neededBy.after_or_equal' => 'Target deadline cannot be in the past.',
            'projects.*.notes.max' => "Keep the notes to {$limits['notes']} characters or fewer.",
            'projects.*.attachments.max' => "You can attach up to {$limits['files']} files.",
            'projects.*.attachments.*.mimes' => 'Attachments must be PDF, PNG, JPG or JPEG.',
            'projects.*.attachments.*.max' => 'Attachments must be 10 MB or smaller.',
        ];
    }

    /**
     * The rules that depend on what the project is for.
     *
     * A project needs one service per stone, or a stone left to Regray to recommend
     * and the service for that. A service that needs a furniture type needs one. A
     * service no longer offered is refused, unless this inquiry already has it
     * (heldServiceSlugs()).
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

                /** @var array<int, array<string, mixed>> $projects */
                $projects = $this->input('projects', []);
                $services = $this->servicesFor($projects);
                $held = $this->heldServiceSlugs();

                foreach ($projects as $index => $project) {
                    foreach ($project['work'] as $row => $work) {
                        /** @var Service $service */
                        $service = $services[$work['serviceId']];

                        if (! $service->is_active && ! in_array($service->slug, $held, true)) {
                            $validator->errors()->add("projects.{$index}.work.{$row}.serviceId", 'That choice is no longer available.');
                        }

                        if ($service->needs_furniture && empty($work['furnitureType'])) {
                            $validator->errors()->add("projects.{$index}.work.{$row}.furnitureType", 'Choose the furniture type.');
                        }
                    }

                    if (empty($project['neededBy'])) {
                        $validator->errors()->add("projects.{$index}.neededBy", 'Say when this is needed.');
                    }

                    $this->requireWorkForEachStone($validator, $index, $project);
                    $this->rejectRepeatedStones($validator, $index, $project['stones'] ?? []);
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
     * Every service the projects name, by slug.
     *
     * @param  array<int, array<string, mixed>>  $projects
     * @return Collection<string, Service>
     */
    private function servicesFor(array $projects): Collection
    {
        $slugs = [];

        foreach ($projects as $project) {
            foreach ($project['work'] as $work) {
                $slugs[] = $work['serviceId'];
            }
        }

        return Service::query()
            ->whereIn('slug', array_unique($slugs))
            ->get()
            ->keyBy('slug');
    }

    /**
     * Each stone has exactly one service, and work is only for its stones. A
     * stone left to Regray has one row with no stone.
     *
     * @param  array<string, mixed>  $project
     */
    private function requireWorkForEachStone(Validator $validator, int $index, array $project): void
    {
        $stones = array_values(array_unique(array_column($project['stones'] ?? [], 'stoneId')));
        $advice = $stones === [] && filter_var($project['stoneAdvice'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($stones === [] && ! $advice) {
            $validator->errors()->add("projects.{$index}.stones", 'Select at least one stone.');

            return;
        }

        $worked = array_map(fn (array $work): ?string => $work['stoneId'] ?? null, $project['work']);
        $expected = $advice ? [null] : $stones;

        sort($worked);
        sort($expected);

        if ($worked !== $expected) {
            $validator->errors()->add("projects.{$index}.work", 'Choose the work for each stone.');
        }
    }

    /**
     * The same stone may repeat only with a different edge or finish.
     *
     * @param  list<array{stoneId: string, edge: string, finish: string}>  $rows
     */
    private function rejectRepeatedStones(Validator $validator, int $index, array $rows): void
    {
        $seen = [];

        foreach ($rows as $row => $stone) {
            $key = "{$stone['stoneId']}::{$stone['edge']}::{$stone['finish']}";

            if (isset($seen[$key])) {
                $validator->errors()->add(
                    "projects.{$index}.stones.{$row}.finish",
                    'Another row of this stone has the same edge profile and finish. Change one of them or remove this row.',
                );

                continue;
            }

            $seen[$key] = true;
        }
    }

    /**
     * The validated request in the shape the service writes: slugs and names
     * already turned into ids, and the files collected under each project.
     *
     * @return array{projects: list<ProjectInput>}
     */
    public function inquiryInput(): array
    {
        /** @var list<array<string, mixed>> $projects */
        $projects = array_values($this->validated('projects'));

        $services = $this->servicesFor($projects);
        $stoneSlugs = [];

        foreach ($projects as $project) {
            foreach ($project['stones'] ?? [] as $stone) {
                $stoneSlugs[] = $stone['stoneId'];
            }

            foreach ($project['work'] as $work) {
                if (! empty($work['stoneId'])) {
                    $stoneSlugs[] = $work['stoneId'];
                }
            }
        }

        $stoneIds = Stone::query()->withTrashed()->whereIn('slug', array_unique($stoneSlugs))->pluck('id', 'slug');
        $edgeProfileIds = EdgeProfile::query()->pluck('id', 'name');
        $finishIds = Finish::query()->pluck('id', 'name');
        $furnitureTypeIds = FurnitureType::query()->pluck('id', 'slug');

        $input = [];

        foreach ($projects as $project) {
            $work = [];

            foreach ($project['work'] as $row) {
                /** @var Service $service */
                $service = $services[$row['serviceId']];

                $work[] = [
                    'stone_id' => empty($row['stoneId']) ? null : $stoneIds[$row['stoneId']],
                    'service_id' => $service->id,
                    // Only the work that asks for a furniture type keeps one.
                    'furniture_type_id' => $service->needs_furniture ? $furnitureTypeIds[$row['furnitureType']] : null,
                ];
            }

            $deliveryMethod = DeliveryMethod::from($project['deliveryMethod']);
            $isDelivery = $deliveryMethod === DeliveryMethod::Delivery;

            $stones = [];

            foreach ($project['stones'] ?? [] as $stone) {
                $stones[] = [
                    'stone_id' => $stoneIds[$stone['stoneId']],
                    'edge_profile_id' => $edgeProfileIds[$stone['edge']],
                    'finish_id' => $finishIds[$stone['finish']],
                    'quantity' => (int) $stone['quantity'],
                ];
            }

            $files = [];

            foreach ($project['attachments'] ?? [] as $file) {
                if ($file instanceof UploadedFile) {
                    $files[] = $file;
                }
            }

            $input[] = [
                'name' => $project['name'],
                'work' => $work,
                'stones' => $stones,
                'delivery_method' => $deliveryMethod,
                // A pickup has no site to name.
                'location' => $isDelivery ? $project['location'] : null,
                'landmark' => $project['landmark'] ?? null,
                'area_sqm' => isset($project['areaSqm']) ? (float) $project['areaSqm'] : null,
                'deadline_date' => $project['neededBy'],
                'notes' => $project['notes'] ?? null,
                // Choosing a stone answers the question, so the two never stand together.
                'stone_advice_requested' => $stones === [] && filter_var($project['stoneAdvice'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'files' => $files,
            ];
        }

        return ['projects' => $input];
    }
}
