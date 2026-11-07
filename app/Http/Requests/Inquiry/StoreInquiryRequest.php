<?php

namespace App\Http\Requests\Inquiry;

use App\Enums\DeliveryMethod;
use App\Models\EdgeProfile;
use App\Models\Finish;
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
 * field: the keys are the form's own (`serviceId`, `stones.*.stoneId`,
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

            // Whether it is still offered is after()'s, since an edit may keep its own.
            'projects.*.serviceId' => ['required', 'string', Rule::exists('services', 'slug')],

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
            'projects.*.serviceId.required' => 'Choose what you need.',
            'projects.*.serviceId.exists' => 'That choice is no longer available.',
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
     * A project needs a stone, or a stone left to Regray to recommend. A service
     * no longer offered, or whose service type is not, is refused unless this
     * inquiry already has it (heldServiceSlugs()).
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
                    /** @var Service $service */
                    $service = $services[$project['serviceId']];

                    if (! $service->isOffered() && ! in_array($service->slug, $held, true)) {
                        $validator->errors()->add("projects.{$index}.serviceId", 'That choice is no longer available.');
                    }

                    if (empty($project['neededBy'])) {
                        $validator->errors()->add("projects.{$index}.neededBy", 'Say when this is needed.');
                    }

                    if (empty($project['stones']) && ! filter_var($project['stoneAdvice'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                        $validator->errors()->add("projects.{$index}.stones", 'Select at least one stone.');
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
     * Every service the projects name, by slug.
     *
     * @param  array<int, array<string, mixed>>  $projects
     * @return Collection<string, Service>
     */
    private function servicesFor(array $projects): Collection
    {
        return Service::query()
            ->with('serviceType')
            ->whereIn('slug', array_unique(array_column($projects, 'serviceId')))
            ->get()
            ->keyBy('slug');
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
        }

        $stoneIds = Stone::query()->withTrashed()->whereIn('slug', array_unique($stoneSlugs))->pluck('id', 'slug');
        $edgeProfileIds = EdgeProfile::query()->pluck('id', 'name');
        $finishIds = Finish::query()->pluck('id', 'name');
        $quantityLimit = (int) config('inquiry.limits.quantity_max');

        $input = [];

        foreach ($projects as $project) {
            $deliveryMethod = DeliveryMethod::from($project['deliveryMethod']);
            $isDelivery = $deliveryMethod === DeliveryMethod::Delivery;

            $stones = [];

            // A stone holds one row per edge and finish, so a repeated row adds to it.
            foreach ($project['stones'] ?? [] as $stone) {
                $row = "{$stone['stoneId']}::{$stone['edge']}::{$stone['finish']}";

                $stones[$row] = [
                    'stone_id' => $stoneIds[$stone['stoneId']],
                    'edge_profile_id' => $edgeProfileIds[$stone['edge']],
                    'finish_id' => $finishIds[$stone['finish']],
                    'quantity' => min(($stones[$row]['quantity'] ?? 0) + (int) $stone['quantity'], $quantityLimit),
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
                'service_id' => $services[$project['serviceId']]->id,
                'stones' => array_values($stones),
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
