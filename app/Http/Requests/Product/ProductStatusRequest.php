<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

/** A row's switch: turn the product or list item on (`active: true`) or off. */
class ProductStatusRequest extends FormRequest
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
            'active' => ['required', 'boolean'],
        ];
    }
}
