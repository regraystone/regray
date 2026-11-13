<?php

namespace App\Enums;

/** What a `user_consents` row is about. */
enum ConsentType: string
{
    case Terms = 'terms';
    case MarketingEmails = 'marketing_emails';
}
