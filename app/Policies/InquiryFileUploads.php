<?php

namespace App\Policies;

use App\Enums\DocumentType;
use App\Enums\Permission;
use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Who may upload which kind of file on an inquiry. The one place the rule
 * lives; the database only holds the kind.
 *
 * | Document          | Uploaded by                                   |
 * |-------------------|-----------------------------------------------|
 * | client_attachment | the inquiry's client                          |
 * | quotation         | whoever moves sales (`inquiries.status.sales`)  |
 * | job_order         | whoever moves sales                           |
 * | billing_statement | whoever moves payment (`inquiries.status.payment`) |
 * | receipt           | whoever moves payment                         |
 * | invoice           | whoever moves payment                         |
 *
 * The client is whoever the inquiry belongs to, so an inquiry sent from the
 * Inquire page carries its sender's attachments whatever their role. The staff
 * documents go with the status update they belong to, so they follow that
 * track's permission: sales and accounting, and the super-admin, who holds
 * every permission.
 */
class InquiryFileUploads
{
    public function allows(User $user, DocumentType $type, Inquiry $inquiry): bool
    {
        return match ($type) {
            DocumentType::ClientAttachment => $inquiry->client_id === $user->id,
            DocumentType::Quotation, DocumentType::JobOrder => $user->can(Permission::InquiriesStatusSales->value),
            DocumentType::BillingStatement, DocumentType::Receipt, DocumentType::Invoice => $user->can(Permission::InquiriesStatusPayment->value),
        };
    }

    /**
     * @throws AuthorizationException When `$user` may not upload this kind of file on this inquiry.
     */
    public function authorize(User $user, DocumentType $type, Inquiry $inquiry): void
    {
        if (! $this->allows($user, $type, $inquiry)) {
            throw new AuthorizationException(match ($type) {
                DocumentType::ClientAttachment => 'Only the inquiry\'s client can attach files to it.',
                DocumentType::Quotation => 'Only sales can upload a quotation.',
                DocumentType::JobOrder => 'Only sales can upload a job order.',
                DocumentType::BillingStatement => 'Only accounting can upload a billing statement.',
                DocumentType::Receipt => 'Only accounting can upload a receipt.',
                DocumentType::Invoice => 'Only accounting can upload an invoice.',
            });
        }
    }
}
