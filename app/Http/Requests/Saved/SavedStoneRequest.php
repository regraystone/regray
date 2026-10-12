<?php

namespace App\Http\Requests\Saved;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SavedStoneRequest extends FormRequest
{
    /** The route middleware decides access before this request is resolved. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array{stone: string} */
    public function validationData(): array
    {
        return ['stone' => $this->route('stone')];
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'stone' => ['required', 'string', 'max:100', 'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/'],
        ];
    }

    /** Malformed slugs must look like unknown ones, not redirect with validation errors. */
    protected function failedValidation(Validator $validator): void
    {
        throw new NotFoundHttpException;
    }
}
