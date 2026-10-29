<?php

namespace App\Http\Requests\Inquiry;

use App\Enums\DocumentType;
use App\Enums\InquiryStatusTrack;
use App\Services\InquiryStatusRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * One Update Status from the Inquiry list: the track, where it moves, the
 * note, the files by document type (`files[quotation][]` and so on) and the
 * amount the target needs. The shapes are checked here; whether the move and
 * what it carries are right is InquiryStatusUpdateService's.
 */
class UpdateInquiryStatusRequest extends FormRequest
{
    /** The route middleware and InquiryPolicy decide access. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $limits = config('inquiry.limits');
        $staffDocuments = array_values(array_diff(DocumentType::values(), [DocumentType::ClientAttachment->value]));

        return [
            'track' => ['required', Rule::in(array_column(InquiryStatusTrack::cases(), 'value'))],
            'to' => ['required', 'string', 'max:20'],
            'note' => ['required', 'string', 'max:'.InquiryStatusRules::NOTE_MAX_LENGTH],
            'files' => ['nullable', 'array:'.implode(',', $staffDocuments)],
            'files.*' => ['array', 'max:'.$limits['files']],
            'files.*.*' => ['file', 'mimes:'.implode(',', $limits['file_extensions']), 'max:'.$limits['file_kilobytes']],
            InquiryStatusRules::QUOTED_TOTAL => ['nullable', 'numeric', 'gt:0', 'max:9999999999.99'],
            InquiryStatusRules::AMOUNT_RECEIVED => ['nullable', 'numeric', 'gt:0', 'max:9999999999.99'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'note.required' => 'Add a note explaining this change.',
            'note.max' => 'Keep the note to 500 characters.',
            'to.required' => 'Choose the status to move to.',
        ];
    }

    public function track(): InquiryStatusTrack
    {
        return InquiryStatusTrack::from((string) $this->validated('track'));
    }

    public function to(): string
    {
        return (string) $this->validated('to');
    }

    public function note(): string
    {
        return (string) $this->validated('note');
    }

    /** @return array<string, list<UploadedFile>> */
    public function uploads(): array
    {
        $files = [];

        foreach ((array) $this->file('files', []) as $type => $uploads) {
            $files[(string) $type] = array_values(array_filter(
                is_array($uploads) ? $uploads : [$uploads],
                fn ($upload): bool => $upload instanceof UploadedFile,
            ));
        }

        return $files;
    }

    /** The amount the target carries: the quoted total or the amount received. */
    public function amount(): ?string
    {
        $field = InquiryStatusRules::extraField($this->track(), $this->to());
        $value = $field === null ? null : $this->validated($field);

        return $value === null ? null : (string) $value;
    }
}
