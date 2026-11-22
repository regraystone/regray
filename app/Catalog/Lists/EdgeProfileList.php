<?php

namespace App\Catalog\Lists;

use App\Catalog\CatalogListDefinition;
use App\Models\EdgeProfile;
use App\Models\StoneType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Edge profiles, such as Bullnose, that a stone type lists (its stones'
 * pages show them) and an inquiry asks for. An advisory one ("Not sure yet,
 * advise me") is a choice on the Inquire page only. Archived only once no
 * stone type lists it; past inquiries keep it.
 *
 * @extends CatalogListDefinition<EdgeProfile>
 */
class EdgeProfileList extends CatalogListDefinition
{
    public bool $hasStatus = true;

    public bool $hasOrder = true;

    public function title(): string
    {
        return 'Edge profiles';
    }

    public function singular(): string
    {
        return 'edge profile';
    }

    public function subtitle(): string
    {
        return 'The edges a stone type is cut with and an inquiry asks for';
    }

    public function model(): string
    {
        return EdgeProfile::class;
    }

    public function query(?string $escapedSearch): Builder
    {
        return EdgeProfile::query()
            ->when($escapedSearch !== null, fn (Builder $query) => $query->where('name', 'ILIKE', "%{$escapedSearch}%"))
            ->withCount(['stoneTypes' => fn (Builder $types) => $types->whereNull('stone_types.deleted_at')])
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function columns(): array
    {
        return [['key' => 'advisory', 'header' => 'Kind'], ['key' => 'types', 'header' => 'Stone types'], ['key' => 'order', 'header' => 'Order']];
    }

    public function fields(): array
    {
        return [
            ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'maxLength' => 50],
            ['name' => 'is_advisory', 'label' => 'An "advise me" choice', 'type' => 'switch', 'hint' => 'A choice for a client who is not sure yet, such as "Not sure yet, advise me". It is never shown on a product\'s page.'],
            self::orderField(),
            self::activeField('An inactive edge profile is not offered on the Inquire page or a stone\'s page.'),
        ];
    }

    public function rules(?Model $item): array
    {
        return [
            'name' => ['required', 'string', 'max:50', Rule::unique('edge_profiles', 'name')->ignore($item?->getKey())],
            'is_advisory' => ['required', 'boolean'],
            ...$this->orderAndStatusRules(),
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => __('Enter the edge profile\'s name.'),
            'name.unique' => __('Another edge profile, maybe an archived one, already has that name.'),
        ];
    }

    public function name(Model $item): string
    {
        return $item->name;
    }

    public function row(Model $item): array
    {
        return [
            'cells' => ['advisory' => $item->is_advisory ? __('Advise me') : __('Edge'), 'types' => self::count((int) $item->getAttribute('stone_types_count'), 'stone type', 'stone types'), 'order' => (string) $item->sort_order],
            'values' => ['name' => $item->name, 'is_advisory' => $item->is_advisory, 'sort_order' => (string) $item->sort_order, 'is_active' => $item->is_active],
        ];
    }

    public function fill(Model $item, array $data): void
    {
        $item->fill(['name' => $data['name'], 'is_advisory' => filter_var($data['is_advisory'] ?? false, FILTER_VALIDATE_BOOLEAN)]);
        $this->fillOrderAndStatus($item, $data);
    }

    public function usage(Model $item): ?string
    {
        $types = StoneType::query()->whereHas('edgeProfiles', fn (Builder $items) => $items->whereKey($item->getKey()))->count();

        return $types === 0 ? null : self::say('Used by :count. Take it off their edge profiles first.', ['count' => self::count($types, 'stone type', 'stone types')]);
    }

    public function snapshot(Model $item): array
    {
        return ['name' => $item->name, 'is_advisory' => $item->is_advisory, 'sort_order' => $item->sort_order, 'is_active' => $item->is_active, 'archived_at' => $item->deleted_at?->toIso8601String()];
    }
}
