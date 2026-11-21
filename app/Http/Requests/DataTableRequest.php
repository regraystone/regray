<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator as ValidatorFactory;

/**
 * The query a DataTable sends: `search` and `page`. Every DataTable shows a
 * fixed 10 rows a page, as resources/js/lib/data-table.ts does, so a
 * `perPage` in the query is ignored. A bad value on a list page is not worth
 * a page of its own: it falls back to its default.
 */
class DataTableRequest extends FormRequest
{
    public const DEFAULT_PAGE_SIZE = 10;

    /** The route middleware decides access before this request is resolved. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    protected function failedValidation(Validator $validator): void {}

    /** The search, escaped for ILIKE, or null when there is none. */
    public function escapedSearch(): ?string
    {
        $search = trim((string) ($this->valid()['search'] ?? ''));

        return $search === '' ? null : str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $search);
    }

    public function page(): int
    {
        return (int) ($this->valid()['page'] ?? 1);
    }

    /** Always DEFAULT_PAGE_SIZE: there is no other page size to ask for. */
    public function perPage(): int
    {
        return self::DEFAULT_PAGE_SIZE;
    }

    /**
     * The filters as the page holds them.
     *
     * @return array{search: string, page: int, perPage: int}
     */
    public function filters(): array
    {
        return [
            'search' => trim((string) ($this->valid()['search'] ?? '')),
            'page' => $this->page(),
            'perPage' => $this->perPage(),
        ];
    }

    /** @return array<string, mixed> */
    private function valid(): array
    {
        return ValidatorFactory::make($this->query(), $this->rules())->valid();
    }
}
