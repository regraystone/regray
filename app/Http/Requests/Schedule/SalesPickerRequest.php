<?php

namespace App\Http\Requests\Schedule;

use Illuminate\Foundation\Http\FormRequest;

/** The sales picker's search. The route middleware decides access. */
class SalesPickerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function search(): ?string
    {
        $search = $this->validated('search');

        return is_string($search) ? $search : null;
    }
}
