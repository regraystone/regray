<?php

namespace App\Enums;

/**
 * What may be done to an appointment once it is booked. The value is the key
 * of the `can` flags ScheduleResource sends, and the SchedulePolicy ability.
 *
 * Marketing assigns an appointment to a sales member; that sales member
 * confirms or declines it. Marketing (and the client, their own) updates and
 * cancels; marketing and sales mark how it went.
 */
enum ScheduleAction: string
{
    case Update = 'update';
    case Cancel = 'cancel';
    case Assign = 'assign';
    case Confirm = 'confirm';
    case Decline = 'decline';
    case Serve = 'serve';
    case NoShow = 'noShow';

    /** The permission the route checks. */
    public function permission(): Permission
    {
        return match ($this) {
            self::Update => Permission::ScheduleUpdate,
            self::Cancel => Permission::ScheduleCancel,
            self::Assign => Permission::ScheduleAssign,
            self::Confirm, self::Decline => Permission::ScheduleConfirm,
            self::Serve, self::NoShow => Permission::ScheduleAttend,
        };
    }

    /** Whether a client may ever do it, to their own appointment. */
    public function isOpenToClients(): bool
    {
        return $this === self::Update || $this === self::Cancel;
    }

    /** Whether only the sales member the appointment is assigned to may do it. */
    public function isForAssignedSales(): bool
    {
        return $this === self::Confirm || $this === self::Decline;
    }
}
