<?php

namespace App\Http\Controllers\Inquiry;

use App\Actions\Inquiry\CreateInquiryAction;
use App\Enums\ClientKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inquiry\StoreClientInquiryRequest;
use App\Http\Requests\Inquiry\StoreInquiryRequest;
use App\Http\Requests\Inquiry\StoreLeadInquiryRequest;
use App\Models\Client;
use App\Models\Inquiry;
use App\Models\Lead;
use App\Models\User;
use App\Repositories\Contracts\LeadRepositoryInterface;
use App\Services\WalkInInquiryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Throwable;

class InquiryController extends Controller
{
    public function __construct(
        public readonly CreateInquiryAction $createInquiry,
        public readonly LeadRepositoryInterface $leads,
        public readonly WalkInInquiryService $walkIns,
    ) {}

    /**
     * Send the inquiry the Inquire page built, for the signed-in client. Each
     * project on it opens an inquiry of its own, so the success names every
     * reference.
     *
     * Validation failures are the form request's and come back as Inertia
     * errors. Anything else is reported once and flashed as an error, and the
     * redirect leaves the page where it was so the form keeps its contents.
     */
    public function store(StoreClientInquiryRequest $request): RedirectResponse
    {
        return $this->send($request, $request->user());
    }

    /**
     * Staff open the inquiry for a lead's client, from the Inquire page. The
     * lead is read after the route's permissions, and LeadPolicy says whether
     * this user may use it: marketing its own idle or won lead, sales a won
     * lead.
     *
     * The reference stays a plain string for the reason the details page's
     * does: a bound model would 404 before the permission check.
     */
    public function storeForLead(StoreLeadInquiryRequest $request, string $reference): RedirectResponse
    {
        $lead = $this->leads->findByReference($reference) ?? abort(404);

        Gate::authorize('createInquiry', $lead);

        return $this->send($request, User::query()->findOrFail($lead->client_id), $lead);
    }

    /**
     * Staff open the inquiry for a direct (walk-in) client, who has no
     * account: no lead, and sales starts at negotiation at once. Any staff
     * member who may open an inquiry may open one on any walk-in record,
     * including one already on file under somebody else's inquiries; it reads
     * the new inquiry alone, never theirs. A record that does not exist, or
     * belongs to an account (whose inquiries go through their lead), is 404.
     *
     * Sales owns what it opens. Anyone else names the sales owner
     * (`ownerId`, WalkInInquiryService::ownerFor()).
     */
    public function storeForDirectClient(StoreLeadInquiryRequest $request, int $client): RedirectResponse
    {
        $record = Client::query()->whereKey($client)->where('kind', ClientKind::Direct->value)->first() ?? abort(404);
        $ownerId = $request->input('ownerId');
        $owner = $this->walkIns->ownerFor($request->user(), is_numeric($ownerId) ? (int) $ownerId : null);

        return $this->send($request, null, null, $record, $owner);
    }

    private function send(StoreInquiryRequest $request, ?User $client, ?Lead $lead = null, ?Client $direct = null, ?User $owner = null): RedirectResponse
    {
        $input = $request->inquiryInput();

        try {
            $inquiries = $direct !== null
                ? $this->createInquiry->executeForDirectClient($direct, $input, $request->user(), $owner ?? $request->user())
                : $this->createInquiry->execute($client ?? $request->user(), $input, $lead, $lead === null ? null : $request->user());
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            Inertia::flash('inquiry', [
                'status' => 'error',
                'message' => __('We could not save your inquiry. Your answers are still here, so you can try again.'),
            ]);

            return back();
        }

        Inertia::flash('inquiry', [
            'status' => 'success',
            // One project by its name; several, by how many, each with its own reference.
            'title' => count($inquiries) === 1 ? $input['projects'][0]['name'] : 'your '.count($inquiries).' projects',
            'reference' => implode(', ', array_map(fn (Inquiry $inquiry): string => $inquiry->reference, $inquiries)),
        ]);

        return back();
    }
}
