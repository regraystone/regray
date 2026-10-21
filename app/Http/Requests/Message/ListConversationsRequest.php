<?php

namespace App\Http\Requests\Message;

use App\Data\Message\ConversationListFilters;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator as ValidatorFactory;
use Illuminate\Validation\Rule;

class ListConversationsRequest extends FormRequest
{
    /** The route middleware decides access before this request is resolved. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'filter' => ['nullable', Rule::in([ConversationListFilters::FILTER_ALL, ConversationListFilters::FILTER_UNREAD])],
            'page' => ['nullable', 'integer', 'min:1'],
            'conversation' => ['nullable', 'string', 'max:20'],
        ];
    }

    /**
     * A bad value on a list page is not an error worth a page of its own: the
     * value is dropped and its default takes over.
     */
    protected function failedValidation(Validator $validator): void {}

    public function filters(): ConversationListFilters
    {
        $valid = $this->validValues();
        $search = isset($valid['search']) ? trim((string) $valid['search']) : '';

        return new ConversationListFilters(
            search: $search === '' ? null : $search,
            filter: $valid['filter'] ?? ConversationListFilters::FILTER_ALL,
            page: (int) ($valid['page'] ?? 1),
        );
    }

    public function requestedConversation(): ?string
    {
        $value = $this->validValues()['conversation'] ?? null;

        return is_string($value) ? $value : null;
    }

    /** @return array<string, mixed> */
    private function validValues(): array
    {
        return ValidatorFactory::make($this->query(), $this->rules())->valid();
    }
}
