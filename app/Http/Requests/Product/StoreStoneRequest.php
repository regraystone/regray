<?php

namespace App\Http\Requests\Product;

use App\Concerns\StoneValidationRules;
use App\Models\Stone;
use Illuminate\Foundation\Http\FormRequest;

/** Add Stone: a new one from the Products page. */
class StoreStoneRequest extends FormRequest
{
    use StoneValidationRules;

    /** The route middleware decides access before this request is resolved. */
    public function authorize(): bool
    {
        return true;
    }

    protected function stone(): ?Stone
    {
        return null;
    }
}
