<?php

namespace App\Enums;

/** What a file on an inquiry is, and so who may upload it (App\Policies\InquiryFileUploads). */
enum DocumentType: string
{
    case ClientAttachment = 'client_attachment';
    case Quotation = 'quotation';
    case Receipt = 'receipt';
    case JobOrder = 'job_order';
}
