<?php

namespace App\Http\Requests\Quote;

use App\Data\PhAddress;
use App\Http\Requests\Inquiry\StoreInquiryRequest;
use App\Models\DetailField;
use App\Models\Inquiry;
use App\Models\InquiryStone;
use App\Models\Service;
use App\Models\User;
use App\Services\ScheduleService;
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
 * - The client picks the address from their address book, as on a new
 *   inquiry, or keeps the one the inquiry already has (`keepAddress`), which
 *   may since have left the book.
 *
 * What the inquiry already has is held, as its service is: maintenance on
 * the catalog since it was sent (a stone switched off or archived, a brand
 * archived, a stone type taken off its service, an edge profile, finish or
 * answer taken off its list) never stops it being saved as it is. Its
 * stones are held while it keeps its service; a new service takes only
 * stones it offers. Its brands and answers are held either way.
 *
 * @phpstan-import-type ProjectUpdateInput from \App\Services\InquiryService
 */
class UpdateInquiryRequest extends StoreInquiryRequest
{
    /** The inquiry being edited with what it holds, read once (heldInquiry()). */
    private ?Inquiry $held = null;

    private bool $heldRead = false;

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

    protected function addressSource(): string
    {
        return self::ADDRESS_FROM_BOOK;
    }

    /** The address the client's inquiry already has, which the edit may keep. */
    protected function heldAddress(): ?PhAddress
    {
        $inquiry = $this->editedInquiry();

        return $inquiry?->address();
    }

    /**
     * The inquiry being edited, read only as its editor may read it: a
     * client's own, or one staff read.
     */
    protected function editedInquiry(): ?Inquiry
    {
        /** @var User $editor */
        $editor = $this->user();

        return Inquiry::query()
            ->where('reference', $this->route('reference'))
            ->when($editor->isClient(), fn ($own) => $own->where('client_id', $editor->id), fn ($visible) => $visible->visibleTo($editor))
            ->first();
    }

    /**
     * An edit keeps the service its inquiry already has, offered or not. Read
     * only from an inquiry the editor may edit (a client's own, one staff
     * read), so the answer says nothing about any other.
     *
     * @return list<string>
     */
    protected function heldServiceSlugs(): array
    {
        /** @var User $editor */
        $editor = $this->user();

        return array_values(Service::query()
            ->whereHas('inquiries', fn ($inquiry) => $inquiry
                ->where('reference', $this->route('reference'))
                ->when($editor->isClient(), fn ($own) => $own->where('client_id', $editor->id), fn ($visible) => $visible->visibleTo($editor)))
            ->pluck('slug')
            ->all());
    }

    /** @return list<string> */
    protected function heldStoneSlugs(): array
    {
        return array_values(array_unique($this->heldInquiry()?->stones
            ->map(fn (InquiryStone $row): ?string => $row->stone?->slug)
            ->filter()
            ->all() ?? []));
    }

    /** @return list<string> */
    protected function heldBrandSlugs(): array
    {
        return array_values($this->heldInquiry()?->brands->pluck('slug')->all() ?? []);
    }

    /** The date the inquiry already has, in the showroom's time zone. */
    protected function heldDeadline(): ?string
    {
        return $this->editedInquiry()?->loadMissing('schedule')->schedule?->arrival_at->setTimezone(ScheduleService::timezone())->toDateString();
    }

    /** @return array<string, list<string>> */
    protected function heldAnswers(): array
    {
        $inquiry = $this->heldInquiry();

        if ($inquiry === null) {
            return [];
        }

        $held = [
            DetailField::EDGE_PROFILE => array_values(array_unique(array_filter($inquiry->stones->map(fn (InquiryStone $row): ?string => $row->edgeProfile?->name)->all()))),
            DetailField::FINISH => array_values(array_unique(array_filter($inquiry->stones->map(fn (InquiryStone $row): ?string => $row->finish?->name)->all()))),
        ];

        foreach ($inquiry->detailAnswers as $answer) {
            $value = $answer->formValue();

            if ($value !== null && ! is_bool($value)) {
                $held[$answer->detailField->slug][] = (string) $value;
            }
        }

        return $held;
    }

    /**
     * The inquiry being edited, with its stones (archived ones too), their
     * edge profiles and finishes, its brands and its answers: what an edit
     * may keep. Read only as its editor may read it, once per request.
     */
    private function heldInquiry(): ?Inquiry
    {
        if (! $this->heldRead) {
            $this->heldRead = true;
            $this->held = $this->editedInquiry()?->load([
                'stones.stone' => fn ($stone) => $stone->withTrashed()->select(['id', 'slug']),
                'stones.edgeProfile:id,name',
                'stones.finish:id,name',
                'brands:id,slug',
                'detailAnswers.detailField',
                'detailAnswers.option',
            ]);
        }

        return $this->held;
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
