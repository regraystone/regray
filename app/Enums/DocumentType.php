<?php

namespace App\Enums;

/** What a file on an inquiry is, and so who may upload it (App\Policies\InquiryFileUploads). */
enum DocumentType: string
{
    case ClientAttachment = 'client_attachment';
    case Quotation = 'quotation';
    case JobOrder = 'job_order';
    case BillingStatement = 'billing_statement';
    case Receipt = 'receipt';
    case Invoice = 'invoice';

    /**
     * Whether the client sees it on their inquiry. The job order is internal;
     * the client's own attachments are shown with the project instead.
     */
    public function isSharedWithClient(): bool
    {
        return in_array($this, [self::Quotation, self::BillingStatement, self::Receipt, self::Invoice], true);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
