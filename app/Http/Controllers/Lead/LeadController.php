<?php

namespace App\Http\Controllers\Lead;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lead\UpdateLeadRequest;
use App\Http\Requests\Lead\UpdateLeadStatusRequest;
use App\Repositories\Contracts\InquiryRepositoryInterface;
use App\Repositories\Contracts\LeadRepositoryInterface;
use App\Services\ClientService;
use App\Services\InquiryStatusService;
use App\Services\LeadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class LeadController extends Controller
{
    public function __construct(
        public readonly LeadRepositoryInterface $leads,
        public readonly LeadService $leadService,
        public readonly ClientService $clientService,
        public readonly InquiryRepositoryInterface $inquiries,
        public readonly InquiryStatusService $inquiryStatusService,
    ) {}

    /**
     * Decide one of the lead's inquiries won or lost, with a note, from the
     * Leads page. Only the lead's marketing user may (LeadPolicy), and only
     * while the inquiry waits for a decision (InquiryStatusService::decide()).
     * Won starts its sales; lost shows it cancelled. The client's other
     * inquiries are untouched.
     *
     * The references stay plain strings so the permissions are checked before
     * anything says whether the lead or the inquiry exists; an inquiry that is
     * not on this lead is a 404 too.
     */
    public function decideInquiry(UpdateLeadStatusRequest $request, string $reference, string $inquiry): RedirectResponse
    {
        $lead = $this->leads->findByReference($reference) ?? abort(404);

        Gate::authorize('update', $lead);

        $status = $request->to();

        DB::transaction(function () use ($lead, $inquiry, $request, $status): void {
            $locked = $this->inquiries->findByReferenceForUpdate($inquiry);

            if ($locked === null || $locked->lead_id !== $lead->id) {
                abort(404);
            }

            $this->inquiryStatusService->decide($locked, $status, $request->user(), $request->note());
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$inquiry} marked {$status->value}."]);

        return back();
    }

    /**
     * Correct the lead's client's phone from Edit on the Leads page. Only its
     * marketing user may (LeadPolicy).
     */
    public function update(UpdateLeadRequest $request, string $reference): RedirectResponse
    {
        $lead = $this->leads->findByReference($reference) ?? abort(404);

        Gate::authorize('update', $lead);

        $this->leadService->updateClientPhone($lead, $request->string('phone')->trim()->value());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Lead {$lead->reference} updated."]);

        return back();
    }
}
