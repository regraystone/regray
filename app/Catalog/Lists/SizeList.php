<?php

namespace App\Catalog\Lists;

use App\Catalog\CatalogListDefinition;
use App\Data\Measurement;
use App\Enums\SizeUnit;
use App\Models\Size;
use App\Models\Stone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Slab sizes, width by length in one unit, that a stone's sizes are chosen
 * from. One width, length and unit comes once, archived ones included (as
 * the database holds it). Its place in a list is its unit's: metric first,
 * then smallest first. Archived only once no stone comes in it.
 *
 * @extends CatalogListDefinition<Size>
 */
class SizeList extends CatalogListDefinition
{
    public function title(): string
    {
        return 'Sizes';
    }

    public function singular(): string
    {
        return 'size';
    }

    public function subtitle(): string
    {
        return 'The slab sizes a stone can come in';
    }

    public function model(): string
    {
        return Size::class;
    }

    public function query(?string $escapedSearch): Builder
    {
        return Size::query()
            ->when($escapedSearch !== null, fn (Builder $query) => $query->whereRaw(
                "concat(trim_scale(width), ' x ', trim_scale(length), ' ', unit) ILIKE ?",
                ["%{$escapedSearch}%"],
            ))
            ->addSelect(['sizes.*', 'stones_count' => Stone::query()->selectRaw('count(distinct stones.id)')
                ->join('stone_variants', 'stone_variants.stone_id', '=', 'stones.id')
                ->whereColumn('stone_variants.size_id', 'sizes.id')])
            ->orderBy('sort_order')
            ->orderBy('width')
            ->orderBy('length');
    }

    public function columns(): array
    {
        return [['key' => 'stones', 'header' => 'Stones']];
    }

    public function fields(): array
    {
        return [
            ['name' => 'width', 'label' => 'Width', 'type' => 'number', 'required' => true, 'step' => '0.01'],
            ['name' => 'length', 'label' => 'Length', 'type' => 'number', 'required' => true, 'step' => '0.01'],
            ['name' => 'unit', 'label' => 'Unit', 'type' => 'select', 'required' => true, 'options' => array_map(
                fn (SizeUnit $unit): array => ['value' => $unit->value, 'label' => $unit->label()],
                SizeUnit::cases(),
            )],
        ];
    }

    public function rules(?Model $item): array
    {
        return [
            'width' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:99999'],
            'length' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:99999'],
            'unit' => ['required', Rule::enum(SizeUnit::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'width.required' => __('Enter the width.'),
            'width.gt' => __('Enter a width greater than 0.'),
            'width.decimal' => __('Use at most two decimals.'),
            'length.required' => __('Enter the length.'),
            'length.gt' => __('Enter a length greater than 0.'),
            'length.decimal' => __('Use at most two decimals.'),
            'unit.required' => __('Choose the unit.'),
        ];
    }

    /**
     * A size comes once, archived ones included.
     *
     * @return \Closure(Validator, ?Model): void
     */
    public function after(): \Closure
    {
        return function (Validator $validator, ?Model $item): void {
            if ($validator->errors()->hasAny(['width', 'length', 'unit'])) {
                return;
            }

            $data = $validator->getData();
            $taken = Size::query()->withTrashed()
                ->where('width', Measurement::number((string) $data['width']))
                ->where('length', Measurement::number((string) $data['length']))
                ->where('unit', $data['unit'])
                ->whereKeyNot($item?->getKey() ?? 0)
                ->first();

            if ($taken !== null) {
                $validator->errors()->add('width', $taken->trashed()
                    ? __('That size was archived and can\'t be added again.')
                    : __('That size is already on the list.'));
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
                'width' => Measurement::number($item->width),
                'length' => Measurement::number($item->length),
                'unit' => $item->unit->value,
            ],
        ];
    }

    public function fill(Model $item, array $data): void
    {
        $unit = SizeUnit::from((string) $data['unit']);

        $item->fill([
            'width' => Measurement::number((string) $data['width']),
            'length' => Measurement::number((string) $data['length']),
            'unit' => $unit,
            'sort_order' => $unit->position(),
        ]);
    }

    public function usage(Model $item): ?string
    {
        $stones = Stone::query()->whereHas('variants', fn (Builder $variants) => $variants->where('size_id', $item->id))->count();

        return $stones === 0 ? null : self::say('Used by :count. Take it off their sizes first.', ['count' => self::count($stones, 'stone', 'stones')]);
    }

    public function snapshot(Model $item): array
    {
        return [
            'width' => $item->width,
            'length' => $item->length,
            'unit' => $item->unit->value,
            'archived_at' => $item->deleted_at?->toIso8601String(),
        ];
    }
}
