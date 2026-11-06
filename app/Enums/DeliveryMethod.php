<?php

namespace App\Enums;

/**
 * How an inquiry reaches the client. A delivery goes to the location the client
 * names; a pickup has no location.
 */
enum DeliveryMethod: string
{
    case Delivery = 'delivery';
    case Pickup = 'pickup';
}
