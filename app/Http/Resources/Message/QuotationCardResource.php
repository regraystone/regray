<?php

namespace App\Http\Resources\Message;

use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A quotation as a card in a thread, built from the `quotation_card` message:
 * the snapshot of the document taken when the card was posted
 * (QuotationCardSnapshot), and the two ways to open it.
 *
 * Both links are the inquiry's file route, inline and `?download=1`, so who
 * may open the document stays that route's (InquiryFileVisibility). A reader
 * who may not see it, such as a client opening a revision that a later one
 * has superseded, gets the route's 404; nothing here decides it.
 *
 * @mixin Message
 */
class QuotationCardResource extends JsonResource
{
    public function __construct(Message $message)
    {
        parent::__construct($message);
    }

    /**
     * @return array{fileName: string, sizeBytes: int, revision: int|null, materialCount: int, inquiryName: string, reference: string, viewUrl: string, downloadUrl: string, occurredAt: string}
     */
    public function toArray(Request $request): array
    {
        /** @var array{fileName: string, storedName: string, sizeBytes: int, revision: int|null, materialCount: int, inquiryName: string, reference: string} $snapshot */
        $snapshot = $this->card_snapshot;
        $file = ['reference' => $snapshot['reference'], 'storedName' => $snapshot['storedName']];

        return [
            'fileName' => $snapshot['fileName'],
            'sizeBytes' => $snapshot['sizeBytes'],
            'revision' => $snapshot['revision'],
            'materialCount' => $snapshot['materialCount'],
            'inquiryName' => $snapshot['inquiryName'],
            'reference' => $snapshot['reference'],
            'viewUrl' => route('inquiry.files.show', $file, absolute: false),
            'downloadUrl' => route('inquiry.files.show', [...$file, 'download' => 1], absolute: false),
            'occurredAt' => $this->created_at->toIso8601String(),
        ];
    }
}
