<?php

namespace App\Catalog\Lists;

use App\Catalog\CatalogListDefinition;
use App\Http\Resources\ShowcaseCardResource;
use App\Models\Application;
use App\Models\CareTip;
use App\Models\EdgeProfile;
use App\Models\Finish;
use App\Models\Service;
use App\Models\Stone;
use App\Models\StoneType;
use App\Support\CatalogLocks;
use App\Support\ReservedSlugs;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Stone types: a stone's kind, such as Marble, with the description, finishes,
 * edge profiles,
 * applications and care tips every stone of the type shows on its
 * page, and the thumbnail its filter and the Inquire page show. Archived only
 * once no stone has it, as its own type or as one it is also listed under, and
 * no service is made from it. An archived brand's stones don't count: they are
 * history, and the Products page no longer shows them.
 *
 * @extends CatalogListDefinition<StoneType>
 */
class StoneTypeList extends CatalogListDefinition
{
    public bool $hasStatus = true;

    public bool $hasOrder = true;

    public ?string $imageDirectory = 'stone-types';

    public function title(): string
    {
        return 'Stone types';
    }

    public function singular(): string
    {
        return 'stone type';
    }

    public function subtitle(): string
    {
        return 'The kinds of stone, with the finishes, edge profiles, applications and care tips every stone of each kind shows';
    }

    public function model(): string
    {
        return StoneType::class;
    }

    public function query(?string $escapedSearch): Builder
    {
        return StoneType::query()
            ->when($escapedSearch !== null, fn (Builder $query) => $query->where('name', 'ILIKE', "%{$escapedSearch}%"))
            ->withCount(['finishes', 'edgeProfiles', 'applications', 'careTips'])
            ->addSelect(['stones_count' => Stone::query()->ofLiveBrand()->selectRaw('count(*)')->whereColumn('stone_type_id', 'stone_types.id')])
            ->with(['finishes:finishes.id,finishes.is_advisory', 'edgeProfiles:edge_profiles.id,edge_profiles.is_advisory', 'applications:applications.id', 'careTips:care_tips.id'])
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function columns(): array
    {
        return [
            ['key' => 'stones', 'header' => 'Stones'],
            ['key' => 'details', 'header' => 'On its stones\' pages'],
            ['key' => 'order', 'header' => 'Order'],
        ];
    }

    public function fields(): array
    {
        $options = fn (string $model, string $column): array => array_values($model::query()->orderBy($column)->get(['id', $column])
            ->map(fn (Model $item): array => ['id' => (int) $item->getKey(), 'label' => (string) $item->getAttribute($column)])->all());
        // An "advise me" choice is the Inquire page's alone; a stone's page never lists one.
        $choices = fn (string $model): array => array_values($model::query()->where('is_advisory', false)->orderBy('sort_order')->orderBy('name')->get(['id', 'name'])
            ->map(fn (Model $item): array => ['id' => (int) $item->getKey(), 'label' => (string) $item->getAttribute('name')])->all());

        return [
            ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'maxLength' => 150],
            ['name' => 'slug', 'label' => 'Slug', 'type' => 'slug', 'required' => true, 'maxLength' => 100, 'hint' => 'Its filter link on a brand\'s page, such as marble.'],
            ['name' => 'description', 'label' => 'Description', 'type' => 'textarea', 'required' => true, 'maxLength' => config('products.description_max'), 'hint' => 'Shown on every stone of the type.'],
            ['name' => 'thumbnail', 'label' => 'Thumbnail', 'type' => 'image', 'required' => true, 'hint' => 'The photo on the type\'s tile on a brand\'s page.'],
            ['name' => 'finishes', 'label' => 'Finishes', 'type' => 'multiselect', 'options' => $choices(Finish::class), 'hint' => 'In the order its stones\' pages list them.'],
            ['name' => 'edge_profiles', 'label' => 'Edge profiles', 'type' => 'multiselect', 'options' => $choices(EdgeProfile::class), 'hint' => 'The edges its stones can be cut with, in the order their pages list them.'],
            ['name' => 'applications', 'label' => 'Applications', 'type' => 'multiselect', 'options' => $options(Application::class, 'name'), 'hint' => 'Where it suits, such as Kitchen countertops, in the order its stones\' pages list them.'],
            ['name' => 'care_tips', 'label' => 'Care tips', 'type' => 'multiselect', 'options' => $options(CareTip::class, 'tip'), 'hint' => 'How to care for it, in the order its stones\' pages list them.'],
            self::orderField(),
            self::activeField('An inactive type is hidden from the filters and the Inquire page.'),
        ];
    }

    public function rules(?Model $item): array
    {
        return [
            'name' => ['required', 'string', 'max:150', $this->uniqueIgnoringCase('name', $item, self::say('Another stone type already has that name.'), archivedToo: false)],
            'slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::notIn(ReservedSlugs::ALL), Rule::unique('stone_types', 'slug')->ignore($item?->id)],
            'description' => ['required', 'string', 'max:'.config('products.description_max')],
            'thumbnail' => [$item === null ? 'required' : 'nullable', 'file', 'mimes:'.implode(',', config('products.image_mimes')), 'max:'.config('products.thumbnail_max_kb')],
            'finishes' => ['nullable', 'array'],
            'finishes.*' => ['integer', 'distinct', Rule::exists('finishes', 'id')->whereNull('deleted_at')->where(fn ($query) => $query->where('is_advisory', false))],
            'applications' => ['nullable', 'array'],
            'applications.*' => ['integer', 'distinct', Rule::exists('applications', 'id')->whereNull('deleted_at')],
            'edge_profiles' => ['nullable', 'array'],
            'edge_profiles.*' => ['integer', 'distinct', Rule::exists('edge_profiles', 'id')->whereNull('deleted_at')->where(fn ($query) => $query->where('is_advisory', false))],
            'care_tips' => ['nullable', 'array'],
            'care_tips.*' => ['integer', 'distinct', Rule::exists('care_tips', 'id')->whereNull('deleted_at')],
            ...$this->orderAndStatusRules(),
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => __('Use lower-case letters, numbers and single hyphens, such as marble.'),
            'slug.not_in' => __('That slug is kept for one of Regray\'s own pages. Choose another.'),
            'slug.unique' => __('Another stone type, maybe an archived one, already uses that slug.'),
            'thumbnail.required' => __('Add the stone type\'s thumbnail.'),
            'thumbnail.mimes' => __('The thumbnail has to be a PNG, JPG or WebP.'),
            'thumbnail.max' => __('The thumbnail can be :max KB at most.'),
        ];
    }

    public function prepare(array $input): array
    {
        $input = parent::prepare($input);
        $input['slug'] = is_string($input['slug'] ?? null) ? mb_strtolower($input['slug']) : ($input['slug'] ?? null);

        return $input;
    }

    public function name(Model $item): string
    {
        return $item->name;
    }

    public function row(Model $item): array
    {
        $parts = array_filter([
            self::count((int) $item->getAttribute('finishes_count'), 'finish', 'finishes'),
            self::count((int) $item->getAttribute('edge_profiles_count'), 'edge', 'edges'),
            self::count((int) $item->getAttribute('applications_count'), 'application', 'applications'),
            self::count((int) $item->getAttribute('care_tips_count'), 'care tip', 'care tips'),
        ]);

        return [
            'thumbnail' => ShowcaseCardResource::resolveThumbnailUrl($item->thumbnail_path),
            'cells' => [
                'stones' => self::count((int) $item->getAttribute('stones_count'), 'stone', 'stones'),
                'details' => implode(' · ', $parts),
                'order' => (string) $item->sort_order,
            ],
            'values' => [
                'name' => $item->name,
                'slug' => $item->slug,
                'description' => $item->description,
                // An "advise me" one is not on the form to remove, so it is left off and the next save drops it.
                'finishes' => $item->finishes->where('is_advisory', false)->modelKeys(),
                'edge_profiles' => $item->edgeProfiles->where('is_advisory', false)->modelKeys(),
                'applications' => $item->applications->modelKeys(),
                'care_tips' => $item->careTips->modelKeys(),
                'sort_order' => (string) $item->sort_order,
                'is_active' => $item->is_active,
            ],
        ];
    }

    public function fill(Model $item, array $data): void
    {
        $item->fill([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'],
        ]);
        $this->fillOrderAndStatus($item, $data);
    }

    public function sync(Model $item, array $data): void
    {
        $ordered = function (string $key) use ($data): array {
            $rows = [];

            foreach (array_values((array) ($data[$key] ?? [])) as $order => $id) {
                $rows[(int) $id] = ['sort_order' => $order];
            }

            return $rows;
        };

        // Held, so none of them can be archived under the save (CatalogLocks).
        CatalogLocks::hold('finishes', array_keys($ordered('finishes')), 'finishes', self::say('One of those finishes is no longer offered. Remove it.'));
        CatalogLocks::hold('edge_profiles', array_keys($ordered('edge_profiles')), 'edge_profiles', self::say('One of those edge profiles is no longer offered. Remove it.'));
        CatalogLocks::hold('applications', array_keys($ordered('applications')), 'applications', self::say('One of those applications is no longer offered. Remove it.'));
        CatalogLocks::hold('care_tips', array_keys($ordered('care_tips')), 'care_tips', self::say('One of those care tips is no longer offered. Remove it.'));

        $item->finishes()->sync($ordered('finishes'));
        $item->edgeProfiles()->sync($ordered('edge_profiles'));
        $item->applications()->sync($ordered('applications'));
        $item->careTips()->sync($ordered('care_tips'));
    }

    public function usage(Model $item): ?string
    {
        $stones = Stone::query()->ofLiveBrand()
            ->where(fn (Builder $stones) => $stones->where('stone_type_id', $item->id)
                ->orWhereHas('types', fn (Builder $types) => $types->whereKey($item->id)))
            ->count();
        $services = Service::query()->notArchived()->whereHas('stoneTypes', fn (Builder $types) => $types->whereKey($item->id))->count();

        $reasons = array_filter([
            $stones === 0 ? null : self::say('Used by :count, as their type or one they are also listed under. Give them another type first.', ['count' => self::count($stones, 'stone', 'stones')]),
            $services === 0 ? null : self::say('The Services page makes :count from it. Take it off them first.', ['count' => self::count($services, 'service', 'services')]),
        ]);

        return $reasons === [] ? null : implode(' ', $reasons);
    }

    public function snapshot(Model $item): array
    {
        return [
            'name' => $item->name,
            'slug' => $item->slug,
            'description' => $item->description,
            'thumbnail_path' => $item->thumbnail_path,
            'finishes' => $item->finishes()->pluck('finishes.id')->all(),
            'edge_profiles' => $item->edgeProfiles()->pluck('edge_profiles.id')->all(),
            'applications' => $item->applications()->pluck('applications.id')->all(),
            'care_tips' => $item->careTips()->pluck('care_tips.id')->all(),
            'sort_order' => $item->sort_order,
            'is_active' => $item->is_active,
            'archived_at' => $item->deleted_at?->toIso8601String(),
        ];
    }
}
