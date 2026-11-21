<?php

namespace App\Http\Requests\Settings;

use App\Data\PhAddress;
use App\Http\Requests\Concerns\ValidatesPhAddress;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * One address in the client's address book, added or changed: its label
 * ("Home", "Office", "Site" or the client's own) and the address picked with
 * the address picker. Never typed: every level is a PSGC code, checked as a
 * place and against its parent (PhAddressService).
 */
class SaveClientAddressRequest extends FormRequest
{
    use ValidatesPhAddress;

    public const LABEL_MAX = 50;

    /** The route middleware decides access; the controller finds the address among the user's own. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:'.self::LABEL_MAX],
            ...$this->phAddressRules('address'),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'label.required' => 'Give this address a label, like Home or Office.',
            'label.max' => 'Keep the label to '.self::LABEL_MAX.' characters or fewer.',
            ...$this->phAddressMessages('address'),
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('label'))) {
            $this->merge(['label' => trim($this->input('label'))]);
        }
    }

    /** @return list<Closure(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('address') || $validator->errors()->has('address.*')) {
                    return;
                }

                $this->checkPhAddress($validator, 'address');
            },
        ];
    }

    public function label(): string
    {
        return (string) $this->validated('label');
    }

    public function address(): PhAddress
    {
        return $this->checkedAddress('address');
    }
}
