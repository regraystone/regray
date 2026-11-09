<?php

namespace App\Enums;

/**
 * What may be done to an appointment once it is booked. The value is the key
 * of the `can` flags ScheduleResource sends, and the SchedulePolicy ability.
 */
enum ScheduleAction: string
{
    case Update = 'update';
    case Cancel = 'cancel';
    case Approve = 'approve';
    case Serve = 'serve';
    case NoShow = 'noShow';

    /** The permission the route checks: attended and did not attend are a kind of update. */
    public function permission(): Permission
    {
        return match ($this) {
            self::Update, self::Serve, self::NoShow => Permission::ScheduleUpdate,
            self::Cancel => Permission::ScheduleCancel,
            self::Approve => Permission::ScheduleApprove,
        };
    }

    /** Whether a client may ever do it, to their own appointment. */
    public function isOpenToClients(): bool
    {
        return $this === self::Update || $this === self::Cancel;
    }
}
