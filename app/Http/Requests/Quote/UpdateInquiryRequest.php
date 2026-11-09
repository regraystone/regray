<?php

namespace App\Http\Requests\Quote;

use App\Http\Requests\Inquiry\StoreInquiryRequest;
use App\Models\Service;
use Closure;
use Illuminate\Validation\Validator;

/**
 * The edit of an inquiry from the Quote page: the same answers the Inquire page
 * sends, so every create rule applies unchanged, plus what an edit needs on top.
 *
 * - `projects.*.id` is the inquiry's own id: an inquiry is one project, and the
 *   page sends it as the one project it opened. Whether it is this inquiry is
 *   checked by InquiryService, which holds the inquiry's row lock.
 * - `projects.*.keepFileIds` are the stored files the client left in place.
 *   `projects.*.attachments` are new uploads only, and the two together may not
 *   exceed the per-project file limit.
 *
 * The stone rule of the create applies as it is: every stone, the inquiry's
 * own included, has to be offered for the service sent, whether the service
 * changed or not. Unlike the service, a stone is never held.
 *
 * @phpstan-import-type ProjectUpdateInput from \App\Services\InquiryService
 */
class UpdateInquiryRequest extends StoreInquiryRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'projects.*.id' => ['required', 'integer', 'distinct'],
            'projects.*.keepFileIds' => ['nullable', 'array'],
            'projects.*.keepFileIds.*' => ['integer', 'distinct'],
        ];
    }

    /**
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            ...parent::after(),
            function (Validator $validator): void {
                $limit = config('inquiry.limits.files');

                /** @var array<int, array<string, mixed>> $projects */
                $projects = $this->input('projects', []);

                foreach ($projects as $index => $project) {
                    $kept = is_array($project['keepFileIds'] ?? null) ? count($project['keepFileIds']) : 0;
                    $added = is_array($project['attachments'] ?? null) ? count($project['attachments']) : 0;

                    if ($kept + $added > $limit) {
                        $validator->errors()->add("projects.{$index}.attachments", "You can attach up to {$limit} files.");
                    }
                }
            },
        ];
    }

    /**
     * An edit keeps the service its inquiry already has, offered or not.
     *
     * @return list<string>
     */
    protected function heldServiceSlugs(): array
    {
        return array_values(Service::query()
            ->whereHas('inquiries', fn ($inquiry) => $inquiry->where('reference', $this->route('reference')))
            ->pluck('slug')
            ->all());
    }

    /**
     * The validated edit in the shape InquiryService::update() writes: the
     * create input for each project, with its id and the files it keeps.
     *
     * @return array{projects: list<ProjectUpdateInput>}
     */
    public function updateInput(): array
    {
        /** @var list<array<string, mixed>> $validated */
        $validated = array_values($this->validated('projects'));
        $projects = [];

        foreach ($this->inquiryInput()['projects'] as $index => $project) {
            $keep = $validated[$index]['keepFileIds'] ?? [];

            $projects[] = [
                'id' => (int) $validated[$index]['id'],
                ...$project,
                'keep_file_ids' => array_values(array_map('intval', is_array($keep) ? $keep : [])),
            ];
        }

        return ['projects' => $projects];
    }
}
