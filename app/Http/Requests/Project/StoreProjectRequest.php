<?php

namespace App\Http\Requests\Project;

use App\Concerns\ProjectValidationRules;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;

/** Add project: a new project, its thumbnail required. */
class StoreProjectRequest extends FormRequest
{
    use ProjectValidationRules;

    /** The route middleware decides access before this request is resolved. */
    public function authorize(): bool
    {
        return true;
    }

    protected function project(): ?Project
    {
        return null;
    }
}
