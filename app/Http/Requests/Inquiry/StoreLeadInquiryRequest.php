<?php

namespace App\Http\Requests\Inquiry;

/**
 * The Inquire page's answers, sent by staff for a lead's client. The same
 * fields and rules as the client's own, except attachments: a client
 * attachment is uploaded by the client alone (InquiryFileUploads), so staff
 * send none.
 */
class StoreLeadInquiryRequest extends StoreInquiryRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'projects.*.attachments' => ['nullable', 'array', 'max:0'],
            'projects.*.attachments.*' => ['prohibited'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'projects.*.attachments.max' => 'Attachments are added by the client.',
            'projects.*.attachments.*.prohibited' => 'Attachments are added by the client.',
        ];
    }
}
