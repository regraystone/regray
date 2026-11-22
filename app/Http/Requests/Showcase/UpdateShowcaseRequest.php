<?php

namespace App\Http\Requests\Showcase;

use App\Concerns\ShowcaseValidationRules;
use App\Http\Controllers\Showcase\ShowcaseAdminController;
use App\Models\Showcase;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Edit showcase: the same form, its thumbnail kept unless a new one is sent,
 * and its gallery as the form now lists it. Sent as a POST with
 * `_method=put`, since a PUT can't carry files.
 */
class UpdateShowcaseRequest extends FormRequest
{
    use ShowcaseValidationRules;

    private ?Showcase $showcase = null;

    /** The route middleware decides access before this request is resolved. */
    public function authorize(): bool
    {
        return true;
    }

    /** The showcase in the URL, one still on the Showcase page, or a 404. */
    protected function showcase(): ?Showcase
    {
        return $this->showcase ??= ShowcaseAdminController::find((string) $this->route('id'));
    }
}
