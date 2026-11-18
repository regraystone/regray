<?php

namespace App\Http\Requests\Concerns;

use App\Enums\DeliveryMethod;
use Illuminate\Validation\Rule;

/**
 * The two "Client Information" fields, shared by the Profile save and the
 * client's own inquiry send so the two requests cannot drift apart.
 *
 * Both callers normalize the same way and use the same location list from
 * `config/inquiry.php`. The inquiry request nests the fields under
 * `clientInformation`; the Profile request sends them at the root.
 *
 * @phpstan-type ClientInformationInput array{deliveryMethod: string, location: string|null}
 */
trait ValidatesClientInformation
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function clientInformationRules(string $prefix = ''): array
    {
        return [
            $prefix.'deliveryMethod' => ['required', Rule::enum(DeliveryMethod::class)],
            $prefix.'location' => [
                'nullable',
                'required_if:'.$prefix.'deliveryMethod,'.DeliveryMethod::Delivery->value,
                'string',
                Rule::in((array) config('inquiry.locations')),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function clientInformationMessages(string $prefix = ''): array
    {
        return [
            $prefix.'deliveryMethod.required' => 'Choose how you would like to get it.',
            $prefix.'deliveryMethod.Illuminate\Validation\Rules\Enum' => 'Choose how you would like to get it.',
            $prefix.'location.required_if' => 'Choose where the site is.',
            $prefix.'location.in' => 'Choose a city or municipality from the list.',
        ];
    }

    /**
     * Trim both answers. An unanswered site is null, and a pickup never keeps a
     * site even if one was sent with it: the question is only asked for a
     * delivery. A null `$key` means the fields sit at the root of the request;
     * otherwise they sit under that key. Only the keys that changed are merged
     * back, so a multipart request's projects and files stay as they were.
     */
    protected function normalizeClientInformation(?string $key = null): void
    {
        $source = $key === null ? $this->all() : $this->input($key);

        if (! is_array($source)) {
            return;
        }

        $changed = [];

        foreach (['deliveryMethod', 'location'] as $field) {
            if (isset($source[$field]) && is_string($source[$field])) {
                $changed[$field] = trim($source[$field]);
            }
        }

        if (($changed['location'] ?? null) === '') {
            $changed['location'] = null;
        }

        if (($changed['deliveryMethod'] ?? null) === DeliveryMethod::Pickup->value) {
            $changed['location'] = null;
        }

        if ($changed === []) {
            return;
        }

        $this->merge($key === null ? $changed : [$key => [...$source, ...$changed]]);
    }

    /**
     * The validated two fields, in the shape the service writes.
     *
     * @return ClientInformationInput
     */
    protected function validatedClientInformation(?string $key = null): array
    {
        $source = $key === null ? $this->validated() : $this->validated($key);

        if (! is_array($source)) {
            $source = [];
        }

        $method = (string) ($source['deliveryMethod'] ?? '');

        return [
            'deliveryMethod' => $method,
            'location' => $method === DeliveryMethod::Pickup->value
                ? null
                : (isset($source['location']) && is_string($source['location']) && $source['location'] !== ''
                    ? $source['location']
                    : null),
        ];
    }
}
