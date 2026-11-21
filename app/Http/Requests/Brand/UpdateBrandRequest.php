<?php

namespace App\Http\Requests\Brand;

use App\Concerns\BrandValidationRules;
use App\Http\Controllers\Brand\BrandAdminController;
use App\Models\Brand;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Edit brand: the same form, its current images kept unless a new one is sent
 * or the thumbnail is removed. Sent as a POST with `_method=put`, since a PUT
 * can't carry files.
 */
class UpdateBrandRequest extends FormRequest
{
    use BrandValidationRules;

    private ?Brand $brand = null;

    /** The route middleware decides access before this request is resolved. */
    public function authorize(): bool
    {
        return true;
    }

    /** The brand in the URL, one still on the Brands page, or a 404. */
    protected function brand(): ?Brand
    {
        return $this->brand ??= BrandAdminController::find((string) $this->route('id'));
    }
}
