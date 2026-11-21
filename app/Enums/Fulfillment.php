<?php

namespace App\Enums;

/**
 * How a service's work reaches the client (`services.fulfillment`), which is
 * what the Inquire page asks about it.
 *
 * - DeliveryOrPickup: something made and handed over (slabs, furniture). The
 *   client says how they would like to get it; a delivery needs an address,
 *   a pickup none.
 * - Installed: work done on site. There is no method to choose, only the
 *   installation address.
 */
enum Fulfillment: string
{
    case DeliveryOrPickup = 'delivery_or_pickup';
    case Installed = 'installed';

    /** Whether the client is asked how they would like to get it. */
    public function asksDeliveryMethod(): bool
    {
        return $this === self::DeliveryOrPickup;
    }
}
