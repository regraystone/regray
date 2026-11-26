<?php

namespace App\Http\Requests\Product;

use App\Concerns\FurnitureValidationRules;
use App\Http\Controllers\Product\FurnitureAdminController;
use App\Models\Furniture;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Edit Furniture: the same form, its thumbnail kept unless a new one is sent, and
 * its gallery as the form now lists it. Sent as a POST with `_method=put`,
 * since a PUT can't carry files.
 */
class UpdateFurnitureRequest extends FormRequest
{
    use FurnitureValidationRules;

    /** The route middleware decides access before this request is resolved. */
    public function authorize(): bool
    {
        return true;
    }

    private ?Furniture $piece = null;

    /** The one in the URL, still on the Products page, or a 404. */
    protected function piece(): ?Furniture
    {
        return $this->piece ??= FurnitureAdminController::find((string) $this->route('id'));
    }
}
