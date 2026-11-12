<?php

namespace App\Enums;

/**
 * What kind of answer a service's question takes, and so which column of an
 * answer holds it: free text, a positive number, one of the listed options,
 * or yes or no.
 */
enum DetailAnswerType: string
{
    case Text = 'text';
    case Number = 'number';
    case Choice = 'choice';
    case Boolean = 'boolean';
}
