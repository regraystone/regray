<?php

namespace App\Http\Requests\Settings;

use App\Enums\Permission;
use App\Http\Requests\Concerns\ValidatesClientInformation;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The Profile page's save of the signed-in client's own delivery choice and
 * site. There is no user id in the payload: the row is always the reader's, and
 * the route's permission has already answered who may write it.
 *
 * @phpstan-import-type ClientInformationInput from \App\Http\Requests\Concerns\ValidatesClientInformation
 */
class UpdateClientInformationRequest extends FormRequest
{
    use ValidatesClientInformation;

    public function authorize(): bool
    {
        return $this->user()?->can(Permission::ProfileClientInformationView->value) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return $this->clientInformationRules();
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return $this->clientInformationMessages();
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeClientInformation();
    }

    /**
     * @return ClientInformationInput
     */
    public function informationInput(): array
    {
        return $this->validatedClientInformation();
    }
}
