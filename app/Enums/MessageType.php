<?php

namespace App\Enums;

enum MessageType: string
{
    case Text = 'text';
    case InquiryCard = 'inquiry_card';
    case ScheduleCard = 'schedule_card';

    /** A claim, release or reassignment of a Regray Team thread. */
    case System = 'system';
}
