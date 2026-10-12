<?php

namespace App\Http\Requests\Saved;

use Illuminate\Foundation\Http\FormRequest;

class SavedIndexRequest extends FormRequest
{
    /** The route middleware decides access before this request is resolved. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, never> */
    public function rules(): array
    {
        return [];
    }
}
