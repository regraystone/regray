<?php

namespace App\Catalog\Lists;

use App\Catalog\CatalogListDefinition;
use App\Models\CareTip;
use App\Models\StoneType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Care tips, such as Seal it once a year, that a stone type lists under how
 * to care for it. Archived only once no stone type lists it.
 *
 * @extends CatalogListDefinition<CareTip>
 */
class CareTipList extends CatalogListDefinition
{
    public function title(): string
    {
        return 'Care tips';
    }

    public function singular(): string
    {
        return 'care tip';
    }

    public function subtitle(): string
    {
        return 'How to care for a stone, as its stone type\'s pages list it';
    }

    public function model(): string
    {
        return CareTip::class;
    }

    public function query(?string $escapedSearch): Builder
    {
        return CareTip::query()
            ->when($escapedSearch !== null, fn (Builder $query) => $query->where('tip', 'ILIKE', "%{$escapedSearch}%"))
            ->withCount(['stoneTypes' => fn (Builder $types) => $types->whereNull('stone_types.deleted_at')])
            ->orderBy('tip')
            ->orderBy('id');
    }

    public function columns(): array
    {
        return [['key' => 'types', 'header' => 'Stone types']];
    }

    public function fields(): array
    {
        return [
            ['name' => 'tip', 'label' => 'Tip', 'type' => 'textarea', 'required' => true, 'maxLength' => 255],
        ];
    }

    public function rules(?Model $item): array
    {
        return [
            'tip' => ['required', 'string', 'max:255', Rule::unique('care_tips', 'tip')->ignore($item?->getKey())],
            ...$this->orderAndStatusRules(),
        ];
    }

    public function messages(): array
    {
        return [
            'tip.required' => __('Enter the care tip.'),
            'tip.unique' => __('That care tip, maybe an archived one, is already on the list.'),
        ];
    }

    public function name(Model $item): string
    {
        return $item->tip;
    }

    public function row(Model $item): array
    {
        return [
            'cells' => ['types' => self::count((int) $item->getAttribute('stone_types_count'), 'stone type', 'stone types')],
            'values' => ['tip' => $item->tip],
        ];
    }

    public function fill(Model $item, array $data): void
    {
        $item->fill(['tip' => $data['tip']]);
        $this->fillOrderAndStatus($item, $data);
    }

    public function usage(Model $item): ?string
    {
        $types = StoneType::query()->whereHas('careTips', fn (Builder $items) => $items->whereKey($item->getKey()))->count();

        return $types === 0 ? null : self::say('Used by :count. Take it off their care tips first.', ['count' => self::count($types, 'stone type', 'stone types')]);
    }

    public function snapshot(Model $item): array
    {
        return ['tip' => $item->tip, 'archived_at' => $item->deleted_at?->toIso8601String()];
    }
}
