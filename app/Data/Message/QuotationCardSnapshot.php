<?php

namespace App\Data\Message;

use App\Models\Inquiry;
use App\Models\InquiryFile;

/**
 * What a quotation card shows, read from the document and its inquiry when the
 * card is posted and kept on the message (its `card_*` columns), so the card stays
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

    /** @var array<string, string> Each message column, by the snapshot's key. */
    private const COLUMNS = [
        'fileName' => 'card_file_name',
        'storedName' => 'card_stored_name',
        'sizeBytes' => 'card_size_bytes',
        'revision' => 'card_revision',
        'materialCount' => 'card_material_count',
        'inquiryName' => 'card_inquiry_name',
        'reference' => 'card_reference',
    ];

    /**
     * The message columns a snapshot is kept in.
     *
     * @param  array{fileName: string, storedName: string, sizeBytes: int, revision: int|null, materialCount: int, inquiryName: string, reference: string}  $snapshot
     * @return array<string, string|int|null>
     */
    public static function columns(array $snapshot): array
    {
        $columns = [];

        foreach (self::COLUMNS as $key => $column) {
            $columns[$column] = $snapshot[$key];
        }

        return $columns;
    }

    /**
     * The snapshot a message's columns hold, or null when it has none (any
     * message but a quotation card).
     *
     * @param  array<string, mixed>  $attributes
     * @return array{fileName: string, storedName: string, sizeBytes: int, revision: int|null, materialCount: int, inquiryName: string, reference: string}|null
     */
    public static function fromColumns(array $attributes): ?array
    {
        if (($attributes['card_file_name'] ?? null) === null) {
            return null;
        }

        $revision = $attributes['card_revision'] ?? null;

        return [
            'fileName' => (string) $attributes['card_file_name'],
            'storedName' => (string) $attributes['card_stored_name'],
            'sizeBytes' => (int) $attributes['card_size_bytes'],
            'revision' => $revision === null ? null : (int) $revision,
            'materialCount' => (int) $attributes['card_material_count'],
            'inquiryName' => (string) $attributes['card_inquiry_name'],
            'reference' => (string) $attributes['card_reference'],
        ];
    }
}
