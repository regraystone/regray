<?php

namespace App\Http\Requests\Service;

use App\Concerns\ServiceValidationRules;
use App\Models\Service;
use Illuminate\Foundation\Http\FormRequest;

/** Add service: a new service under one of the service types. */
class StoreServiceRequest extends FormRequest
{
    use ServiceValidationRules;

    /** The route middleware decides access before this request is resolved. */
    public function authorize(): bool
    {
        return true;
    }

    protected function service(): ?Service
    {
        return null;
    }
}
