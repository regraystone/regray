<?php

namespace App\Http\Requests\Notification;

use Illuminate\Foundation\Http\FormRequest;

/** A page of the bell: the newest, or those older than `before`. */
class NotificationPageRequest extends FormRequest
{
    /** Every signed-in user has a bell; the route's middleware has signed them in. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'before' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function beforeId(): ?int
    {
        return $this->filled('before') ? (int) $this->validated('before') : null;
    }
}
