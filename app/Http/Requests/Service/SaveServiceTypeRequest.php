<?php

namespace App\Http\Requests\Service;

use App\Http\Controllers\Service\ServiceAdminController;
use App\Models\ServiceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Add or edit a service type: the first choice of "What do you need?" on the
 * Inquire page. Its name is unique among every type, archived ones included
 * (the column is). Its slug is made from the name when it is added and never
 * changes, because the Inquire page reaches one type by slug.
 */
class SaveServiceTypeRequest extends FormRequest
{
    private ?ServiceType $type = null;

    /** The route middleware decides access before this request is resolved. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150', Rule::unique('service_types', 'name')->ignore($this->serviceType()?->id)],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:32767'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $name = $this->input('name');

        $this->merge(['name' => is_string($name) ? trim($name) : $name]);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => __('Enter the service type\'s name.'),
            'name.unique' => __('There is already a service type by that name, or an archived one had it.'),
            'sort_order.integer' => __('Enter a whole number.'),
            'sort_order.min' => __('Enter 0 or more.'),
        ];
    }

    /** The type in the URL when one is being changed, or null when one is being added. */
    public function serviceType(): ?ServiceType
    {
        $id = $this->route('id');

        return $id === null ? null : $this->type ??= ServiceAdminController::findServiceType((string) $id);
    }

    /** @return array{name: string, is_active: bool, sort_order: int} */
    public function typeData(): array
    {
        return [
            'name' => (string) $this->validated('name'),
            'is_active' => $this->boolean('is_active'),
            'sort_order' => (int) $this->validated('sort_order'),
        ];
    }
}
