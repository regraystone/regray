<?php

namespace App\Services;

use App\Models\DetailField;
use App\Models\DetailFieldOption;
use App\Models\EdgeProfile;
use App\Models\Finish;
use App\Models\Furniture;
use App\Models\FurnitureType;
use App\Models\Service;
use App\Models\StoneType;
use Illuminate\Database\Eloquent\Collection;

/**
 * What a client may choose for a piece of furniture, read from the Inquire
 * page's own services so the furniture page never offers what an inquiry
 * cannot ask for: the stone types the service takes, and the answers to its
 * choice questions (its table shape, base design and material, edge profile
 * and finish, as offered for that service) and its number
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
        'other-customized-furniture' => ['other-customized-furniture'],
    ];

    /** Questions about the project rather than the piece, left out. */
    private const PROJECT_QUESTIONS = ['area_sqm'];

    /**
     * The furniture columns that hold a table's own shape and base design. A
     * service asks one shape question (table_shape) and its own base design
     * question (DetailField::isTableBaseDesign()).
     */
    public const TABLE_COLUMNS = ['table_shape_option_id', 'table_base_design_option_id'];

    /**
     * @return array{stones: list<string>, options: list<array{label: string, values: list<string>}>}
     */
    public function for(Furniture $furniture): array
    {
        $services = $this->servicesFor($furniture->furnitureType->slug, ['stoneTypes' => fn ($types) => $types->where('stone_types.is_active', true)->orderBy('stone_types.sort_order')]);

        $options = [];

        foreach ($services as $service) {
            foreach ($service->detailFields as $field) {
                $values = $this->valuesOf($field, $service->id);

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
     * The shapes and base designs a piece of each furniture type may be given
     * on the Products page: the choices its services offer, without a custom
     * or "advise me" one, by furniture type id and then by furniture column.
     * A type none of whose services asks either is left out.
     *
     * @return array<int, array<string, list<array{id: int, name: string}>>>
     */
    public function tableChoices(): array
    {
        $choices = [];

        foreach (FurnitureType::query()->whereIn('slug', array_keys(self::SERVICES_BY_TYPE))->get(['id', 'slug']) as $type) {
            $services = $this->servicesFor($type->slug);

            foreach (self::TABLE_COLUMNS as $column) {
                $options = $services->flatMap(fn (Service $service): array => $this->tableOptions($service, $column)->all())->unique('id')->values();

                if ($options->isNotEmpty()) {
                    $choices[$type->id][$column] = array_values($options->map(fn (DetailFieldOption $option): array => ['id' => $option->id, 'name' => $option->name])->all());
                }
            }
        }

        return $choices;
    }

    /**
     * What a piece's Inquire button picks for the client, as the Inquire page
     * names it: the piece's brand, the service it is ordered through and its
     * own shape and base design. A center or side table is ordered through
     * the service whose base design question lists its base (the first, with
     * none). Null for a piece
     * not shown on Regray, or whose type no offered service takes.
     *
     * @return array{brand: string, serviceType: string, service: string, answers: array<string, string>}|null
     */
    public function inquiryPrefill(?string $slug): ?array
    {
        $piece = $slug === null ? null : Furniture::query()->active()->with(['brand', 'furnitureType'])->where('slug', $slug)->first();

        if ($piece === null) {
            return null;
        }

        $services = $this->servicesFor($piece->furnitureType->slug, ['serviceType']);
        $base = $piece->table_base_design_option_id;
        $service = ($base === null ? null : $services->first(fn (Service $service): bool => $this->tableOptions($service, 'table_base_design_option_id')->contains('id', $base)))
            ?? $services->first();

        if ($service === null) {
            return null;
        }

        $answers = [];

        foreach (self::TABLE_COLUMNS as $column) {
            $field = $this->tableField($service, $column);
            $option = $this->tableOptions($service, $column)->firstWhere('id', $piece->{$column});

            if ($field !== null && $option !== null) {
                $answers[$field->slug] = $option->slug;
            }
        }

        return ['brand' => $piece->brand->slug, 'serviceType' => $service->serviceType->slug, 'service' => $service->slug, 'answers' => $answers];
    }

    /**
     * The active services a furniture type is ordered through, in order, with
     * their questions and offered choices.
     *
     * @param  array<int|string, mixed>  $with
     * @return Collection<int, Service>
     */
    private function servicesFor(string $typeSlug, array $with = []): Collection
    {
        return Service::query()
            ->where('is_active', true)
            ->whereIn('slug', self::SERVICES_BY_TYPE[$typeSlug] ?? [])
            ->with([
                ...$with,
                'detailFields.options' => fn ($options) => $options->where('is_active', true)->whereNull('archived_at'),
                'detailFields.options.services:services.id',
            ])
            ->orderBy('sort_order')
            ->get();
    }

    /** The service's question a furniture column answers: its shape, or its own base design. */
    private function tableField(Service $service, string $column): ?DetailField
    {
        return $service->detailFields->first(fn (DetailField $field): bool => $column === 'table_shape_option_id'
            ? $field->slug === DetailField::TABLE_SHAPE
            : $field->isTableBaseDesign());
    }

    /**
     * A table question's choices a piece may be given under this service: the
     * ones offered for it, without a custom or "advise me" one. None where the
     * service doesn't ask it.
     *
     * @return Collection<int, DetailFieldOption>
     */
    private function tableOptions(Service $service, string $column): Collection
    {
        $field = $this->tableField($service, $column);

        return $field === null
            ? new Collection
            : $field->closedOptions($service->id)->reject(fn (DetailFieldOption $option): bool => $option->is_advisory || $option->is_custom)->values();
    }

    /**
     * The answers a question offers, as the Inquire page shows them: the edge
     * profiles and finishes (not the "advise me" one), a number's set answers
     * as a range ("2 to 12"), or the choice's own options offered for the
     * service, leaving out "Not sure yet, advise me" and a custom one (a table
     * shape or base of the client's own is asked for on the Inquire page).
     *
     * @return list<string>
     */
    private function valuesOf(DetailField $field, int $serviceId): array
    {
        return array_values(match (true) {
            $field->slug === 'edge_profile' => $this->namesOf(EdgeProfile::query()->where('is_active', true)->where('is_advisory', false)->orderBy('sort_order')->get(['name'])),
            $field->slug === 'finish' => $this->namesOf(Finish::query()->where('is_active', true)->where('is_advisory', false)->orderBy('sort_order')->get(['name'])),
            $field->answer_type->value === 'number' => $this->range($field->options),
            $field->answer_type->value === 'choice' => $field->closedOptions($serviceId)
                ->reject(fn (DetailFieldOption $option): bool => $option->is_advisory || $option->is_custom)
                ->map(fn (DetailFieldOption $option): string => $option->name)->values()->all(),
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
