<?php

namespace App\Http\Requests\Service;

use App\Concerns\ServiceValidationRules;
use App\Http\Controllers\Service\ServiceAdminController;
use App\Models\Service;
use Illuminate\Foundation\Http\FormRequest;

/** Edit service: the same form. Its slug never changes. */
class UpdateServiceRequest extends FormRequest
{
    use ServiceValidationRules;

    private ?Service $service = null;

    /** The route middleware decides access before this request is resolved. */
    public function authorize(): bool
    {
        return true;
    }

    /** The service in the URL, one still on the Services page, or a 404. */
    protected function service(): ?Service
    {
        return $this->service ??= ServiceAdminController::findService((string) $this->route('id'));
    }
}
