<?php

namespace App\Http\Controllers\Quote;

use App\Actions\Quote\CancelInquiryAction;
use App\Actions\Quote\ListClientInquiriesAction;
use App\Enums\InquiryStatusGroup;
use App\Http\Controllers\Controller;
use App\Http\Requests\Quote\CancelInquiryRequest;
use App\Http\Requests\Quote\ListQuoteInquiriesRequest;
use App\Http\Resources\ClientInquiryResource;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class QuoteController extends Controller
{
    public function __construct(
        public readonly ListClientInquiriesAction $listClientInquiries,
        public readonly CancelInquiryAction $cancelInquiry,
    ) {}

    public function index(ListQuoteInquiriesRequest $request): Response
    {
        $filters = $request->filters();
        $result = $this->listClientInquiries->execute($request->user(), $filters);

        return Inertia::render('quote', [
            'inquiries' => Inertia::scroll(ClientInquiryResource::collection($result['inquiries'])),
            'hasMore' => $result['hasMore'],
            'filters' => [
                'search' => $filters->search ?? '',
                'status' => $filters->status->value ?? 'all',
                'tab' => $filters->tab->value ?? 'all',
                'sort' => $filters->sort,
            ],
            'statusOptions' => Inertia::once(fn (): array => ['all', ...InquiryStatusGroup::values()]),
        ]);
    }

    public function cancel(CancelInquiryRequest $request): RedirectResponse
    {
        $inquiry = $this->cancelInquiry->execute($request->user(), $request->validated('reference'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Inquiry :reference cancelled', ['reference' => $inquiry->reference])]);

        return back();
    }
}
