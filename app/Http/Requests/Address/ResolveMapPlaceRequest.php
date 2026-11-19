<?php

namespace App\Http\Requests\Address;

use Illuminate\Foundation\Http\FormRequest;

/**
 * What the map knows of a point: Mapbox's names for it, lowest first
 * (`names[]`), and its postal code when it has one.
 */
class ResolveMapPlaceRequest extends FormRequest
{
    /** The route middleware decides access before this request is resolved. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'names' => ['required', 'array', 'min:1', 'max:8'],
            'names.*' => ['required', 'string', 'max:150'],
            'postalCode' => ['nullable', 'string', 'regex:/^[0-9]{4}$/'],
        ];
    }

    /** @return list<string> */
    public function names(): array
    {
        /** @var list<string> */
        return array_values($this->validated('names'));
    }

    public function postalCode(): ?string
    {
        $code = $this->validated('postalCode');

        return is_string($code) ? $code : null;
    }
}
