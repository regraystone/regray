<?php

namespace App\Http\Requests\Showcase;

use App\Concerns\ShowcaseValidationRules;
use App\Models\Showcase;
use Illuminate\Foundation\Http\FormRequest;

/** Add showcase: a new showcase, its thumbnail required. */
class StoreShowcaseRequest extends FormRequest
{
    use ShowcaseValidationRules;

    /** The route middleware decides access before this request is resolved. */
    public function authorize(): bool
    {
        return true;
    }

    protected function showcase(): ?Showcase
    {
        return null;
    }
}
