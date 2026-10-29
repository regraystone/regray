<?php

namespace App\Http\Requests\Inquiry;

use App\Services\InquiryStatusRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/** The job order sales uploads after the quote, with a note: `files[job_order][]`. */
class UploadJobOrderRequest extends FormRequest
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

        return [
            'note' => ['required', 'string', 'max:'.InquiryStatusRules::NOTE_MAX_LENGTH],
            'files' => ['required', 'array:job_order'],
            'files.job_order' => ['required', 'array', 'size:1'],
            'files.job_order.*' => ['file', 'mimes:'.implode(',', $limits['file_extensions']), 'max:'.$limits['file_kilobytes']],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'note.required' => 'Add a note explaining this change.',
            'files.required' => 'Add the job order.',
            'files.job_order.required' => 'Add the job order.',
            'files.job_order.size' => 'Add one job order.',
        ];
    }

    public function note(): string
    {
        return (string) $this->validated('note');
    }

    /** @return list<UploadedFile> */
    public function jobOrders(): array
    {
        $files = $this->file('files.job_order', []);

        return array_values(is_array($files) ? $files : [$files]);
    }
}
