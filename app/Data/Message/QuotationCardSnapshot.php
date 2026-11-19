<?php

namespace App\Data\Message;

use App\Models\Inquiry;
use App\Models\InquiryFile;

/**
 * What a quotation card shows, read from the document and its inquiry when the
 * card is posted and kept on the message (`card_snapshot`), so the card stays
 * the record of what was sent: renaming the inquiry or changing its stones
 * later does not rewrite it.
 *
 * - `fileName` is the name Sales uploaded it under, `storedName` the generated
 *   one the file route serves it by.
 * - `materialCount` counts the inquiry's stones once each, the way the inquiry
 *   card's `stoneCount` does; 0 for a service-only inquiry.
 */
final readonly class QuotationCardSnapshot
{
    /**
     * @return array{fileName: string, storedName: string, sizeBytes: int, revision: int|null, materialCount: int, inquiryName: string, reference: string}
     */
    public static function of(InquiryFile $file): array
    {
        /** @var Inquiry $inquiry */
        $inquiry = $file->loadMissing('inquiry')->inquiry;

        return [
            'fileName' => $file->original_name,
            'storedName' => $file->stored_name,
            'sizeBytes' => $file->size_bytes,
            'revision' => $file->revision,
            'materialCount' => $inquiry->stones()->whereNotNull('stone_id')->distinct()->count('stone_id'),
            'inquiryName' => (string) $inquiry->name,
            'reference' => $inquiry->reference,
        ];
    }
}
