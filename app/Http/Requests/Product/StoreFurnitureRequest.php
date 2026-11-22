<?php

namespace App\Http\Requests\Product;

use App\Concerns\FurnitureValidationRules;
use App\Models\Furniture;
use Illuminate\Foundation\Http\FormRequest;

/** Add Furniture: a new one from the Products page. */
class StoreFurnitureRequest extends FormRequest
{
    use FurnitureValidationRules;

    /** The route middleware decides access before this request is resolved. */
    public function authorize(): bool
    {
        return true;
    }

    protected function piece(): ?Furniture
    {
        return null;
    }
}
