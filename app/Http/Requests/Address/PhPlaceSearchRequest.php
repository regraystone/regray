<?php

namespace App\Http\Requests\Address;

use App\Support\PhPlaceName;
use Illuminate\Foundation\Http\FormRequest;

/**
 * One search of the address picker: the text typed (`q`), the page, and the
 * parent the places are under, which each endpoint names for itself
 * (`region`, `province`, `city`). A code is nine digits, as the PSGC writes it.
 */
class PhPlaceSearchRequest extends FormRequest
{
    public const PER_PAGE = 50;

    /** The route middleware decides access before this request is resolved. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        $code = ['nullable', 'string', 'regex:/^[0-9]{9}$/'];

        return [
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'region' => $code,
            'province' => $code,
            'city' => $code,
        ];
    }

    /** The LIKE pattern for `q`, or null when nothing worth searching was typed. */
    public function pattern(): ?string
    {
        $query = $this->validated('q');

        if (! is_string($query) || trim($query) === '') {
            return null;
        }

        $pattern = PhPlaceName::searchPattern($query);

        return $pattern === '%%' ? null : $pattern;
    }

    public function page(): int
    {
        return (int) ($this->validated('page') ?? 1);
    }

    public function code(string $key): ?string
    {
        $code = $this->validated($key);

        return is_string($code) ? $code : null;
    }
}
