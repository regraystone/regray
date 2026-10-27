<?php

namespace App\Enums;

enum MessageType: string
{
    case Text = 'text';

    /** A snapshot of one inquiry: its display status and its four role statuses. */
    case InquiryCard = 'inquiry_card';

    case ScheduleCard = 'schedule_card';

    /** A claim, release or reassignment of a Regray Team thread. */
    case System = 'system';
}
