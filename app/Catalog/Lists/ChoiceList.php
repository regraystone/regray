<?php

namespace App\Catalog\Lists;

use App\Catalog\CatalogListDefinition;
use App\Http\Requests\Service\StoreServiceRequest;
use App\Http\Resources\ShowcaseCardResource;
use App\Models\Choice;
use App\Models\DetailField;
use App\Models\DetailFieldOption;
use App\Models\Service;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The library of choices the Inquire page's list questions pick from: each
 * written once, with what a question's list of names can't hold, and listed
 * by as many questions as need it (the Questions page picks them). A choice
 * has a description shown once it is picked, a picture (a question with a
 * pictured choice is asked with picture cards, as Table shape and the base
 * designs are), its kind (an ordinary choice, a custom one that asks the
 * client to describe it, "Not sure yet, advise me", or "Same as stone"), how
 * a table shape asks the size, and the services it is offered for (none
 * listed: every service that asks a question listing it). A change shows on
 * every question listing it (Choice::mirror()).
 *
 * Edge profiles and finishes keep their own pages. Archiving sets
 * `archived_at`, switches the choice off and takes it off every question;
 * past answers keep it.
 *
 * @extends CatalogListDefinition<Choice>
 */
class ChoiceList extends CatalogListDefinition
{
    public bool $hasStatus = true;

    public bool $hasOrder = true;

    public ?string $imageDirectory = 'question-choices';

    /** What each kind of choice is called, in the order the form offers them. */
    private const KINDS = [
        'choice' => 'A choice',
        'custom' => 'Custom: asks the client to describe it',
        'advisory' => 'Not sure yet, advise me',
    ];

    /** How a table shape asks the size, as the form offers it; `free` is no size kind. */
    private const SIZE_KINDS = [
        DetailFieldOption::SIZE_WIDTH_LENGTH => 'Width x length',
        DetailFieldOption::SIZE_DIAMETER => 'Diameter',
        'free' => 'In the client\'s own words',
    ];

    /** choices.description's length. */
    private const DESCRIPTION_MAX = 255;

    public function title(): string
    {
        return 'Choices';
    }

    public function singular(): string
    {
        return 'choice';
    }

    public function subtitle(): string
    {
        return 'The choices questions pick from, with their pictures, descriptions and services';
    }

    public function model(): string
    {
        return Choice::class;
    }

    public function live(): Builder
    {
        return Choice::query()->notArchived();
    }

    public function archiveItem(Model $item): void
    {
        $item->forceFill(['archived_at' => now(), 'is_active' => false])->save();
    }

    public function archiveDescription(): string
    {
        return self::say('It leaves the library and every question that lists it; past inquiries keep it.');
    }

    public function emptyDescription(): string
    {
        return self::say('Add a choice for questions to list.');
    }

    public function query(?string $escapedSearch): Builder
    {
        return $this->live()
            ->when($escapedSearch !== null, fn (Builder $query) => $query->where('choices.name', 'ILIKE', "%{$escapedSearch}%"))
            ->with(['services:services.id,services.name'])
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function columns(): array
    {
        return [
            ['key' => 'kind', 'header' => 'Kind'],
            ['key' => 'services', 'header' => 'Offered for'],
            ['key' => 'order', 'header' => 'Order'],
        ];
    }

    public function fields(): array
    {
        $services = Service::query()->notArchived()->with('serviceType:id,name')->orderBy('service_type_id')->orderBy('sort_order')->get(['id', 'name', 'service_type_id']);

        return [
            ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'maxLength' => StoreServiceRequest::CHOICE_MAX, 'hint' => 'As the client reads it, such as Round.'],
            ['name' => 'description', 'label' => 'Description', 'type' => 'textarea', 'maxLength' => self::DESCRIPTION_MAX, 'hint' => 'One or two short sentences the Inquire page shows once a client picks it.'],
            ['name' => 'thumbnail', 'label' => 'Picture', 'type' => 'image', 'hint' => 'A question with any pictured choice is asked with picture cards on the Inquire page.'],
            [
                'name' => 'kind',
                'label' => 'Kind',
                'type' => 'select',
                'required' => true,
                'hint' => 'A custom choice opens a box for the client to describe theirs. A question lists one of each at most.',
                'options' => array_map(fn (string $value, string $label): array => ['value' => $value, 'label' => $label], array_keys(self::KINDS), self::KINDS),
            ],
            [
                'name' => 'size_kind',
                'label' => 'Size asked as',
                'type' => 'select',
                'required' => true,
                'hint' => 'Where Table shape lists it: how the Inquire page then asks the table\'s size.',
                'options' => array_map(fn (string $value, string $label): array => ['value' => $value, 'label' => $label], array_keys(self::SIZE_KINDS), self::SIZE_KINDS),
                'showWhen' => ['field' => 'kind', 'equals' => 'choice'],
            ],
            [
                'name' => 'services',
                'label' => 'Offered for',
                'type' => 'multiselect',
                'hint' => 'Left empty, it is offered for every service that asks a question listing it.',
                'options' => array_values($services->map(fn (Service $service): array => ['id' => $service->id, 'label' => "{$service->name} ({$service->serviceType->name})"])->all()),
            ],
            self::orderField(),
            self::activeField('An inactive choice is not offered on the Inquire page.'),
        ];
    }

    public function rules(?Model $item): array
    {
        return [
            'name' => ['required', 'string', 'max:'.StoreServiceRequest::CHOICE_MAX, $this->uniqueIgnoringCase('name', $item, self::say('Another choice, maybe an archived one, already reads that way.'))],
            'description' => ['nullable', 'string', 'max:'.self::DESCRIPTION_MAX],
            'thumbnail' => ['nullable', 'file', 'mimes:'.implode(',', config('products.image_mimes')), 'max:'.config('products.thumbnail_max_kb')],
            'kind' => ['required', Rule::in(array_keys(self::KINDS))],
            'size_kind' => ['nullable', Rule::in(array_keys(self::SIZE_KINDS))],
            'services' => ['nullable', 'array'],
            'services.*' => ['integer', 'distinct', Rule::exists('services', 'id')->whereNull('archived_at')],
            ...$this->orderAndStatusRules(),
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => __('Enter the choice\'s name.'),
            'name.max' => __('A choice can be :max characters at most.'),
            'kind.required' => __('Choose what kind of choice it is.'),
            'thumbnail.mimes' => __('The picture has to be a PNG, JPG or WebP.'),
            'thumbnail.max' => __('The picture can be :max KB at most.'),
            'services.*.exists' => __('One of those services is no longer offered. Remove it.'),
        ];
    }

    /**
     * "Same as stone" stays an ordinary choice, and a question listing it
     * keeps one custom and one "advise me" choice at most.
     */
    public function after(): \Closure
    {
        return function (Validator $validator, ?Model $item): void {
            $kind = $validator->getData()['kind'] ?? null;

            if (! $item instanceof Choice || ! in_array($kind, ['choice', 'custom', 'advisory'], true)) {
                return;
            }

            if ($item->links_to_stone && $kind !== 'choice') {
                $validator->errors()->add('kind', self::say('"Same as stone" stays an ordinary choice.'));

                return;
            }

            if ($kind === 'choice' || $kind === $item->kind()) {
                return;
            }

            $column = $kind === 'custom' ? 'is_custom' : 'is_advisory';
            $crowded = DetailField::query()
                ->whereHas('options', fn (Builder $options) => $options->where('choice_id', $item->id)->whereNull('archived_at'))
                ->whereHas('options', fn (Builder $options) => $options->where($column, true)->whereNull('archived_at')->where(fn (Builder $other) => $other->whereNull('choice_id')->orWhere('choice_id', '!=', $item->id)))
                ->orderBy('sort_order')
                ->value('name');

            if ($crowded !== null) {
                $validator->errors()->add('kind', $kind === 'custom'
                    ? self::say(':question already lists a custom choice.', ['question' => $crowded])
                    : self::say(':question already lists a "Not sure yet, advise me" choice.', ['question' => $crowded]));
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
            'thumbnail' => filled($item->thumbnail_path) ? ShowcaseCardResource::resolveThumbnailUrl((string) $item->thumbnail_path) : null,
            'cells' => [
                'kind' => match ($item->kind()) {
                    'custom' => self::say('Custom'),
                    'advisory' => self::say('Advise me'),
                    'stone' => self::say('Same as stone'),
                    default => self::say('Choice'),
                },
                'services' => $item->services->isEmpty() ? self::say('Every service asking it') : $item->services->pluck('name')->implode(', '),
                'order' => (string) $item->sort_order,
            ],
            'values' => [
                'name' => $item->name,
                'description' => (string) $item->description,
                'kind' => $item->links_to_stone ? 'choice' : $item->kind(),
                'size_kind' => $item->size_kind ?? 'free',
                'services' => $item->services->modelKeys(),
                'sort_order' => (string) $item->sort_order,
                'is_active' => $item->is_active,
            ],
            'fixed' => $item->links_to_stone ? ['kind'] : [],
        ];
    }

    public function fill(Model $item, array $data): void
    {
        if (! $item->exists) {
            $item->fill(['slug' => Choice::uniqueSlug((string) $data['name']), 'links_to_stone' => false]);
        }

        $kind = (string) $data['kind'];
        $sizeKind = $data['size_kind'] ?? null;

        $item->fill([
            'name' => trim((string) $data['name']),
            'description' => filled($data['description'] ?? null) ? $data['description'] : null,
            'is_custom' => $kind === 'custom',
            'is_advisory' => $kind === 'advisory',
            'size_kind' => $kind === 'choice' && in_array($sizeKind, DetailFieldOption::SIZE_KINDS, true) ? $sizeKind : null,
        ]);

        $order = $data['sort_order'] ?? null;
        $item->setAttribute('sort_order', $order === null || $order === ''
            // A new one goes last in the library; one already in it keeps its place.
            ? ($item->exists ? $item->sort_order : (int) Choice::query()->max('sort_order') + 1)
            : (int) $order);
        $item->setAttribute('is_active', filter_var($data['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN));
    }

    public function sync(Model $item, array $data): void
    {
        $item->offerFor(array_values(array_map(intval(...), (array) ($data['services'] ?? []))));
    }

    /** Archiving keeps the row, so a sent answer still reads it. */
    public function usage(Model $item): ?string
    {
        return null;
    }

    public function snapshot(Model $item): array
    {
        return [
            'name' => $item->name,
            'slug' => $item->slug,
            'description' => $item->description,
            'thumbnail_path' => $item->thumbnail_path,
            'kind' => $item->kind(),
            'size_kind' => $item->size_kind,
            'services' => $item->services()->pluck('services.id')->all(),
            'questions' => $item->questions()->pluck('detail_fields.id')->all(),
            'sort_order' => $item->sort_order,
            'is_active' => $item->is_active,
            'archived_at' => $item->archived_at?->toIso8601String(),
        ];
    }
}
