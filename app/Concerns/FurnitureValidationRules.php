<?php

namespace App\Concerns;

use App\Models\Furniture;
use App\Support\ReservedSlugs;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The Add and Edit furniture forms' rules, shared by StoreFurnitureRequest
 * and UpdateFurnitureRequest.
 *
 * A piece's slug is found alone on the Saved door, so it is unique among
 * every piece, archived ones included (as the database holds it), and none
 * of the names Regray keeps for its own pages. The brand is one not
 * archived, and the type a furniture type not archived, an active one unless
 * the piece already has it. Its thumbnail is optional: a piece without one
 * shows the placeholder.
 */
trait FurnitureValidationRules
{
    use ProductImageRules;

    public const SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /** The piece being changed, or null when one is being added. */
    abstract protected function piece(): ?Furniture;

    protected function product(): ?Model
    {
        return $this->piece();
    }

    protected function needsThumbnail(): bool
    {
        return false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $piece = $this->piece();

        return [
            'brand_id' => ['required', 'integer', Rule::exists('brands', 'id')->whereNull('archived_at')],
            'furniture_type_id' => ['required', 'integer', Rule::exists('furniture_types', 'id')->whereNull('deleted_at')
                ->where(fn ($types) => $types->where('is_active', true)->orWhere('id', $piece->furniture_type_id ?? 0))],
            'name' => ['required', 'string', 'max:150'],
            'slug' => [
                'required', 'string', 'max:100', 'regex:'.self::SLUG_PATTERN,
                Rule::notIn(ReservedSlugs::ALL),
                Rule::unique('furniture', 'slug')->ignore($piece?->id),
            ],
            'description' => ['nullable', 'string', 'max:'.config('products.description_max')],
            'is_active' => ['required', 'boolean'],
            ...$this->imageRules(),
        ];
    }

    /** Trim what was typed and lower-case the slug. */
    protected function prepareForValidation(): void
    {
        $text = fn (mixed $value): mixed => is_string($value) ? trim($value) : $value;

        $this->merge([
            'name' => $text($this->input('name')),
            'slug' => is_string($this->input('slug')) ? mb_strtolower(trim($this->input('slug'))) : $this->input('slug'),
            'description' => $text($this->input('description')) === '' ? null : $text($this->input('description')),
        ]);

        $this->mergeGallery();
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'brand_id.required' => __('Choose the piece\'s brand.'),
            'brand_id.exists' => __('That brand is no longer on Regray. Choose another.'),
            'furniture_type_id.required' => __('Choose the piece\'s type.'),
            'furniture_type_id.exists' => __('That furniture type is no longer offered. Choose another.'),
            'name.required' => __('Enter the piece\'s name.'),
            'slug.required' => __('Enter the slug for its link.'),
            'slug.regex' => __('Use lower-case letters, numbers and single hyphens, such as arte-di-casa.'),
            'slug.not_in' => __('That slug is kept for one of Regray\'s own pages. Choose another.'),
            'slug.unique' => __('Another piece already uses that slug.'),
            'description.max' => __('Keep the description to :max characters.'),
            ...$this->imageMessages(),
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('slug')
                    && Furniture::query()->onlyTrashed()->where('slug', $this->input('slug'))->whereKeyNot($this->piece()->id ?? 0)->exists()) {
                    $validator->errors()->forget('slug');
                    $validator->errors()->add('slug', __('That slug belonged to an archived piece and can\'t be used again.'));
                }
            },
        ];
    }

    /**
     * The piece's fields, in the shape the service writes. The images are
     * read on their own (thumbnail(), gallery()).
     *
     * @return array{brand_id: int, furniture_type_id: int, name: string, slug: string, description: string|null, is_active: bool}
     */
    public function furnitureData(): array
    {
        $description = $this->validated('description');

        return [
            'brand_id' => (int) $this->validated('brand_id'),
            'furniture_type_id' => (int) $this->validated('furniture_type_id'),
            'name' => (string) $this->validated('name'),
            'slug' => (string) $this->validated('slug'),
            'description' => is_string($description) && $description !== '' ? $description : null,
            'is_active' => $this->boolean('is_active'),
        ];
    }
}
