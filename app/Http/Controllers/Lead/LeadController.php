<?php

namespace App\Http\Controllers\Lead;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lead\UpdateLeadRequest;
use App\Http\Requests\Lead\UpdateLeadStatusRequest;
use App\Repositories\Contracts\LeadRepositoryInterface;
use App\Services\ClientService;
use App\Services\LeadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class LeadController extends Controller
{
    public function __construct(
        public readonly LeadRepositoryInterface $leads,
        public readonly LeadService $leadService,
        public readonly ClientService $clientService,
    ) {}

    /**
     * Mark a lead won or lost, with a note, from the Leads page. Only its
     * marketing user may (LeadPolicy), and only while it is idle (LeadService).
     * Won starts sales on its inquiries; lost shows them cancelled.
     *
     * The reference stays a plain string so `leads.view` is checked before
     * anything says whether the lead exists.
     */
    public function updateStatus(UpdateLeadStatusRequest $request, string $reference): RedirectResponse
    {
        $lead = $this->leads->findByReference($reference) ?? abort(404);

        Gate::authorize('update', $lead);

        $status = $request->to();
        $this->leadService->transition($lead, $status, $request->user(), $request->note());

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$lead->reference} marked {$status->value}."]);

        return back();
    }

    /**
     * Correct the lead's client from Edit on the Leads page: their name and
     * phone. Only its marketing user may (LeadPolicy).
     */
    public function update(UpdateLeadRequest $request, string $reference): RedirectResponse
    {
        $lead = $this->leads->findByReference($reference) ?? abort(404);

        Gate::authorize('update', $lead);

        $this->leadService->updateClient($lead, $request->string('name')->trim()->value(), $request->string('phone')->trim()->value());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Lead {$lead->reference} updated."]);

        return back();
    }
}
