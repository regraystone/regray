<?php

namespace App\Policies;

use App\Enums\DocumentType;
use App\Enums\Permission;
use App\Models\InquiryFile;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Which of an inquiry's files a reader who may open the inquiry may see. The
 * one place the rule lives, so the details page lists exactly the files the
 * file route serves.
 *
 * - The client's own attachments: everyone.
 * - The documents shared with the client (DocumentType::isSharedWithClient()):
 *   everyone.
 * - The job order: staff only, as it is internal.
 * - Quotations: only the latest revision, as an older one is superseded,
 *   except to whoever may move sales (`inquiries.status.sales`) and so sends
 *   them, who sees every revision to see how the quote moved.
 */
class InquiryFileVisibility
{
    /**
     * @param  Collection<int, InquiryFile>  $files  every file on the inquiry
     * @return Collection<int, InquiryFile>
     */
    public function filter(User $viewer, Collection $files): Collection
    {
        $latestQuote = $files->where('document_type', DocumentType::Quotation)->max('revision');
        $seesEveryRevision = $viewer->can(Permission::InquiriesStatusSales->value);
        $isClient = $viewer->isClient();

        return $files
            ->filter(fn (InquiryFile $file): bool => match (true) {
                $file->document_type === DocumentType::ClientAttachment => true,
                $file->document_type === DocumentType::JobOrder => ! $isClient,
                $file->document_type === DocumentType::Quotation => $seesEveryRevision || $file->revision === $latestQuote,
                default => $file->document_type->isSharedWithClient(),
            })
            ->values();
    }

    /**
     * Whether the reader may see this one file.
     *
     * @param  Collection<int, InquiryFile>  $files  every file on the inquiry
     */
    public function allows(User $viewer, InquiryFile $file, Collection $files): bool
    {
        return $this->filter($viewer, $files)->contains(fn (InquiryFile $visible): bool => $visible->is($file));
    }
}
