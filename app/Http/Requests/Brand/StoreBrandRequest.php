<?php

namespace App\Http\Requests\Brand;

use App\Concerns\BrandValidationRules;
use App\Models\Brand;
use Illuminate\Foundation\Http\FormRequest;

/** Add brand: a new brand, its logo required. */
class StoreBrandRequest extends FormRequest
{
    use BrandValidationRules;

    /** The route middleware decides access before this request is resolved. */
    public function authorize(): bool
    {
        return true;
    }

    protected function brand(): ?Brand
    {
        return null;
    }
}
