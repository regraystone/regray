<?php

namespace App\Catalog\Lists;

use App\Catalog\CatalogListDefinition;
use App\Models\Application;
use App\Models\StoneType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Applications, such as Kitchen countertops, that a stone type lists under
 * where it suits, as it lists its care tips. Archived only once no stone type lists it.
 *
 * @extends CatalogListDefinition<Application>
 */
class ApplicationList extends CatalogListDefinition
{
    public function title(): string
    {
        return 'Applications';
    }

    public function singular(): string
    {
        return 'application';
    }

    public function subtitle(): string
    {
        return 'Where a stone type suits, as its stones\' pages list it';
    }

    public function model(): string
    {
        return Application::class;
    }

    public function query(?string $escapedSearch): Builder
    {
        return Application::query()
            ->when($escapedSearch !== null, fn (Builder $query) => $query->where('name', 'ILIKE', "%{$escapedSearch}%"))
            ->withCount(['stoneTypes' => fn (Builder $types) => $types->whereNull('stone_types.deleted_at')])
            ->orderBy('name')
            ->orderBy('name');
    }

    public function columns(): array
    {
        return [['key' => 'types', 'header' => 'Stone types']];
    }

    public function fields(): array
    {
        return [
            ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'maxLength' => 150, 'hint' => 'Such as Kitchen countertops.'],
        ];
    }

    public function rules(?Model $item): array
    {
        return [
            'name' => ['required', 'string', 'max:150', Rule::unique('applications', 'name')->ignore($item?->getKey())],
            ...$this->orderAndStatusRules(),
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => __('Enter the application.'),
            'name.unique' => __('That application, maybe an archived one, is already on the list.'),
        ];
    }

    public function name(Model $item): string
    {
        return $item->name;
    }

    public function row(Model $item): array
    {
        return [
            'cells' => ['types' => self::count((int) $item->getAttribute('stone_types_count'), 'stone type', 'stone types')],
            'values' => ['name' => $item->name],
        ];
    }

    public function fill(Model $item, array $data): void
    {
        $item->fill(['name' => $data['name']]);
        $this->fillOrderAndStatus($item, $data);
    }

    public function usage(Model $item): ?string
    {
        $types = StoneType::query()->whereHas('applications', fn (Builder $items) => $items->whereKey($item->getKey()))->count();

        return $types === 0 ? null : self::say('Used by :count. Take it off their applications first.', ['count' => self::count($types, 'stone type', 'stone types')]);
    }

    public function snapshot(Model $item): array
    {
        return ['name' => $item->name, 'archived_at' => $item->deleted_at?->toIso8601String()];
    }
}
