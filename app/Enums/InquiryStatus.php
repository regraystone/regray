<?php

namespace App\Enums;

enum InquiryStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Quoted = 'quoted';
    case Paid = 'paid';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
