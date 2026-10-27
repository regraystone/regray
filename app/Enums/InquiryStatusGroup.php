<?php

namespace App\Enums;

/**
 * The Quote list's status tabs. Pending, closed and cancelled are the display
 * statuses of the same name; everything else is in progress.
 */
enum InquiryStatusGroup: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    /**
     * The display statuses under this tab.
     *
     * @return list<InquiryStatus>
     */
    public function statuses(): array
    {
        return array_values(array_filter(
            InquiryStatus::cases(),
            fn (InquiryStatus $status): bool => $status->group() === $this,
        ));
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $group): string => $group->value, self::cases());
    }
}
