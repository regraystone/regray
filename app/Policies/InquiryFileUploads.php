<?php

namespace App\Policies;

use App\Enums\DocumentType;
use App\Enums\Role;
use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Who may upload which kind of file on an inquiry. The one place the rule
 * lives; the database only holds the kind.
 *
 * | Document          | Uploaded by                     |
 * |-------------------|---------------------------------|
 * | client_attachment | the inquiry's client            |
 * | quotation         | sales                           |
 * | job_order         | sales                           |
 * | receipt           | accounting                      |
 *
 * The client is whoever the inquiry belongs to, so an inquiry sent from the
 * Inquire page carries its sender's attachments whatever their role. The staff
 * documents go by role, not permission, because the rule is about who does the
 * work: no other role, the super-admin included, uploads them.
 */
class InquiryFileUploads
{
    public function allows(User $user, DocumentType $type, Inquiry $inquiry): bool
    {
        return match ($type) {
            DocumentType::ClientAttachment => $inquiry->client_id === $user->id,
            DocumentType::Quotation, DocumentType::JobOrder => $user->hasRole(Role::Sales->value),
            DocumentType::Receipt => $user->hasRole(Role::Accounting->value),
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
                DocumentType::Receipt => 'Only accounting can upload a receipt.',
            });
        }
    }
}
