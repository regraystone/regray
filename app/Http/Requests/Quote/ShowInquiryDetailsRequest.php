<?php

namespace App\Http\Requests\Quote;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ShowInquiryDetailsRequest extends FormRequest
{
    /** The route middleware decides access before this request is resolved. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array{reference: mixed} */
    public function validationData(): array
    {
        return ['reference' => $this->route('reference')];
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'reference' => ['required', 'string', 'max:30', 'regex:/\A[A-Za-z0-9-]+\z/'],
        ];
    }

    /** A malformed reference must look like an unknown one. */
    protected function failedValidation(Validator $validator): void
    {
        throw new NotFoundHttpException;
    }
}
