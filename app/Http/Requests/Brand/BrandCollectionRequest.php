<?php

namespace App\Http\Requests\Brand;

use App\Models\Size;
use App\Models\Thickness;
use Closure;
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
    private const STONE_FILTERS = ['type', 'size', 'thickness'];

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
            'type' => ['sometimes', 'nullable', 'string', Rule::exists('stone_types', 'slug')->where('deleted_at', null)->where('is_active', true)],
            // An option's key ("160x320-cm", "20-mm") that some stone on show comes in.
            'size' => ['bail', 'sometimes', 'nullable', 'string', 'max:30', $this->offeredOption(Size::class)],
            'thickness' => ['bail', 'sometimes', 'nullable', 'string', 'max:20', $this->offeredOption(Thickness::class)],
            'furniture_type' => ['sometimes', 'nullable', 'string', Rule::exists('furniture_types', 'name')->where('is_active', true)],
            'page' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * Whether the key names a size or thickness an active, listed stone comes
     * in: the same options filterOptions() offers. Any brand's stone counts,
     * as before, so a shared link stays valid on a brand with none in it.
     *
     * @param  class-string<Size|Thickness>  $model
     * @return Closure(string, mixed, Closure(string): mixed): void
     */
    private function offeredOption(string $model): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($model): void {
            if (! is_string($value) || ! $model::query()->matchingKey($value)->offered()->exists()) {
                $fail('That filter is no longer available.');
            }
        };
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
