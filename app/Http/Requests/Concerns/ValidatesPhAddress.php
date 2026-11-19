<?php

namespace App\Http\Requests\Concerns;

use App\Data\PhAddress;
use App\Services\PhAddressService;
use Illuminate\Validation\Validator;

/**
 * An address picked with the address picker, inside a form request: its codes
 * checked as types by rules(), then as places by checkPhAddress() in after(),
 * which keeps the address it built for the request to hand on.
 *
 * Errors land on the picker's own fields under the given key
 * (`address.cityCode`, `projects.0.address.barangayCode`).
 */
trait ValidatesPhAddress
{
    /** @var array<string, PhAddress> The addresses checked so far, by key. */
    private array $checkedAddresses = [];

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function phAddressRules(string $key, bool $required = true): array
    {
        $code = ['nullable', 'string', 'regex:/^[0-9]{9}$/'];

        return [
            $key => [$required ? 'required' : 'nullable', 'array:'.implode(',', PhAddressService::FIELDS)],
            "{$key}.regionCode" => $code,
            "{$key}.provinceCode" => $code,
            "{$key}.cityCode" => $code,
            "{$key}.barangayCode" => $code,
            "{$key}.postalCode" => ['nullable', 'string', 'regex:/^[0-9]{4}$/'],
            "{$key}.lat" => ['nullable', 'numeric'],
            "{$key}.lng" => ['nullable', 'numeric'],
        ];
    }

    /** @return array<string, string> */
    protected function phAddressMessages(string $key): array
    {
        return [
            "{$key}.required" => 'Choose the address.',
            "{$key}.array" => 'Choose the address.',
            "{$key}.regionCode.regex" => 'Choose the region from the list.',
            "{$key}.provinceCode.regex" => 'Choose the province from the list.',
            "{$key}.cityCode.regex" => 'Choose the city or municipality from the list.',
            "{$key}.barangayCode.regex" => 'Choose the barangay from the list.',
            "{$key}.postalCode.regex" => 'Choose the postal code from the list.',
            "{$key}.lat.numeric" => 'Place the pin in the Philippines.',
            "{$key}.lng.numeric" => 'Place the pin in the Philippines.',
        ];
    }

    /**
     * Check the address at `$key` as places, adding each problem to its field.
     * The address it names is kept for checkedAddress().
     */
    protected function checkPhAddress(Validator $validator, string $key): ?PhAddress
    {
        $input = $this->input($key);
        $result = app(PhAddressService::class)->check(is_array($input) ? $input : []);

        foreach ($result['errors'] as $field => $message) {
            $validator->errors()->add("{$key}.{$field}", $message);
        }

        if ($result['address'] !== null) {
            $this->checkedAddresses[$key] = $result['address'];
        }

        return $result['address'];
    }

    /** The address checkPhAddress() built for `$key`. Only after validation passed. */
    protected function checkedAddress(string $key): PhAddress
    {
        return $this->checkedAddresses[$key] ?? throw new \LogicException("The address at {$key} was not checked.");
    }
}
