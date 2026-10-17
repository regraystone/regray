<?php

namespace App\Http\Requests\Saved;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProjectShowRequest extends FormRequest
{
    /** The route's permission middleware settles access before this request is resolved. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array{stone: mixed, project: mixed} */
    public function validationData(): array
    {
        return ['stone' => $this->route('stone'), 'project' => $this->route('project')];
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'stone' => ['required', 'string', 'max:100', 'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/'],
            'project' => ['required', 'string', 'max:100', 'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/'],
        ];
    }

    /** A malformed slug must look like an unknown one, not redirect with validation errors. */
    protected function failedValidation(Validator $validator): void
    {
        throw new NotFoundHttpException;
    }
}
