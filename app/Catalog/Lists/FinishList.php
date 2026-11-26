<?php

namespace App\Catalog\Lists;

use App\Catalog\CatalogListDefinition;
use App\Models\Finish;
use App\Models\StoneType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Validator;

/**
 * Finishes, such as Honed, that a stone type lists and an inquiry asks
 * for. An advisory one ("Not sure yet, advise me") is a choice on the
 * Inquire page only. Archived only once no stone type lists it; past
 * inquiries keep it.
 *
 * @extends CatalogListDefinition<Finish>
 */
class FinishList extends CatalogListDefinition
{
    public bool $hasStatus = true;

    public bool $hasOrder = true;

    public function title(): string
    {
        return 'Finishes';
    }

    public function singular(): string
    {
        return 'finish';
    }

    public function subtitle(): string
    {
        return 'The finishes a stone type comes in and an inquiry asks for';
    }

    public function model(): string
    {
        return Finish::class;
    }

    public function query(?string $escapedSearch): Builder
    {
        return Finish::query()
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
            ['name' => 'description', 'label' => 'Description', 'type' => 'textarea', 'maxLength' => 255, 'hint' => 'One or two short sentences the Inquire page shows once a client picks it.'],
            ['name' => 'is_advisory', 'label' => 'An "advise me" choice', 'type' => 'switch', 'hint' => 'A choice for a client who is not sure yet, such as "Not sure yet, advise me". It is never shown on a product\'s page.'],
            self::orderField(),
            self::activeField('An inactive finish is not offered on the Inquire page or a stone\'s page.'),
        ];
    }

    public function rules(?Model $item): array
    {
        return [
            'name' => ['required', 'string', 'max:50', $this->uniqueIgnoringCase('name', $item, self::say('Another finish, maybe an archived one, already has that name.'))],
            'description' => ['nullable', 'string', 'max:255'],
            'is_advisory' => ['required', 'boolean'],
            ...$this->orderAndStatusRules(),
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => __('Enter the finish\'s name.'),
        ];
    }

    /**
     * A stone type's page never lists an "advise me" choice, so one a stone
     * type lists can't become one: the Stone types form would no longer offer
     * it, and every save of that type would be refused.
     */
    public function after(): \Closure
    {
        return function (Validator $validator, ?Model $item): void {
            if ($item === null || $validator->errors()->has('is_advisory') || ! filter_var($validator->getData()['is_advisory'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                return;
            }

            $types = StoneType::query()->whereHas('finishes', fn (Builder $items) => $items->whereKey($item->getKey()))->count();

            if ($types > 0) {
                $validator->errors()->add('is_advisory', self::say('Used by :count, whose pages list it. Take it off their finishes first.', ['count' => self::count($types, 'stone type', 'stone types')]));
            }
        };
    }

    public function name(Model $item): string
    {
        return $item->name;
    }

    public function row(Model $item): array
    {
        return [
            'cells' => ['advisory' => $item->is_advisory ? __('Advise me') : __('Finish'), 'types' => self::count((int) $item->getAttribute('stone_types_count'), 'stone type', 'stone types'), 'order' => (string) $item->sort_order],
            'values' => ['name' => $item->name, 'description' => (string) $item->description, 'is_advisory' => $item->is_advisory, 'sort_order' => (string) $item->sort_order, 'is_active' => $item->is_active],
        ];
    }

    public function fill(Model $item, array $data): void
    {
        $item->fill(['name' => $data['name'], 'description' => filled($data['description'] ?? null) ? $data['description'] : null, 'is_advisory' => filter_var($data['is_advisory'] ?? false, FILTER_VALIDATE_BOOLEAN)]);
        $this->fillOrderAndStatus($item, $data);
    }

    public function usage(Model $item): ?string
    {
        $types = StoneType::query()->whereHas('finishes', fn (Builder $items) => $items->whereKey($item->getKey()))->count();

        return $types === 0 ? null : self::say('Used by :count. Take it off their finishes first.', ['count' => self::count($types, 'stone type', 'stone types')]);
    }

    public function snapshot(Model $item): array
    {
        return ['name' => $item->name, 'description' => $item->description, 'is_advisory' => $item->is_advisory, 'sort_order' => $item->sort_order, 'is_active' => $item->is_active, 'archived_at' => $item->deleted_at?->toIso8601String()];
    }
}
