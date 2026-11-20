<?php

namespace App\Http\Requests\Project;

use App\Concerns\ProjectValidationRules;
use App\Http\Controllers\Project\ProjectAdminController;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Edit project: the same form, its thumbnail kept unless a new one is sent,
 * and its gallery as the form now lists it. Sent as a POST with
 * `_method=put`, since a PUT can't carry files.
 */
class UpdateProjectRequest extends FormRequest
{
    use ProjectValidationRules;

    private ?Project $project = null;

    /** The route middleware decides access before this request is resolved. */
    public function authorize(): bool
    {
        return true;
    }

    /** The project in the URL, one still on the Projects page, or a 404. */
    protected function project(): ?Project
    {
        return $this->project ??= ProjectAdminController::find((string) $this->route('id'));
    }
}
