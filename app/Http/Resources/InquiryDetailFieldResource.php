<?php

namespace App\Http\Resources;

use App\Models\DetailField;
use App\Models\DetailFieldOption;
use App\Models\EdgeProfile;
use App\Models\Finish;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One question a service asks, as the Inquire page renders it: whether it is
 * answered on each stone row or once for the project, its answer type,
 * whether it is required, whether a free entry is taken, the closed `options`
 * to pick from and the `suggestions` shown beside a free entry, each as
 * `{value, label}`. Nothing about a question is written into the page.
 *
 * Edge profile and finish list the active rows of their own tables, by name,
 * with their advisory flag and description. "Same as stone" carries
 * `linksToStone`. A suggestion's value is the value itself, which is what an
 * answer stores; a closed option's value is its slug.
 *
 * A question's own choices are only those offered for the service it was
 * loaded through (a choice that lists services is offered for those alone),
 * each with its `description`, its picture (`image`), whether it asks the
 * client to describe their own (`isCustom`) or is "Not sure yet, advise me"
 * (`isAdvisory`), and, for a table shape, how the size is then asked
 * (`sizeKind`). `pictures` says the question is asked with picture cards.
 *
 * Expects the service pivot, the options and their services loaded.
 *
 * @mixin DetailField
 */
class InquiryDetailFieldResource extends JsonResource
{
    /**
     * What a custom choice's description is held under in the page's answers,
     * after its question's slug: `table_shape:custom`. A slug never has a
     * colon, so it never meets a question's own answer.
     */
    public const CUSTOM_SUFFIX = ':custom';

    /**
     * @return array{slug: string, label: string, scope: string, answerType: string, required: bool, allowsCustom: bool, pictures: bool, options: list<array<string, mixed>>, suggestions: list<array{value: string, label: string}>}
     */
    public function toArray(Request $request): array
    {
        $serviceId = $this->relationLoaded('pivot') ? $this->getRelation('pivot')?->getAttribute('service_id') : null;

        return [
            'slug' => $this->slug,
            'label' => $this->name,
            'scope' => $this->scope->value,
            'answerType' => $this->answer_type->value,
            'required' => $this->isRequiredByService(),
            'allowsCustom' => $this->allows_custom,
            'pictures' => $this->asksWithPictures(),
            'options' => $this->answersFromCatalog()
                ? self::catalogAnswers($this->slug)
                : array_values($this->closedOptions(is_numeric($serviceId) ? (int) $serviceId : null)->map(fn (DetailFieldOption $option): array => self::choice($option))->all()),
            'suggestions' => array_values($this->suggestions()->map(fn (DetailFieldOption $option): array => [
                'value' => $option->name,
                'label' => $option->name,
            ])->all()),
        ];
    }

    /**
     * One of a question's own choices, as the page lists it. Also how an edit
     * lists a choice its inquiry already has (InquiryEditResource).
     *
     * @return array{value: string, label: string, linksToStone: bool, isCustom: bool, isAdvisory: bool, description: string|null, image: string|null, sizeKind: string|null}
     */
    public static function choice(DetailFieldOption $option): array
    {
        return [
            'value' => $option->slug,
            'label' => $option->name,
            'linksToStone' => $option->links_to_stone,
            'isCustom' => $option->is_custom,
            'isAdvisory' => $option->is_advisory,
            'description' => $option->description,
            'image' => filled($option->thumbnail_path) ? ShowcaseCardResource::resolveThumbnailUrl((string) $option->thumbnail_path) : null,
            'sizeKind' => $option->size_kind,
        ];
    }

    /**
     * The active edge profiles or finishes, read once per request however many
     * services ask for them, in their lists' order (as their admin pages list
     * them: display order, then name).
     *
     * @return list<array{value: string, label: string, isAdvisory: bool, description: string|null}>
     */
    private static function catalogAnswers(string $slug): array
    {
        $profiles = once(fn (): array => [
            DetailField::EDGE_PROFILE => EdgeProfile::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['name', 'is_advisory', 'description']),
            DetailField::FINISH => Finish::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['name', 'is_advisory', 'description']),
        ]);

        return array_values($profiles[$slug]->map(fn (EdgeProfile|Finish $row): array => [
            'value' => $row->name,
            'label' => $row->name,
            'isAdvisory' => $row->is_advisory,
            'description' => $row->description,
        ])->all());
    }
}
