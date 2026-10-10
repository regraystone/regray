<?php

namespace App\Enums;

enum MessageType: string
{
    case Text = 'text';
    case InquiryCard = 'inquiry_card';
    case AgendaCard = 'agenda_card';
}
