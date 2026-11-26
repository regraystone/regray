<?php

namespace App\Catalog\Lists;

use App\Catalog\CatalogListDefinition;
use App\Models\Service;
use App\Models\ServiceType;
use App\Support\Slugs;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Service types, such as Natural Stone & Applications: the first choice of
 * "What do you need?" on the Inquire page, which decides the services offered
 * after it. A hidden type offers none of its services.
 *
 * Its slug is made from the name when it is added and never changes, as the
 * Inquire page reaches one type by slug. Archiving sets `archived_at` and
 * switches it off, once none of its services is left on the Services page
 * (they are moved or archived first, so none goes with it unseen); the row
 * stays, as sent inquiries reference its services.
 *
 * @extends CatalogListDefinition<ServiceType>
 */
class ServiceTypeList extends CatalogListDefinition
{
    public bool $hasStatus = true;

    public bool $hasOrder = true;

    public function title(): string
    {
        return 'Service types';
    }

    public function singular(): string
    {
        return 'service type';
    }

    public function subtitle(): string
    {
        return 'The first choice of "What do you need?" on the Inquire page';
    }

    public function model(): string
    {
        return ServiceType::class;
    }

    public function live(): Builder
    {
        return ServiceType::query()->notArchived();
    }

    public function archiveItem(Model $item): void
    {
        $item->forceFill(['is_active' => false, 'archived_at' => now()])->save();
    }

    public function query(?string $escapedSearch): Builder
    {
        return $this->live()
            ->when($escapedSearch !== null, fn (Builder $query) => $query->where('name', 'ILIKE', "%{$escapedSearch}%"))
            ->withCount(['services' => fn (Builder $services) => $services->whereNull('services.archived_at')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id');
    }

    public function columns(): array
    {
        return [['key' => 'services', 'header' => 'Services'], ['key' => 'order', 'header' => 'Order']];
    }

    public function fields(): array
    {
        return [
            ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'maxLength' => 150, 'hint' => 'Such as Custom Stone Furniture.'],
            self::orderField(),
            self::activeField('While hidden, none of its services is offered.', 'Visible on Inquire'),
        ];
    }

    public function rules(?Model $item): array
    {
        return [
            'name' => ['required', 'string', 'max:150', Rule::unique('service_types', 'name')->ignore($item?->getKey())],
            ...$this->orderAndStatusRules(),
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => __('Enter the service type\'s name.'),
            'name.unique' => __('There is already a service type by that name, or an archived one had it.'),
        ];
    }

    public function statusWords(): array
    {
        return ['header' => 'Visible', 'on' => 'Visible', 'off' => 'Hidden', 'turnOn' => 'Show', 'turnOff' => 'Hide'];
    }

    public function statusMessage(Model $item, bool $active): string
    {
        return $active
            ? self::say(':name is visible on the Inquire page again.', ['name' => $item->name])
            : self::say(':name is hidden from the Inquire page, its services with it.', ['name' => $item->name]);
    }

    public function archiveDescription(): string
    {
        return self::say('It leaves this list and the Inquire page; past inquiries keep it. A service type with services left can\'t be archived.');
    }

    public function emptyDescription(): string
    {
        return self::say('Add one, such as Natural Stone & Applications, then the services under it.');
    }

    public function name(Model $item): string
    {
        return $item->name;
    }

    public function row(Model $item): array
    {
        return [
            'cells' => ['services' => self::count((int) $item->getAttribute('services_count'), 'service', 'services'), 'order' => (string) $item->sort_order],
            'values' => ['name' => $item->name, 'sort_order' => (string) $item->sort_order, 'is_active' => $item->is_active],
        ];
    }

    public function fill(Model $item, array $data): void
    {
        $item->fill(['name' => $data['name']]);

        if (! $item->exists) {
            $item->slug = Slugs::unique(ServiceType::query(), $data['name']);
        }

        $this->fillOrderAndStatus($item, $data);
    }

    public function usage(Model $item): ?string
    {
        $left = Service::query()->notArchived()->where('service_type_id', $item->getKey())->count();

        return $left === 0 ? null : self::say('Move or archive its :count first.', ['count' => self::count($left, 'service', 'services')]);
    }

    public function snapshot(Model $item): array
    {
        return ['name' => $item->name, 'slug' => $item->slug, 'is_active' => $item->is_active, 'sort_order' => $item->sort_order, 'archived_at' => $item->archived_at?->toIso8601String()];
    }
}
