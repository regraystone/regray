<?php

namespace App\Http\Controllers\Quote;

use App\Actions\Inquiry\ListInquiryOptionsAction;
use App\Actions\Quote\ShowInquiryEditAction;
use App\Actions\Quote\UpdateInquiryAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Quote\EditInquiryRequest;
use App\Http\Requests\Quote\UpdateInquiryRequest;
use App\Http\Resources\InquiryCatalogResource;
use App\Http\Resources\InquiryEditResource;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Editing an inquiry from the Quote page: the Inquire page opened at its Review
 * step with the inquiry's projects, and the save that writes them back.
 *
 * Client-only, like the Quote list. Both are scoped to the signed-in client's
 * own inquiries, so another client's reference is a 404, and both ask
 * InquiryActions whether this status may still be updated.
 */
class InquiryEditController extends Controller
{
    public function __construct(
        public readonly ShowInquiryEditAction $showInquiryEdit,
        public readonly UpdateInquiryAction $updateInquiry,
        public readonly ListInquiryOptionsAction $listInquiryOptions,
    ) {}

    public function edit(EditInquiryRequest $request): Response|RedirectResponse
    {
        $reference = $request->validated('reference');
        $result = $this->showInquiryEdit->execute($request->user(), $reference);

        if ($result['refusal'] !== null) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $result['refusal']]);

            return to_route('quote.show', $reference);
        }

        return Inertia::render('inquire', [
            ...InquiryCatalogResource::make($this->listInquiryOptions->execute())->resolve($request),
            'mode' => 'edit',
            'reference' => $result['inquiry']->reference,
            'projects' => InquiryEditResource::collection($result['inquiry']->projects)->resolve($request),
        ]);
    }

    public function update(UpdateInquiryRequest $request): RedirectResponse
    {
        $inquiry = $this->updateInquiry->execute(
            $request->user(),
            (string) $request->route('reference'),
            $request->updateInput(),
            $request->ip(),
            $request->userAgent(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Inquiry :reference updated', ['reference' => $inquiry->reference])]);

        return to_route('quote.show', $inquiry->reference);
    }
}
