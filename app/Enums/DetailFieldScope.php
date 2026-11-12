<?php

namespace App\Enums;

/**
 * Where a service's question is answered: on each stone row (`inquiry_stones`)
 * or once for the whole project (`inquiry_detail_answers`).
 */
enum DetailFieldScope: string
{
    case Stone = 'stone';
    case Project = 'project';
}
