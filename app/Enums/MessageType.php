<?php

namespace App\Enums;

enum MessageType: string
{
    case Text = 'text';

    /** A snapshot of one inquiry: its display status and its four role statuses. */
    case InquiryCard = 'inquiry_card';

    case ScheduleCard = 'schedule_card';

    /**
     * One quotation document Sales sent, a revision included, posted beside
     * the inquiry card that announced it. Its contents are a snapshot
     * (the `card_*` columns) of the document when it was posted.
     */
    case QuotationCard = 'quotation_card';

    /** A claim of a Regray Team thread (and, on older threads, a release or reassignment). */
    case System = 'system';
}
