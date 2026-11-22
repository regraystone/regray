<?php

namespace App\Concerns;

use App\Models\Showcase;
use App\Models\Stone;
use App\Support\ReservedSlugs;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The Add and Edit stone forms' rules, shared by StoreStoneRequest and
 * UpdateStoneRequest. The limits are the columns' (the stones migrations)
 * and config/products.php's.
 *
 * A stone's slug is its link everywhere a stone is found by slug alone (the
 * Saved door, an inquiry's stones), so it is unique among every stone,
 * archived ones included, none of the names Regray keeps for its own pages,
 * and no showcase's: a showcase's link `/{slug}` would find the stone. Its
 * name is unique among the stones on Regray, ignoring case, as the database
 * holds it.
 *
 * The brand is one not archived; the type and the other types it is listed
 * under are ones not archived, and the type an active one unless the stone
 * already has it. Its sizes are rows of one size (or none, for a stone sold
 * without set sizes, which `size_note` then describes) and one thickness,
 * each row one of its variants; a size and thickness come together once.
 */
trait StoneValidationRules
{
    use ProductImageRules;

    public const SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /** The stone being changed, or null when one is being added. */
    abstract protected function stone(): ?Stone;

    protected function product(): ?Model
    {
        return $this->stone();
    }

    protected function needsThumbnail(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $stone = $this->stone();

        return [
            'brand_id' => ['required', 'integer', Rule::exists('brands', 'id')->whereNull('archived_at')],
            'stone_type_id' => ['required', 'integer', Rule::exists('stone_types', 'id')->whereNull('deleted_at')
                ->where(fn ($types) => $types->where('is_active', true)->orWhere('id', $stone->stone_type_id ?? 0))],
            'types' => ['nullable', 'array'],
            'types.*' => ['integer', 'distinct', Rule::exists('stone_types', 'id')->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:150'],
            'slug' => [
                'required', 'string', 'max:100', 'regex:'.self::SLUG_PATTERN,
                Rule::notIn(ReservedSlugs::ALL),
                Rule::unique('stones', 'slug')->ignore($stone?->id),
                Rule::unique('showcases', 'slug'),
            ],
            'origin' => ['required', 'string', 'max:255'],
            'size_note' => ['nullable', 'string', 'max:100'],
            'is_active' => ['required', 'boolean'],
            'variants' => ['required', 'array', 'min:1', 'max:'.config('products.sizes_max')],
            'variants.*' => ['array:size_id,thickness_id'],
            // A size and thickness come together once (after()); either alone can repeat.
            'variants.*.size_id' => ['nullable', 'integer', Rule::exists('sizes', 'id')->whereNull('deleted_at')],
            'variants.*.thickness_id' => ['required', 'integer', Rule::exists('thicknesses', 'id')->whereNull('deleted_at')],
            ...$this->imageRules(),
        ];
    }

    /** Trim what was typed, lower-case the slug, and number the lists from 0. */
    protected function prepareForValidation(): void
    {
        $text = fn (mixed $value): mixed => is_string($value) ? trim($value) : $value;
        $variants = $this->input('variants');

        $this->merge([
            'name' => $text($this->input('name')),
            'slug' => is_string($this->input('slug')) ? mb_strtolower(trim($this->input('slug'))) : $this->input('slug'),
            'origin' => $text($this->input('origin')),
            'size_note' => $text($this->input('size_note')) === '' ? null : $text($this->input('size_note')),
            'types' => is_array($this->input('types')) ? array_values($this->input('types')) : $this->input('types'),
            'variants' => is_array($variants) ? array_values(array_map(fn (mixed $row): mixed => is_array($row) ? [
                'size_id' => ($row['size_id'] ?? null) === '' ? null : ($row['size_id'] ?? null),
                'thickness_id' => ($row['thickness_id'] ?? null) === '' ? null : ($row['thickness_id'] ?? null),
            ] : $row, $variants)) : $variants,
        ]);

        $this->mergeGallery();
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'brand_id.required' => __('Choose the stone\'s brand.'),
            'brand_id.exists' => __('That brand is no longer on Regray. Choose another.'),
            'stone_type_id.required' => __('Choose the stone\'s type.'),
            'stone_type_id.exists' => __('That stone type is no longer offered. Choose another.'),
            'types.*.exists' => __('That stone type is no longer offered. Remove it.'),
            'types.*.distinct' => __('That stone type is already chosen.'),
            'name.required' => __('Enter the stone\'s name.'),
            'slug.required' => __('Enter the slug for its link.'),
            'slug.regex' => __('Use lower-case letters, numbers and single hyphens, such as bianco-carrara.'),
            'slug.not_in' => __('That slug is kept for one of Regray\'s own pages. Choose another.'),
            'slug.unique' => __('Another stone already uses that slug.'),
            'origin.required' => __('Enter where the stone comes from.'),
            'variants.required' => __('Add at least one size the stone comes in.'),
            'variants.min' => __('Add at least one size the stone comes in.'),
            'variants.max' => __('A stone can list :max sizes at most.'),
            'variants.*.size_id.exists' => __('That size is no longer offered. Choose another.'),
            'variants.*.thickness_id.required' => __('Choose the thickness.'),
            'variants.*.thickness_id.exists' => __('That thickness is no longer offered. Choose another.'),
            ...$this->imageMessages(),
        ];
    }

    /**
     * The name is unique among the stones on Regray, ignoring case; a slug
     * taken by an archived stone or a showcase says so; a size and thickness
     * are listed together once.
     *
     * @return list<\Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $name = $this->input('name');

                if (is_string($name) && ! $validator->errors()->has('name') && Stone::query()
                    ->whereRaw('lower(name) = ?', [mb_strtolower($name)])
                    ->whereKeyNot($this->stone()->id ?? 0)
                    ->exists()) {
                    $validator->errors()->add('name', __('Another stone already has that name.'));
                }

                if (! $validator->errors()->has('slug')) {
                    return;
                }

                $slug = $this->input('slug');
                $message = match (true) {
                    Showcase::query()->withTrashed()->where('slug', $slug)->exists() => __('A showcase already uses that slug. Choose another.'),
                    Stone::query()->onlyTrashed()->where('slug', $slug)->whereKeyNot($this->stone()->id ?? 0)->exists() => __('That slug belonged to an archived stone and can\'t be used again.'),
                    default => null,
                };

                if (is_string($message)) {
                    $validator->errors()->forget('slug');
                    $validator->errors()->add('slug', $message);
                }
            },
            function (Validator $validator): void {
                $seen = [];

                foreach ((array) $this->input('variants', []) as $index => $row) {
                    if (! is_array($row) || ! is_scalar($row['thickness_id'] ?? null)) {
                        continue;
                    }

                    $pair = (is_scalar($row['size_id'] ?? null) ? (string) $row['size_id'] : 'none').'x'.$row['thickness_id'];

                    if (isset($seen[$pair])) {
                        $validator->errors()->add("variants.{$index}.thickness_id", __('That size and thickness are already listed.'));
                    }

                    $seen[$pair] = true;
                }
            },
        ];
    }

    /**
     * The stone's fields, types and sizes, in the shape the service writes.
     * The images are read on their own (thumbnail(), gallery()).
     *
     * @return array{brand_id: int, stone_type_id: int, name: string, slug: string, origin: string, size_note: string|null, is_active: bool, types: list<int>, variants: list<array{size_id: int|null, thickness_id: int}>}
     */
    public function stoneData(): array
    {
        /** @var list<array{size_id: mixed, thickness_id: mixed}> $variants */
        $variants = $this->validated('variants');
        $note = $this->validated('size_note');

        return [
            'brand_id' => (int) $this->validated('brand_id'),
            'stone_type_id' => (int) $this->validated('stone_type_id'),
            'name' => (string) $this->validated('name'),
            'slug' => (string) $this->validated('slug'),
            'origin' => (string) $this->validated('origin'),
            'size_note' => is_string($note) && $note !== '' ? $note : null,
            'is_active' => $this->boolean('is_active'),
            'types' => array_values(array_map(fn (mixed $id): int => (int) $id, $this->validated('types') ?? [])),
            'variants' => array_map(fn (array $row): array => [
                'size_id' => $row['size_id'] === null ? null : (int) $row['size_id'],
                'thickness_id' => (int) $row['thickness_id'],
            ], $variants),
        ];
    }
}
