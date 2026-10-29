<?php

namespace App\Http\Controllers\Lead;

use App\Enums\ContactKind;
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
     * Update lead contact details: name, phone, email.
     * Only the marketing user who owns the lead may update it.
     */
    public function update(UpdateLeadRequest $request, string $reference): RedirectResponse
    {
        $lead = $this->leads->findByReference($reference) ?? abort(404);

        Gate::authorize('update', $lead);

        $client = $lead->client;

        // Update client name
        if ($request->filled('name')) {
            $client->update(['full_name' => $request->input('name')]);
        }

        // Update or create phone contact
        if ($request->filled('phone')) {
            $phone = $request->input('phone');
            $normalized = ContactKind::Phone->normalise($phone);

            $client->contacts()
                ->where('kind', ContactKind::Phone)
                ->delete();

            if ($normalized !== '') {
                $client->contacts()->create([
                    'kind' => ContactKind::Phone,
                    'value' => $normalized,
                ]);
            }
        } elseif ($request->input('phone') === '') {
            // Empty phone means delete it
            $client->contacts()
                ->where('kind', ContactKind::Phone)
                ->delete();
        }

        // Update or create email contact
        if ($request->filled('email')) {
            $email = $request->input('email');

            $client->contacts()
                ->where('kind', ContactKind::Email)
                ->delete();

            if ($email !== '') {
                $client->contacts()->create([
                    'kind' => ContactKind::Email,
                    'value' => $email,
                ]);
            }
        } elseif ($request->input('email') === '') {
            // Empty email means delete it
            $client->contacts()
                ->where('kind', ContactKind::Email)
                ->delete();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "Lead {$lead->reference} updated."]);

        return back();
    }
}
