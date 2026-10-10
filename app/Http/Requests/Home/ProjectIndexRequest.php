<?php

namespace App\Http\Requests\Home;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class ProjectIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $input = $this->query->all();

        foreach (['search', 'stone', 'category', 'page'] as $key) {
            if (array_key_exists($key, $input) && $input[$key] === '') {
                unset($input[$key]);
            }
        }

        if (isset($input['search']) && is_string($input['search'])) {
            $trimmed = trim($input['search']);
            $input['search'] = $trimmed === '' ? null : $trimmed;
        }

        if (isset($input['page'])) {
            $input['page'] = (string) $input['page'];
        }

        $this->replace($input);
        $this->query->replace($input);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'stone' => ['sometimes', 'nullable', 'string', Rule::exists('stones', 'slug')->where('deleted_at', null)->where('is_active', true)],
            'category' => ['sometimes', 'nullable', 'string', Rule::exists('project_categories', 'slug')->where('is_active', true)],
            'page' => ['sometimes', 'nullable', 'integer', 'min:1'],
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
