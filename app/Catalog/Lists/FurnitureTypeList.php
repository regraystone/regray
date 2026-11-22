<?php

namespace App\Catalog\Lists;

use App\Catalog\CatalogListDefinition;
use App\Models\Furniture;
use App\Models\FurnitureType;
use App\Support\ReservedSlugs;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Furniture types, such as Dining Tables, that a piece is filed under and a
 * brand's page filters by. Archived only once no piece has it.
 *
 * @extends CatalogListDefinition<FurnitureType>
 */
class FurnitureTypeList extends CatalogListDefinition
{
    public bool $hasStatus = true;

    public bool $hasOrder = true;

    public function title(): string
    {
        return 'Furniture types';
    }

    public function singular(): string
    {
        return 'furniture type';
    }

    public function subtitle(): string
    {
        return 'The kinds of furniture a piece is filed under';
    }

    public function model(): string
    {
        return FurnitureType::class;
    }

    public function query(?string $escapedSearch): Builder
    {
        return FurnitureType::query()
            ->when($escapedSearch !== null, fn (Builder $query) => $query->where('name', 'ILIKE', "%{$escapedSearch}%"))
            ->addSelect(['furniture_types.*', 'pieces_count' => Furniture::query()->selectRaw('count(*)')->whereColumn('furniture_type_id', 'furniture_types.id')])
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function columns(): array
    {
        return [['key' => 'pieces', 'header' => 'Pieces'], ['key' => 'order', 'header' => 'Order']];
    }

    public function fields(): array
    {
        return [
            ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'maxLength' => 100],
            ['name' => 'slug', 'label' => 'Slug', 'type' => 'slug', 'required' => true, 'maxLength' => 100, 'hint' => 'Its filter link on a brand\'s page, such as dining-tables.'],
            self::orderField(),
            self::activeField('An inactive type is not offered for a new piece or as a filter.'),
        ];
    }

    public function rules(?Model $item): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('furniture_types', 'name')->ignore($item?->getKey())],
            'slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::notIn(ReservedSlugs::ALL), Rule::unique('furniture_types', 'slug')->ignore($item?->getKey())],
            ...$this->orderAndStatusRules(),
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => __('Enter the furniture type\'s name.'),
            'name.unique' => __('Another furniture type, maybe an archived one, already has that name.'),
        ];
    }

    public function name(Model $item): string
    {
        return $item->name;
    }

    public function row(Model $item): array
    {
        return [
            'cells' => ['pieces' => self::count((int) $item->getAttribute('pieces_count'), 'piece', 'pieces'), 'order' => (string) $item->sort_order],
            'values' => ['name' => $item->name, 'slug' => $item->slug, 'sort_order' => (string) $item->sort_order, 'is_active' => $item->is_active],
        ];
    }

    public function fill(Model $item, array $data): void
    {
        $item->fill(['name' => $data['name'], 'slug' => $data['slug']]);
        $this->fillOrderAndStatus($item, $data);
    }

    public function usage(Model $item): ?string
    {
        $pieces = Furniture::query()->where('furniture_type_id', $item->getKey())->count();

        return $pieces === 0 ? null : self::say('Used by :count. Give them another type first.', ['count' => self::count($pieces, 'piece', 'pieces')]);
    }

    public function snapshot(Model $item): array
    {
        return ['name' => $item->name, 'slug' => $item->slug, 'sort_order' => $item->sort_order, 'is_active' => $item->is_active, 'archived_at' => $item->deleted_at?->toIso8601String()];
    }
}
