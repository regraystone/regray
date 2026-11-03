<?php

namespace App\Http\Requests\Furniture;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class FurnitureShowRequest extends FormRequest
{
    /** The route's permission middleware settles access before this request is resolved. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array{furniture: mixed} */
    public function validationData(): array
    {
        return ['furniture' => $this->route('furniture')];
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'furniture' => ['required', 'string', 'max:100', 'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/'],
        ];
    }

    /** Malformed slugs must look like unknown ones, not redirect with validation errors. */
    protected function failedValidation(Validator $validator): void
    {
        throw new NotFoundHttpException;
    }
}
