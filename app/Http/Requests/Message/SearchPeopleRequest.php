<?php

namespace App\Http\Requests\Message;

use Illuminate\Foundation\Http\FormRequest;

class SearchPeopleRequest extends FormRequest
{
    /** The route middleware decides access before this request is resolved. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'clientId' => ['nullable', 'integer'],
        ];
    }

    public function search(): ?string
    {
        $search = $this->validated('search');

        return is_string($search) ? $search : null;
    }

    /** The client whose inquiries to list, once one is chosen. */
    public function clientId(): ?int
    {
        $clientId = $this->validated('clientId');

        return $clientId === null ? null : (int) $clientId;
    }
}
