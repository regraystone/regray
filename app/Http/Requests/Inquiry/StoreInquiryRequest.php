<?php

namespace App\Http\Requests\Inquiry;

use App\Models\EdgeProfile;
use App\Models\Finish;
use App\Models\ProjectCategory;
use App\Models\Service;
use App\Models\Stone;
use App\Services\ScheduleService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Mirrors `inquirySchema` in resources/js/lib/inquiry-schema.ts, field for
 * field: the keys are the form's own (`serviceIds`, `stones.*.stoneId`,
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

            'projects.*.serviceIds' => ['required', 'array', 'min:1'],
            'projects.*.serviceIds.*' => [
                'required', 'string',
                Rule::exists('services', 'slug')->where('is_active', true)->whereNull('deleted_at'),
            ],

            'projects.*.applicationIds' => ['nullable', 'array'],
            'projects.*.applicationIds.*' => ['string', Rule::exists('project_categories', 'slug')->where('is_active', true)],

            'projects.*.stones' => ['nullable', 'array', 'max:'.$limits['stone_rows']],
            'projects.*.stones.*.stoneId' => [
                'required', 'string',
                Rule::exists('stones', 'slug')->where('is_active', true)->whereNull('deleted_at'),
            ],
            'projects.*.stones.*.quantity' => ['required', 'integer', 'min:'.$limits['quantity_min'], 'max:'.$limits['quantity_max']],
            'projects.*.stones.*.edge' => ['required', 'string', Rule::exists('edge_profiles', 'name')->where('is_active', true)],
            'projects.*.stones.*.finish' => ['required', 'string', Rule::exists('finishes', 'name')->where('is_active', true)],

            'projects.*.location' => ['required', 'string', Rule::in(config('inquiry.locations'))],
            'projects.*.landmark' => ['nullable', 'string', 'max:'.$limits['landmark']],
            'projects.*.areaSqm' => ['nullable', 'numeric', 'gt:0', 'max:99999999.99'],
            'projects.*.neededBy' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'projects.*.neededByTime' => ['nullable', 'date_format:H:i'],
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
            'projects.max' => "An inquiry can hold up to {$limits['projects']} projects.",
            'projects.*.name.required' => 'Give this project a name.',
            'projects.*.name.max' => "Keep the project name to {$limits['name']} characters or fewer.",
            'projects.*.serviceIds.required' => 'Select at least one service.',
            'projects.*.serviceIds.min' => 'Select at least one service.',
            'projects.*.serviceIds.*.exists' => 'That service is no longer available.',
            'projects.*.applicationIds.*.exists' => 'That application is no longer available.',
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
            'projects.*.location.required' => 'Choose where the site is.',
            'projects.*.location.in' => 'Choose a city or municipality from the list.',
            'projects.*.landmark.max' => "Keep the landmark to {$limits['landmark']} characters or fewer.",
            'projects.*.areaSqm.numeric' => 'Enter an area greater than 0.',
            'projects.*.areaSqm.gt' => 'Enter an area greater than 0.',
            'projects.*.neededBy.after_or_equal' => 'Target deadline cannot be in the past.',
            'projects.*.neededByTime.date_format' => 'Enter a time as hours and minutes.',
            'projects.*.notes.max' => "Keep the notes to {$limits['notes']} characters or fewer.",
            'projects.*.attachments.max' => "You can attach up to {$limits['files']} files.",
            'projects.*.attachments.*.mimes' => 'Attachments must be PDF, PNG, JPG or JPEG.',
            'projects.*.attachments.*.max' => 'Attachments must be 10 MB or smaller.',
        ];
    }

    /**
     * The rules that depend on what the project is for.
     *
     * A project with any service that supplies material needs stones and
     * applications. A project of nothing but standalone services (a site visit)
     * needs neither: whatever was picked is extra information. It is attended
     * at an hour, so it needs a time too, still ahead.
     *
     * Every project needs a date, because sending the inquiry books it as a
     * schedule. A project without a time is booked for 10:00, when the showroom opens.
     */
    /**
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

                $slugs = [];

                foreach ($projects as $project) {
                    array_push($slugs, ...$project['serviceIds']);
                }

                $standaloneBySlug = Service::query()
                    ->whereIn('slug', array_unique($slugs))
                    ->pluck('is_standalone', 'slug');

                foreach ($projects as $index => $project) {
                    $standaloneOnly = true;

                    foreach ($project['serviceIds'] as $slug) {
                        if (! $standaloneBySlug->get($slug)) {
                            $standaloneOnly = false;
                        }
                    }

                    $this->rejectRepeats($validator, "projects.{$index}.serviceIds", $project['serviceIds'], 'A service can only appear once in a project.');
                    $this->rejectRepeats($validator, "projects.{$index}.applicationIds", $project['applicationIds'] ?? [], 'An application can only appear once in a project.');

                    if (empty($project['neededBy'])) {
                        $validator->errors()->add("projects.{$index}.neededBy", 'Say when this is needed.');
                    }

                    if ($standaloneOnly) {
                        $this->requireVisitTime($validator, $index, $project);
                    } else {
                        if (empty($project['stones'])) {
                            $validator->errors()->add("projects.{$index}.stones", 'Select at least one stone.');
                        }

                        if (empty($project['applicationIds'])) {
                            $validator->errors()->add("projects.{$index}.applicationIds", 'Select at least one application.');
                        }
                    }

                    $this->rejectRepeatedStones($validator, $index, $project['stones'] ?? []);
                }
            },
        ];
    }

    /**
     * `distinct` would compare across every project at once, which is not the
     * rule: two projects may well want the same service.
     *
     * @param  list<mixed>  $values
     */
    private function rejectRepeats(Validator $validator, string $key, array $values, string $message): void
    {
        foreach (array_keys(array_diff_key($values, array_unique($values))) as $position) {
            $validator->errors()->add("{$key}.{$position}", $message);
        }
    }

    /**
     * A visit needs a time that is still ahead. The date and time are read in
     * the studio's zone, the same way the schedule they become is.
     *
     * @param  array<string, mixed>  $project
     */
    private function requireVisitTime(Validator $validator, int $index, array $project): void
    {
        $date = $project['neededBy'] ?? null;
        $time = $project['neededByTime'] ?? null;

        if (! is_string($time)) {
            $validator->errors()->add("projects.{$index}.neededByTime", 'Say what time of day works.');

            return;
        }

        if (is_string($date) && ScheduleService::arrivalAt($date, $time)->lessThanOrEqualTo(Date::now())) {
            $validator->errors()->add("projects.{$index}.neededByTime", 'That time has already passed.');
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

        $serviceSlugs = [];
        $stoneSlugs = [];
        $applicationIds = [];

        foreach ($projects as $project) {
            array_push($serviceSlugs, ...$project['serviceIds']);
            array_push($applicationIds, ...($project['applicationIds'] ?? []));

            foreach ($project['stones'] ?? [] as $stone) {
                $stoneSlugs[] = $stone['stoneId'];
            }
        }

        $serviceIds = Service::query()->whereIn('slug', array_unique($serviceSlugs))->pluck('id', 'slug');
        $standaloneBySlug = Service::query()->whereIn('slug', array_unique($serviceSlugs))->pluck('is_standalone', 'slug');
        $stoneIds = Stone::query()->whereIn('slug', array_unique($stoneSlugs))->pluck('id', 'slug');
        $edgeProfileIds = EdgeProfile::query()->pluck('id', 'name');
        $finishIds = Finish::query()->pluck('id', 'name');
        $categoryIds = ProjectCategory::query()->whereIn('slug', array_unique($applicationIds))->pluck('id', 'slug');

        $input = [];

        foreach ($projects as $project) {
            $isVisit = true;

            foreach ($project['serviceIds'] as $slug) {
                if (! $standaloneBySlug->get($slug)) {
                    $isVisit = false;
                }
            }

            $row = [
                'name' => $project['name'],
                'service_ids' => [],
                'application_ids' => [],
                'stones' => [],
                'location' => $project['location'],
                'landmark' => $project['landmark'] ?? null,
                'area_sqm' => isset($project['areaSqm']) ? (float) $project['areaSqm'] : null,
                'deadline_date' => $project['neededBy'],
                // Only a visit is attended at an hour; anything else is booked
                // for the default time.
                'deadline_time' => $isVisit ? ($project['neededByTime'] ?? null) : null,
                'notes' => $project['notes'] ?? null,
                'files' => [],
            ];

            foreach ($project['serviceIds'] as $slug) {
                $row['service_ids'][] = $serviceIds[$slug];
            }

            foreach ($project['applicationIds'] ?? [] as $slug) {
                $row['application_ids'][] = $categoryIds[$slug];
            }

            foreach ($project['stones'] ?? [] as $stone) {
                $row['stones'][] = [
                    'stone_id' => $stoneIds[$stone['stoneId']],
                    'edge_profile_id' => $edgeProfileIds[$stone['edge']],
                    'finish_id' => $finishIds[$stone['finish']],
                    'quantity' => (int) $stone['quantity'],
                ];
            }

            foreach ($project['attachments'] ?? [] as $file) {
                if ($file instanceof UploadedFile) {
                    $row['files'][] = $file;
                }
            }

            $input[] = $row;
        }

        return ['projects' => $input];
    }
}
