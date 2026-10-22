<?php

namespace App\Http\Requests\Message;

use App\Models\Conversation;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class SendMessageRequest extends FormRequest
{
    public const MAX_BODY = 5000;

    /** The route middleware decides access before this request is resolved. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        /** @var Conversation $conversation */
        $conversation = $this->route('conversation');
        /** @var array{files: int, file_kilobytes: int, total_kilobytes: int, file_extensions: list<string>} $limits */
        $limits = config('messaging.attachments');

        return [
            'body' => ['required_without:attachments', 'nullable', 'string', 'max:'.self::MAX_BODY],
            'replyToId' => [
                'nullable',
                'integer',
                Rule::exists('messages', 'id')->where('conversation_id', $conversation->id),
            ],
            'clientKey' => ['required', 'uuid'],
            'attachments' => [
                'nullable',
                'array',
                'max:'.$limits['files'],
                function (string $attribute, mixed $value, Closure $fail) use ($limits): void {
                    $bytes = collect(is_array($value) ? $value : [])
                        ->sum(fn (mixed $file): int => $file instanceof UploadedFile ? (int) $file->getSize() : 0);

                    if ($bytes > $limits['total_kilobytes'] * 1024) {
                        $fail('Send up to '.intdiv($limits['total_kilobytes'], 1024).' MB of files at a time.');
                    }
                },
            ],
            'attachments.*' => [
                'file',
                'mimes:'.implode(',', $limits['file_extensions']),
                'max:'.$limits['file_kilobytes'],
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        /** @var array{files: int, file_kilobytes: int} $limits */
        $limits = config('messaging.attachments');

        return [
            'body.required_without' => 'Write a message first.',
            'body.max' => 'Keep a message to '.self::MAX_BODY.' characters or fewer.',
            'replyToId.exists' => 'That message is not in this conversation.',
            'attachments.max' => 'Send up to '.$limits['files'].' files at a time.',
            'attachments.*.uploaded' => 'A file could not be uploaded. Please try again.',
            'attachments.*.mimes' => 'Send photos (JPG, PNG, WebP), PDF or Word (DOCX) files.',
            'attachments.*.max' => 'Each file can be up to '.intdiv($limits['file_kilobytes'], 1024).' MB.',
        ];
    }

    /** The words of the message; empty when only files are sent. */
    public function body(): string
    {
        return (string) $this->validated('body');
    }

    public function replyToId(): ?int
    {
        return $this->filled('replyToId') ? (int) $this->validated('replyToId') : null;
    }

    public function clientKey(): string
    {
        return strtolower((string) $this->validated('clientKey'));
    }

    /** @return list<UploadedFile> */
    public function attachments(): array
    {
        /** @var array<int, UploadedFile> $files */
        $files = $this->file('attachments', []);

        return array_values($files);
    }
}
