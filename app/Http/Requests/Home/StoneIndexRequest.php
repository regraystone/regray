<?php

namespace App\Http\Requests\Home;

use App\Enums\HomeGridPageSize;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoneIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $input = $this->query->all();

        foreach (['search', 'category', 'size', 'thickness', 'page', 'per_page'] as $key) {
            if (array_key_exists($key, $input) && $input[$key] === '') {
                unset($input[$key]);
            }
        }

        if (isset($input['search']) && is_string($input['search'])) {
            $trimmed = trim($input['search']);
            $input['search'] = $trimmed === '' ? null : $trimmed;
        }

        foreach (['page', 'per_page'] as $key) {
            if (isset($input[$key]) && is_scalar($input[$key])) {
                $input[$key] = (string) $input[$key];
            }
        }

        $this->replace($input);
        $this->query->replace($input);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'category' => ['sometimes', 'nullable', 'string', Rule::exists('stone_categories', 'slug')->where('deleted_at', null)->where('is_active', true)],
            'size' => ['sometimes', 'nullable', 'string', 'max:20', Rule::exists('stone_sizes', 'name')->where('is_active', true)],
            'thickness' => ['sometimes', 'nullable', 'string', Rule::exists('stone_thicknesses', 'slug')->where('is_active', true)],
            'page' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'nullable', 'integer', Rule::enum(HomeGridPageSize::class)],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        $failed = array_keys($validator->failed());
        $valid = collect($this->query->all())
            ->reject(fn ($value, string $key) => in_array($key, $failed, true))
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();

        $query = http_build_query($valid);

        throw new HttpResponseException(
            redirect()->route('home.index', $query === '' ? [] : $valid)
        );
    }
}
