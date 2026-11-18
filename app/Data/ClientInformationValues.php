<?php

namespace App\Data;

use App\Enums\DeliveryMethod;
use App\Models\ClientInformation;
use App\Models\ClientInformationSnapshot;

/**
 * The two "Client Information" values in the shape both the Profile page and
 * the Inquire page read: how the client wants to get it, and where, when that
 * answer is delivery. No ids: ownership is never sent to the browser.
 */
final class ClientInformationValues
{
    public function __construct(
        public readonly string $deliveryMethod,
        public readonly ?string $location,
    ) {}

    public static function fromSaved(ClientInformation $information): self
    {
        $method = DeliveryMethod::tryFrom($information->delivery_method ?? '')
            ?? DeliveryMethod::Delivery;

        return new self(
            $method->value,
            $method === DeliveryMethod::Pickup ? null : $information->location,
        );
    }

    public static function fromSnapshot(ClientInformationSnapshot $snapshot): self
    {
        $method = DeliveryMethod::tryFrom($snapshot->delivery_method ?? '')
            ?? DeliveryMethod::Delivery;

        return new self(
            $method->value,
            $method === DeliveryMethod::Pickup ? null : $snapshot->location,
        );
    }

    /**
     * @param  array{deliveryMethod: string, location?: string|null}  $input
     */
    public static function fromInput(array $input): self
    {
        return new self(
            $input['deliveryMethod'],
            $input['location'] ?? null,
        );
    }

    /**
     * A stale or unknown site is ignored silently, on the page and when the
     * values are used to prefill a new request. A pickup never carries one.
     * An unknown method falls back to delivery, the form's starting choice.
     */
    public function withValidLocation(): self
    {
        $method = (DeliveryMethod::tryFrom($this->deliveryMethod)
            ?? DeliveryMethod::Delivery)->value;
        $location = $method === DeliveryMethod::Pickup->value
            ? null
            : ($this->location !== null && in_array($this->location, (array) config('inquiry.locations'), true)
                ? $this->location
                : null);

        return new self($method, $location);
    }

    /**
     * @return array{deliveryMethod: string, location: string|null}
     */
    public function toArray(): array
    {
        return [
            'deliveryMethod' => $this->deliveryMethod,
            'location' => $this->location,
        ];
    }
}
