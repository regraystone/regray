<?php

namespace App\Enums;

enum ConversationType: string
{
    case Direct = 'direct';
    case Group = 'group';

    /** A client's one Regray Team thread, held by the marketing pool until claimed. */
    case Support = 'support';
}
