<?php

namespace App\Catalog\Lists;

use App\Catalog\CatalogListDefinition;
use App\Data\Measurement;
use App\Enums\ThicknessUnit;
use App\Models\Stone;
use App\Models\Thickness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Thicknesses, a value in one unit, that a stone's sizes are cut in. One
 * value and unit comes once, archived ones included (as the database holds
 * it). Its place in a list is its unit's: metric first, then thinnest first.
 * Archived only once no stone is cut in it.
 *
 * @extends CatalogListDefinition<Thickness>
 */
class ThicknessList extends CatalogListDefinition
{
    public function title(): string
    {
        return 'Thicknesses';
    }

    public function singular(): string
    {
        return 'thickness';
    }

    public function subtitle(): string
    {
        return 'The thicknesses a stone can be cut in';
    }

    public function model(): string
    {
        return Thickness::class;
    }

    public function query(?string $escapedSearch): Builder
    {
        return Thickness::query()
            ->when($escapedSearch !== null, fn (Builder $query) => $query->whereRaw(
                "concat(trim_scale(value), ' ', unit) ILIKE ?",
                ["%{$escapedSearch}%"],
            ))
            ->addSelect(['thicknesses.*', 'stones_count' => Stone::query()->selectRaw('count(distinct stones.id)')
                ->join('stone_variants', 'stone_variants.stone_id', '=', 'stones.id')
                ->whereColumn('stone_variants.thickness_id', 'thicknesses.id')])
            ->orderBy('sort_order')
            ->orderBy('value');
    }

    public function columns(): array
    {
        return [['key' => 'stones', 'header' => 'Stones']];
    }

    public function fields(): array
    {
        return [
            ['name' => 'value', 'label' => 'Thickness', 'type' => 'number', 'required' => true, 'step' => '0.01'],
            ['name' => 'unit', 'label' => 'Unit', 'type' => 'select', 'required' => true, 'options' => array_map(
                fn (ThicknessUnit $unit): array => ['value' => $unit->value, 'label' => $unit->label()],
                ThicknessUnit::cases(),
            )],
        ];
    }

    public function rules(?Model $item): array
    {
        return [
            'value' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:99999'],
            'unit' => ['required', Rule::enum(ThicknessUnit::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'value.required' => __('Enter the thickness.'),
            'value.gt' => __('Enter a thickness greater than 0.'),
            'value.decimal' => __('Use at most two decimals.'),
            'unit.required' => __('Choose the unit.'),
        ];
    }

    /**
     * A thickness comes once, archived ones included.
     *
     * @return \Closure(Validator, ?Model): void
     */
    public function after(): \Closure
    {
        return function (Validator $validator, ?Model $item): void {
            if ($validator->errors()->hasAny(['value', 'unit'])) {
                return;
            }

            $data = $validator->getData();
            $taken = Thickness::query()->withTrashed()
                ->where('value', Measurement::number((string) $data['value']))
                ->where('unit', $data['unit'])
                ->whereKeyNot($item?->getKey() ?? 0)
                ->first();

            if ($taken !== null) {
                $validator->errors()->add('value', $taken->trashed()
                    ? __('That thickness was archived and can\'t be added again.')
                    : __('That thickness is already on the list.'));
            }
        };
    }

    public function name(Model $item): string
    {
        return $item->label;
    }

    public function row(Model $item): array
    {
        return [
            'cells' => ['stones' => self::count((int) $item->getAttribute('stones_count'), 'stone', 'stones')],
            'values' => [
                'value' => Measurement::number($item->value),
                'unit' => $item->unit->value,
            ],
        ];
    }

    public function fill(Model $item, array $data): void
    {
        $unit = ThicknessUnit::from((string) $data['unit']);

        $item->fill([
            'value' => Measurement::number((string) $data['value']),
            'unit' => $unit,
            'sort_order' => $unit->position(),
        ]);
    }

    public function usage(Model $item): ?string
    {
        $stones = Stone::query()->whereHas('variants', fn (Builder $variants) => $variants->where('thickness_id', $item->id))->count();

        return $stones === 0 ? null : self::say('Used by :count. Take it off their sizes first.', ['count' => self::count($stones, 'stone', 'stones')]);
    }

    public function snapshot(Model $item): array
    {
        return [
            'value' => $item->value,
            'unit' => $item->unit->value,
            'archived_at' => $item->deleted_at?->toIso8601String(),
        ];
    }
}
