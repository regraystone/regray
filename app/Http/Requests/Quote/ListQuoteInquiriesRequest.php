<?php

namespace App\Http\Requests\Quote;

use App\Data\InquiryListFilters;
use App\Enums\InquiryStatusGroup;
use App\Enums\QuoteTab;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator as ValidatorFactory;
use Illuminate\Validation\Rule;

class ListQuoteInquiriesRequest extends FormRequest
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
            'status' => ['nullable', Rule::in(['all', ...InquiryStatusGroup::values()])],
            'tab' => ['nullable', Rule::in(['all', ...QuoteTab::values()])],
            'sort' => ['nullable', Rule::in([InquiryListFilters::SORT_NEWEST, InquiryListFilters::SORT_OLDEST])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * A bad value on a list page is not an error worth a page of its own: the
     * value is dropped and its default takes over.
     */
    protected function failedValidation(Validator $validator): void {}

    public function filters(): InquiryListFilters
    {
        /** @var array<string, mixed> $valid */
        $valid = ValidatorFactory::make($this->query(), $this->rules())->valid();

        $search = isset($valid['search']) ? trim((string) $valid['search']) : '';
        $status = $valid['status'] ?? 'all';
        $tab = $valid['tab'] ?? 'all';

        return new InquiryListFilters(
            search: $search === '' ? null : $search,
            status: $status === 'all' ? null : InquiryStatusGroup::from($status),
            sort: $valid['sort'] ?? InquiryListFilters::SORT_NEWEST,
            page: (int) ($valid['page'] ?? 1),
            tab: $tab === 'all' ? null : QuoteTab::from($tab),
        );
    }
}
