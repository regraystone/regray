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
 * with their advisory flag. "Same as stone" carries `linksToStone`. A
 * suggestion's value is the value itself, which is what an answer stores; a
 * closed option's value is its slug.
 *
 * Expects the service pivot and the options loaded.
 *
 * @mixin DetailField
 */
class InquiryDetailFieldResource extends JsonResource
{
    /**
     * @return array{slug: string, label: string, scope: string, answerType: string, required: bool, allowsCustom: bool, options: list<array{value: string, label: string, isAdvisory?: bool, linksToStone?: bool}>, suggestions: list<array{value: string, label: string}>}
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'label' => $this->name,
            'scope' => $this->scope->value,
            'answerType' => $this->answer_type->value,
            'required' => $this->isRequiredByService(),
            'allowsCustom' => $this->allows_custom,
            'options' => $this->answersFromCatalog()
                ? self::catalogAnswers($this->slug)
                : array_values($this->closedOptions()->map(fn (DetailFieldOption $option): array => [
                    'value' => $option->slug,
                    'label' => $option->name,
                    'linksToStone' => $option->links_to_stone,
                ])->all()),
            'suggestions' => array_values($this->suggestions()->map(fn (DetailFieldOption $option): array => [
                'value' => $option->name,
                'label' => $option->name,
            ])->all()),
        ];
    }

    /**
     * The active edge profiles or finishes, read once per request however many
     * services ask for them, in their lists' order (as their admin pages list
     * them: display order, then name).
     *
     * @return list<array{value: string, label: string, isAdvisory: bool}>
     */
    private static function catalogAnswers(string $slug): array
    {
        $profiles = once(fn (): array => [
            DetailField::EDGE_PROFILE => EdgeProfile::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['name', 'is_advisory']),
            DetailField::FINISH => Finish::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['name', 'is_advisory']),
        ]);

        return array_values($profiles[$slug]->map(fn (EdgeProfile|Finish $row): array => [
            'value' => $row->name,
            'label' => $row->name,
            'isAdvisory' => $row->is_advisory,
        ])->all());
    }
}
