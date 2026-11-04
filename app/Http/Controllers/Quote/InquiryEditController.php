<?php

namespace App\Http\Controllers\Quote;

use App\Actions\Inquiry\ListInquiryOptionsAction;
use App\Actions\Quote\ShowInquiryEditAction;
use App\Actions\Quote\UpdateInquiryAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Quote\EditInquiryRequest;
use App\Http\Requests\Quote\UpdateInquiryRequest;
use App\Http\Requests\Quote\UpdateStaffInquiryRequest;
use App\Http\Resources\InquiryCatalogResource;
use App\Http\Resources\InquiryEditResource;
use App\Services\InquiryDetailsService;
use App\Services\InquiryService;
use App\Services\InquiryStatusRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Editing an inquiry: the Inquire page with the inquiry's one project open at
 * the first step, and the save that writes it back. From the Quote page by its
 * client, or from the Inquiry list by staff (staffEdit, staffUpdate).
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
        public readonly InquiryDetailsService $inquiryDetailsService,
        public readonly InquiryService $inquiryService,
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
            'projects' => [InquiryEditResource::make($result['inquiry'])->resolve($request)],
        ]);
    }

    /**
     * Staff edit an inquiry's project from the Inquiry list: the same page in
     * edit mode, for an inquiry that is still open and not yet approved by the
     * client (the admin may edit an approved one), with no files.
     */
    public function staffEdit(Request $request, string $reference): Response|RedirectResponse
    {
        $inquiry = $this->inquiryDetailsService->forStaff($reference);

        if (! InquiryStatusRules::isOpen($inquiry)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'This inquiry is closed or cancelled, so it can no longer be changed.']);

            return to_route('inquiry.index');
        }

        if (! InquiryStatusRules::isEditableBy($inquiry, $request->user())) {
            Inertia::flash('toast', ['type' => 'error', 'message' => InquiryStatusRules::LOCKED_AFTER_APPROVAL]);

            return to_route('inquiry.index');
        }

        return Inertia::render('inquire', [
            ...InquiryCatalogResource::make($this->listInquiryOptions->execute())->resolve($request),
            'mode' => 'edit',
            'editor' => 'staff',
            'reference' => $inquiry->reference,
            'projects' => [InquiryEditResource::make($inquiry)->resolve($request)],
        ]);
    }

    public function staffUpdate(UpdateStaffInquiryRequest $request, string $reference): RedirectResponse
    {
        $inquiry = DB::transaction(fn () => $this->inquiryService->updateByStaff(
            $request->user(),
            $reference,
            $request->updateInput(),
            $request->ip(),
            $request->userAgent(),
        ));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Inquiry :reference updated', ['reference' => $inquiry->reference])]);

        return to_route('inquiry.index');
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

        Inertia::flash('toast', ['type' => 'success', 'message' => __("We'll review your changes and get back to you within 1-3 days.")]);

        return to_route('quote.show', $inquiry->reference);
    }
}
