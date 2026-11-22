<?php

namespace App\Http\Requests\Product;

use App\Concerns\StoneValidationRules;
use App\Http\Controllers\Product\StoneAdminController;
use App\Models\Stone;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Edit Stone: the same form, its thumbnail kept unless a new one is sent, and
 * its gallery as the form now lists it. Sent as a POST with `_method=put`,
 * since a PUT can't carry files.
 */
class UpdateStoneRequest extends FormRequest
{
    use StoneValidationRules;

    /** The route middleware decides access before this request is resolved. */
    public function authorize(): bool
    {
        return true;
    }

    private ?Stone $stone = null;

    /** The one in the URL, still on the Products page, or a 404. */
    protected function stone(): ?Stone
    {
        return $this->stone ??= StoneAdminController::find((string) $this->route('id'));
    }
}
