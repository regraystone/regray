<?php

namespace App\Http\Requests\Brand;

use App\Models\Furniture;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class BrandCollectionRequest extends FormRequest
{
    public const COLLECTION_ALL = 'all';

    public const COLLECTION_STONES = 'stones';

    public const COLLECTION_FURNITURE = 'furniture';

    /** Stone filters only mean something in the stones collection. */
    private const STONE_FILTERS = ['category', 'size', 'thickness'];

    /** The route's permission middleware settles access before this request is resolved. */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $input = $this->query->all();

        // The page size is not a URL param any more, so an old link's is dropped.
        unset($input['per_page']);

        foreach (['collection', 'search', ...self::STONE_FILTERS, 'furniture_type', 'page'] as $key) {
            if (array_key_exists($key, $input) && $input[$key] === '') {
                unset($input[$key]);
            }
        }

        if (isset($input['search']) && is_string($input['search'])) {
            $trimmed = trim($input['search']);
            $input['search'] = $trimmed === '' ? null : $trimmed;
        }

        if (isset($input['page']) && is_scalar($input['page'])) {
            $input['page'] = (string) $input['page'];
        }

        $collection = $input['collection'] ?? self::COLLECTION_ALL;

        // A filter from another collection is ignored rather than refused.
        if ($collection !== self::COLLECTION_STONES) {
            $input = array_diff_key($input, array_flip(self::STONE_FILTERS));
        }

        if ($collection !== self::COLLECTION_FURNITURE) {
            unset($input['furniture_type']);
        }

        $this->replace($input);
        $this->query->replace($input);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'collection' => ['sometimes', 'nullable', 'string', Rule::in([self::COLLECTION_ALL, self::COLLECTION_STONES, self::COLLECTION_FURNITURE])],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'category' => ['sometimes', 'nullable', 'string', Rule::exists('stone_categories', 'slug')->where('deleted_at', null)->where('is_active', true)],
            'size' => ['sometimes', 'nullable', 'string', 'max:20', Rule::exists('stone_sizes', 'name')->where('is_active', true)],
            'thickness' => ['sometimes', 'nullable', 'string', Rule::exists('stone_thicknesses', 'slug')->where('is_active', true)],
            'furniture_type' => ['sometimes', 'nullable', 'string', Rule::in(Furniture::TYPES)],
            'page' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }

    /** A bad filter is dropped from the URL, staying on this brand's own guest or signed-in page. */
    protected function failedValidation(Validator $validator): void
    {
        $failed = array_keys($validator->failed());
        $query = http_build_query(collect($this->query->all())
            ->reject(fn ($value, string $key) => in_array($key, $failed, true))
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all());

        throw new HttpResponseException(
            redirect()->to($this->url().($query === '' ? '' : '?'.$query))
        );
    }
}
