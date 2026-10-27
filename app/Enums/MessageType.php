<?php

namespace App\Enums;

enum MessageType: string
{
    case Text = 'text';
    case InquiryCard = 'inquiry_card';
    case ScheduleCard = 'schedule_card';

    /** A snapshot of one project: its status, its quotation's and its payment's. */
    case ProjectCard = 'project_card';

    /** A claim, release or reassignment of a Regray Team thread. */
    case System = 'system';
}
