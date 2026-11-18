<?php

namespace App\Http\Requests\Inquiry;

use App\Http\Requests\Concerns\ValidatesClientInformation;

/**
 * The Inquire page's answers when a client sends their own inquiry. The
 * project rules are the base request's, untouched: staff lead and direct
 * flows, and both edit requests, inherit that one and gain no new field.
 *
 * The delivery answer already on the project is sent under `clientInformation`
 * too, with a flag saying whether to keep it on the Profile. A pickup carries
 * no site. There is no second site field here: it is the same answer the
 * project was validated with.
 *
 * @phpstan-import-type ProjectInput from \App\Services\InquiryService
 *
 * @phpstan-type ClientInput array{deliveryMethod: string, location: string|null, saveToProfile: bool}
 */
class StoreClientInquiryRequest extends StoreInquiryRequest
{
    use ValidatesClientInformation;

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'clientInformation' => ['required', 'array'],
            ...$this->clientInformationRules('clientInformation.'),
            'clientInformation.saveToProfile' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            ...$this->clientInformationMessages('clientInformation.'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeClientInformation('clientInformation');
    }

    /**
     * The service's write shape: the base projects plus the client's own
     * delivery answer, with the save choice read as a real boolean.
     *
     * @return array{projects: list<ProjectInput>, clientInformation: ClientInput}
     */
    public function inquiryInput(): array
    {
        /** @var ClientInput $values */
        $values = [
            ...$this->validatedClientInformation('clientInformation'),
            'saveToProfile' => filter_var($this->validated('clientInformation.saveToProfile', false), FILTER_VALIDATE_BOOLEAN),
        ];

        /** @var array{projects: list<ProjectInput>, clientInformation: ClientInput} */
        return [
            ...parent::inquiryInput(),
            'clientInformation' => $values,
        ];
    }
}
