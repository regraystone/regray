<?php

namespace App\Http\Requests\Quote;

/**
 * Staff editing an inquiry's project from the Inquiry list: the same answers
 * as the client's edit, without files. A client attachment is the client's own
 * to add or remove (InquiryFileUploads), so staff send none and every stored
 * one is kept.
 */
class UpdateStaffInquiryRequest extends UpdateInquiryRequest
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
