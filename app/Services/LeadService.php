<?php

namespace App\Services;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\User;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\InquiryRepositoryInterface;
use App\Repositories\Contracts\LeadRepositoryInterface;
use Illuminate\Support\Facades\Date;

/**
 * Marketing's leads: one per client, kept for good, created when a marketing
 * user is given the client's Regray Team thread, and the lead every inquiry of
 * the client links to. The lead follows the thread: whoever holds the thread
 * holds the lead.
 *
 * A lead has no status of its own. Marketing decides each of its inquiries won
 * or lost (InquiryStatusService::decide()), and the lead shows the tally.
 *
 * Whenever a lead is created, the client's inquiries that had none are linked
 * to it, so an inquiry without a lead never sits beside the client's lead.
 */
class LeadService
{
    public const REFERENCE_PREFIX = 'LEAD-';

    public function __construct(
        public readonly LeadRepositoryInterface $leads,
        public readonly InquiryRepositoryInterface $inquiries,
        public readonly ConversationRepositoryInterface $conversations,
    ) {}

    /**
     * The client's Regray Team thread was given to `$holder`, by a claim or a
     * reassignment. Expects to run inside the transaction that assigned it.
     *
     * The client's lead moves to the holder, whatever its inquiries' decisions
     * (each decision keeps who made it in the lead's log). A client with no
     * lead yet gets one held by `$holder`, and their inquiries that had no lead
     * are linked to it.
     */
    public function assign(User $client, User $holder): Lead
    {
        $lead = $this->leads->forClientForUpdate($client);

        if ($lead === null) {
            return $this->open($client, $holder, $holder);
        }

        if ($lead->marketing_user_id !== $holder->id) {
            $this->leads->moveTo($lead, $holder);
        }

        return $lead;
    }

    /**
     * The lead a new inquiry from this client links to: their lead, or, when
     * they have none yet and somebody holds their Regray Team thread, a new
     * lead for that holder. Null while nobody holds the thread; the claim
     * links the inquiry later. The inquiry waits for marketing's decision.
     *
     * Expects to run inside the transaction that writes the inquiry.
     */
    public function leadForNewInquiry(User $client): ?Lead
    {
        $lead = $this->leads->forClientForUpdate($client);

        if ($lead !== null) {
            return $lead;
        }

        $thread = $this->conversations->findSupportFor($client);

        if ($thread?->assigned_to === null) {
            return null;
        }

        return $this->open($client, User::query()->findOrFail($thread->assigned_to), null);
    }

    /**
     * Correct the lead's client's profile phone. A blank phone clears a stored
     * one, and opens no profile where none is. Their name and email are their
     * account's, theirs to change, not a lead's.
     */
    public function updateClientPhone(Lead $lead, string $phone): void
    {
        $client = $lead->client;

        if ($client->profile !== null) {
            $client->profile->update(['phone' => $phone]);
        } elseif ($phone !== '') {
            $client->profile()->create(['phone' => $phone, 'address' => '']);
        }
    }

    public function nextReference(): string
    {
        return self::REFERENCE_PREFIX.str_pad((string) $this->leads->nextReferenceNumber(), 4, '0', STR_PAD_LEFT);
    }

    /** A new lead for the client, held by `$holder`, with the client's unlinked inquiries linked to it. */
    private function open(User $client, User $holder, ?User $by): Lead
    {
        $now = Date::now();

        $lead = $this->leads->create([
            'reference' => $this->nextReference(),
            'client_id' => $client->id,
            'marketing_user_id' => $holder->id,
        ]);

        $this->leads->createHistory($lead, null, LeadStatus::Idle, $by?->id, $now);
        $this->inquiries->linkUnlinkedToLead($lead);

        return $lead;
    }
}
