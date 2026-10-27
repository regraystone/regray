<?php

namespace App\Enums;

/**
 * A job order's status. After fabrication it is installed, delivered or picked
 * up, then completed; it can go on hold while active, and be cancelled any
 * time before it is completed.
 */
enum JobOrderStatus: string
{
    case Pending = 'pending';
    case Fabrication = 'fabrication';
    case ForInstall = 'for_install';
    case ForDelivery = 'for_delivery';
    case ForPickup = 'for_pickup';
    case Delivered = 'delivered';
    case Installed = 'installed';
    case Completed = 'completed';
    case OnHold = 'on_hold';
    case Cancelled = 'cancelled';
}
