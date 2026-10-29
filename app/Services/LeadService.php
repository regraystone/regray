<?php

namespace App\Services;

use App\Enums\LeadStatus;
use App\Events\LeadStatusChanged;
use App\Models\Lead;
use App\Models\User;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\InquiryRepositoryInterface;
use App\Repositories\Contracts\LeadRepositoryInterface;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Marketing's leads: one per client at a time, created when a marketing user is
 * given the client's Regray Team thread, and the lead every new inquiry of the
 * client links to.
 *
 * Every change to a lead's status goes through transition(), which writes the
 * status, its history row and the LeadStatusChanged event in one transaction.
 * The event's listener (ApplyLeadStatusCascade) moves the lead's inquiries:
 * won starts their sales, lost shows them cancelled.
 *
 * A client has at most one open (idle or won) lead. Whenever a lead is created,
 * the client's inquiries that had none are linked to it, so an inquiry without
 * a lead never sits beside an open lead.
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
     * The client's idle lead moves to the holder; a won lead stays with
     * whoever won it. A client with no open lead (none yet, or only lost ones)
     * gets a new idle lead held by `$holder`, and their inquiries that had no
     * lead are linked to it.
     */
    public function assign(User $client, User $holder): Lead
    {
        $lead = $this->leads->openForUpdate($client);

        if ($lead === null) {
            return $this->open($client, $holder, $holder);
        }

        if ($lead->status === LeadStatus::Idle && $lead->marketing_user_id !== $holder->id) {
            $this->leads->moveTo($lead, $holder);
        }

        return $lead;
    }

    /**
     * The lead a new inquiry from this client links to: their open lead, or,
     * when they have none (their latest lead was lost) and somebody holds their
     * Regray Team thread, a new idle lead for that holder. Null while nobody
     * holds the thread; the claim links the inquiry later.
     *
     * Expects to run inside the transaction that writes the inquiry.
     */
    public function leadForNewInquiry(User $client): ?Lead
    {
        $lead = $this->leads->openForUpdate($client);

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
     * Mark a lead won or lost, with the note saying why. Only an idle lead
     * moves: won and lost are final.
     *
     * Runs in its own transaction, or the caller's: the lead row is locked, its
     * status and history row are written, and LeadStatusChanged moves its
     * inquiries before the transaction ends.
     *
     * @throws ValidationException When the lead is no longer idle, or `$to` is idle.
     */
    public function transition(Lead $lead, LeadStatus $to, ?User $by, ?string $note = null): Lead
    {
        return DB::transaction(function () use ($lead, $to, $by, $note): Lead {
            $locked = $this->leads->lockForUpdate($lead->id);
            $from = $locked->status;

            if ($from !== LeadStatus::Idle) {
                throw ValidationException::withMessages(['status' => "This lead is already {$from->value}."]);
            }

            if ($to === LeadStatus::Idle) {
                throw ValidationException::withMessages(['status' => 'A lead can only be marked won or lost.']);
            }

            $now = Date::now();

            $this->leads->updateStatus($locked, $to, $now);
            $this->leads->createHistory($locked, $from, $to, $by?->id, $now, $note);

            event(new LeadStatusChanged($locked, $from, $to, $by?->id));

            $lead->setRawAttributes($locked->getAttributes(), true);

            return $lead;
        });
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

    /** A new idle lead for the client, held by `$holder`, with the client's unlinked inquiries linked to it. */
    private function open(User $client, User $holder, ?User $by): Lead
    {
        $now = Date::now();

        $lead = $this->leads->create([
            'reference' => $this->nextReference(),
            'client_id' => $client->id,
            'marketing_user_id' => $holder->id,
            'status' => LeadStatus::Idle,
            'status_changed_at' => $now,
        ]);

        $this->leads->createHistory($lead, null, LeadStatus::Idle, $by?->id, $now);
        $this->inquiries->linkUnlinkedToLead($lead);

        return $lead;
    }
}
