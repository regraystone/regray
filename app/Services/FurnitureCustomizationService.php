<?php

namespace App\Services;

use App\Models\DetailField;
use App\Models\DetailFieldOption;
use App\Models\EdgeProfile;
use App\Models\Finish;
use App\Models\Furniture;
use App\Models\Service;
use App\Models\StoneType;
use Illuminate\Database\Eloquent\Collection;

/**
 * What a client may choose for a piece of furniture, read from the Inquire
 * page's own services so the furniture page never offers what an inquiry
 * cannot ask for: the stone types the service takes, and the answers to its
 * choice questions (its table base, edge profile and finish) and its number
 * questions with set answers (how many it seats).
 *
 * A furniture type and its services are linked by slug (SERVICES_BY_TYPE),
 * as the schema has no link between the two. A type with no service, or a
 * service that is not offered, gives no options.
 */
class FurnitureCustomizationService
{
    /** The Inquire services each furniture type is ordered through, by slug. */
    public const SERVICES_BY_TYPE = [
        'center-side-tables' => ['center-tables', 'side-tables'],
        'dining-tables' => ['dining-tables'],
        'pedestal' => ['pedestal'],
        'other-customized-furniture' => ['other-customized-furniture'],
    ];

    /** Questions about the project rather than the piece, left out. */
    private const PROJECT_QUESTIONS = ['area_sqm'];

    /**
     * @return array{stones: list<string>, options: list<array{label: string, values: list<string>}>}
     */
    public function for(Furniture $furniture): array
    {
        $services = Service::query()
            ->where('is_active', true)
            ->whereIn('slug', self::SERVICES_BY_TYPE[$furniture->furnitureType->slug] ?? [])
            ->with([
                'stoneTypes' => fn ($types) => $types->where('stone_types.is_active', true)->orderBy('stone_types.sort_order'),
                'detailFields.options' => fn ($options) => $options->where('is_active', true),
            ])
            ->orderBy('sort_order')
            ->get();

        $options = [];

        foreach ($services as $service) {
            foreach ($service->detailFields as $field) {
                $values = $this->valuesOf($field);

                if ($values === [] || in_array($field->slug, self::PROJECT_QUESTIONS, true)) {
                    continue;
                }

                $options[$field->slug] = [
                    'label' => $field->name,
                    'values' => array_values(array_unique([...($options[$field->slug]['values'] ?? []), ...$values])),
                ];
            }
        }

        return [
            'stones' => array_values(array_unique($services->flatMap(fn (Service $service): array => $service->stoneTypes->map(fn (StoneType $type): string => $type->name)->all())->all())),
            'options' => array_values($options),
        ];
    }

    /**
     * The answers a question offers, as the Inquire page shows them: the edge
     * profiles and finishes (not the "advise me" one), a number's set answers
     * as a range ("2 to 12"), or the choice's own options.
     *
     * @return list<string>
     */
    private function valuesOf(DetailField $field): array
    {
        return array_values(match (true) {
            $field->slug === 'edge_profile' => $this->namesOf(EdgeProfile::query()->where('is_active', true)->where('is_advisory', false)->orderBy('sort_order')->get(['name'])),
            $field->slug === 'finish' => $this->namesOf(Finish::query()->where('is_active', true)->where('is_advisory', false)->orderBy('sort_order')->get(['name'])),
            $field->answer_type->value === 'number' => $this->range($field->options),
            $field->answer_type->value === 'choice' => $field->options->map(fn (DetailFieldOption $option): string => $option->name)->values()->all(),
            default => [],
        });
    }

    /**
     * @param  Collection<int, EdgeProfile>|Collection<int, Finish>  $rows
     * @return list<string>
     */
    private function namesOf(Collection $rows): array
    {
        return array_values($rows->map(fn (EdgeProfile|Finish $row): string => $row->name)->all());
    }

    /**
     * A number question's set answers as one range, "2 to 12".
     *
     * @param  Collection<int, DetailFieldOption>  $options
     * @return list<string>
     */
    private function range(Collection $options): array
    {
        $numbers = $options->map(fn (DetailFieldOption $option): float => (float) $option->name)->filter()->sort()->values();

        if ($numbers->isEmpty()) {
            return [];
        }

        $format = fn (float $number): string => rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
        $low = $format($numbers->first());
        $high = $format($numbers->last());

        return [$low === $high ? $low : "{$low} to {$high}"];
    }
}
