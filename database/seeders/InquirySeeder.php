<?php

namespace Database\Seeders;

use App\Data\Measurement;
use App\Data\Message\QuotationCardSnapshot;
use App\Data\PhAddress;
use App\Enums\ClientKind;
use App\Enums\ContactKind;
use App\Enums\ConversationMemberRole;
use App\Enums\ConversationType;
use App\Enums\DeliveryMethod;
use App\Enums\DetailFieldScope;
use App\Enums\DocumentType;
use App\Enums\InquiryStatus;
use App\Enums\InquiryStatusTrack;
use App\Enums\LeadStatus;
use App\Enums\MessageType;
use App\Enums\PaymentStatus;
use App\Enums\ProcessStatus;
use App\Enums\Role;
use App\Enums\SalesStatus;
use App\Enums\SizeUnit;
use App\Enums\SystemEvent;
use App\Enums\ThicknessUnit;
use App\Models\Brand;
use App\Models\Client;
use App\Models\Conversation;
use App\Models\DetailField;
use App\Models\EdgeProfile;
use App\Models\Finish;
use App\Models\Inquiry;
use App\Models\InquiryFile;
use App\Models\InquiryHandler;
use App\Models\InquiryStatusHistory;
use App\Models\Lead;
use App\Models\LeadStatusHistory;
use App\Models\Message;
use App\Models\PaymentTransaction;
use App\Models\Service;
use App\Models\Stone;
use App\Models\User;
use App\Policies\InquiryFileUploads;
use App\Repositories\Contracts\ConversationMemberRepositoryInterface;
use App\Repositories\Contracts\MessageRepositoryInterface;
use App\Services\ClientService;
use App\Services\InquiryStatusCalculator;
use App\Services\InquiryStatusRules;
use App\Services\InquiryStatusUpdateService;
use App\Services\Message\GroupConversationService;
use App\Services\Message\SupportConversationService;
use App\Services\ScheduleService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The demo clients' leads, inquiries and everything that happened to them,
 * from database/seeders/data/inquiries.php. One inquiry is one project.
 *
 * Each lead is written first, with its history, and its client's Regray Team
 * thread is given to its marketing user when the lead was created, as a claim
 * would. Then each inquiry's events are replayed, in memory, against the flow
 * in docs/database/regray-erd.md, its lead's won or lost among them: a step the
 * flow does not allow, a time out of order, payments that do not add up, or an
 * inquiry that does not end at the status its data says it `shows`, stops the
 * seeder. Then it is written: the inquiry and its project, handlers, its
 * schedule (through ScheduleService, the path sending an inquiry takes), and
 * every status update in order, each with its note and the files it carried
 * (the quotation and job order of a quote, the billing statement, a payment's
 * receipt and invoice), grouped on its history row. Every file goes through the
 * uploader rule (InquiryFileUploads). A cancelled inquiry's visits are
 * cancelled with it.
 *
 * A role's status is set only when its stage starts: sales when the lead is
 * won (negotiation), or at once for an inquiry sent on a lead already won or
 * for a direct client; logistics when sales is quoted (received); payment with
 * the billing statement (awaiting). Until then they are NULL, in the inquiry,
 * its history and its cards.
 *
 * Every inquiry is filed under its client's record (`clients`): a platform
 * record for an account, made the first time it is needed, or a direct
 * (walk-in) client from `direct_clients`, with its contacts. A direct client's
 * inquiry has no lead, starts sales with it, and posts no cards: there is no
 * account to have a Regray Team thread.
 *
 * A direct client's inquiry records the staff member who opened it
 * (`created_by`); a client's own records nobody. It is owned
 * (`owner_user_id`) by its data's `owner`, or by whoever opened it, who is
 * then sales; its owner is among its handlers, as WalkInInquiryService has it.
 *
 * Then the group chats marketing made for some inquiries, by the same rules as
 * GroupConversationService: one sales and one technical member, the client,
 * marketing as admin, and one accounting and one logistics member added later
 * by a sales member. Owners are never members. Every staff member put in a
 * group is assigned to its inquiry (`inquiry_handlers`) by whoever put them
 * there, as GroupConversationService::assign() does.
 *
 * Last, the inquiry cards: one per distinct snapshot of each inquiry, posted
 * where they would have gone at the time (the inquiry's group chat once it
 * existed, otherwise its client's Regray Team thread), every thread's cards in
 * the order they happened.
 *
 * Demo data only: it throws in production, like DemoSeeder.
 */
class InquirySeeder extends Seeder
{
    /** @var array<string, string> */
    private const STAFF_EMAILS = [
        'maria' => 'maria.alvarez@example.com',
        'daniel' => 'daniel.okafor@example.com',
        'sofia' => 'sofia.bianchi@example.com',
        'priya' => 'priya.raman@example.com',
        'kenji' => 'kenji.watanabe@example.com',
        'tomas' => 'tomas.lindqvist@example.com',
        'marketing' => 'marketing@example.com',
        'accounting' => 'accounting@example.com',
    ];

    /** @var array<string, string> */
    private const CLIENT_EMAILS = [
        'cl-elena' => 'elena.villanueva@brightleafdesign.example',
        'cl-rafael' => 'rafael.doming@thenorthgatecafe.example',
        'cl-nadine' => 'nadine.delacroix@lumenandco.example',
        'cl-ivan' => 'ivan.mercado@hillcrestshowroom.example',
        'cl-haruki' => 'haruki.nakamura@groveresidences.example',
        'cl-grace' => 'grace.lim@maplelane.example',
        'lucas' => 'lucas.moreau@example.com',
    ];

    /** @var array<string, User> */
    private array $users = [];

    /**
     * Each inquiry's seeded quotations, by inquiry id, with the time they were
     * sent: each posts a quotation card after the inquiry card of that move.
     *
     * @var array<int, list<array{file: InquiryFile, at: string}>>
     */
    private array $quotations = [];

    public function run(
        ScheduleService $scheduleService,
        SupportConversationService $support,
        MessageRepositoryInterface $messages,
        ConversationMemberRepositoryInterface $members,
        InquiryFileUploads $fileUploads,
        ClientService $clientService,
    ): void {
        if (app()->isProduction()) {
            throw new RuntimeException(
                'InquirySeeder cannot run in production. It writes demo inquiries for the demo clients.'
            );
        }

        $data = require database_path('seeders/data/inquiries.php');
        $leadData = array_column($data['leads'], null, 'reference');
        $leads = $this->seedLeads($support, $members, $data['leads']);
        $directClients = $this->seedDirectClients($data['direct_clients']);
        $inquiries = [];
        $snapshots = [];

        foreach ($data['inquiries'] as $inquiryData) {
            if (isset($inquiryData['direct_client'])) {
                $direct = $directClients[$inquiryData['direct_client']] ?? throw new RuntimeException("{$inquiryData['reference']}: unknown direct client {$inquiryData['direct_client']}.");
                $client = null;
                $record = $direct['client'];
                $lead = null;
                $openedBy = $direct['created_by'];
            } else {
                $client = $this->user($inquiryData['client_id']);
                $record = $clientService->findOrCreateForUser($client);
                $lead = $leadData[$inquiryData['lead']] ?? throw new RuntimeException("{$inquiryData['reference']}: unknown lead {$inquiryData['lead']}.");
                $openedBy = null;

                if ($lead['client_id'] !== $inquiryData['client_id']) {
                    throw new RuntimeException("{$inquiryData['reference']} is not for the client of {$lead['reference']}.");
                }
            }

            $replay = $this->replay($inquiryData, $lead, $openedBy);

            $inquiry = $this->seedInquiry($fileUploads, $inquiryData, $client, $record, $lead === null ? null : $leads[$lead['reference']], $replay['final'], $openedBy === null ? null : $this->user($openedBy));

            foreach ($inquiryData['handlers'] as $handlerName) {
                $inquiry->handlers()->create([
                    'user_id' => $this->user($handlerName)->id,
                    'assigned_by' => ($client ?? $this->user($openedBy))->id,
                    'assigned_at' => Carbon::parse($inquiryData['created_at']),
                ]);
            }

            $scheduleService->createForInquiry(
                $inquiry,
                $client,
                $inquiryData['project']['deadline_date'],
                $inquiryData['project']['deadline_time'],
                $client === null ? $this->user($openedBy) : null,
            );

            $this->seedEvents($fileUploads, $inquiry, $inquiryData, $replay['final']);

            if ($inquiry->status === InquiryStatus::Cancelled) {
                $scheduleService->cancelForInquiry($inquiry, Carbon::parse($replay['final']['cancelled_at']));
            }

            $inquiries[$inquiry->reference] = $inquiry;

            // A direct client has no thread for a card to go to.
            foreach ($client === null ? [] : $replay['snapshots'] as $snapshot) {
                $snapshots[] = ['inquiry' => $inquiry, ...$snapshot];
            }

            foreach ($client === null ? [] : $this->quotations[$inquiry->id] ?? [] as $quotation) {
                $snapshots[] = ['inquiry' => $inquiry, 'at' => $quotation['at'], 'quotation' => $quotation['file']];
            }
        }

        $groups = $this->seedGroups($members, $data['groups'], $inquiries);

        $this->postInquiryCards($support, $messages, $snapshots, $groups);

        $this->setSequence('inquiries_id_seq', (Inquiry::max('id') ?? 0) + 1);
        $this->setSequence('leads_id_seq', (Lead::max('id') ?? 0) + 1);
        $number = fn (string $reference): int => (int) preg_replace('/\D/', '', $reference);

        $this->setSequence('inquiry_reference_seq', Inquiry::query()->pluck('reference')->map($number)->max() ?? 1);
        $this->setSequence('lead_reference_seq', Lead::query()->pluck('reference')->map($number)->max() ?? 1);
    }

    /**
     * Each lead, one per client, held by its marketing user, with the first
     * row of its log: idle from nothing when it was created (by its marketing
     * user, who was given the thread, or by nobody when the client's inquiry
     * opened it). The client's Regray Team thread is given to the marketing
     * user when the lead was created, as a claim would.
     *
     * A lead has no status of its own. Its `won_at` or `lost_at` is when
     * marketing decided the inquiries the client had sent by then; replay()
     * turns that into each inquiry's decision, and seedInquiry() logs it.
     *
     * @param  list<array<string, mixed>>  $leadsData
     * @return array<string, Lead>
     */
    private function seedLeads(SupportConversationService $support, ConversationMemberRepositoryInterface $members, array $leadsData): array
    {
        $leads = [];
        $byClient = [];

        foreach ($leadsData as $leadData) {
            $reference = $leadData['reference'];
            $client = $this->user($leadData['client_id']);
            $marketing = $this->user($leadData['marketing']);
            $createdAt = Carbon::parse($leadData['created_at']);
            $decidedAt = $leadData['won_at'] ?? $leadData['lost_at'] ?? null;

            if (isset($leadData['won_at'], $leadData['lost_at'])) {
                throw new RuntimeException("{$reference} is won and lost; both are final.");
            }

            if ($decidedAt !== null && strcmp($decidedAt, $leadData['created_at']) <= 0) {
                throw new RuntimeException("{$reference} is decided before it was created.");
            }

            if (($byClient[$leadData['client_id']] ?? null) !== null) {
                throw new RuntimeException("{$reference}: {$leadData['client_id']} already has a lead, {$byClient[$leadData['client_id']]}.");
            }

            $byClient[$leadData['client_id']] = $reference;

            if (! $marketing->hasRole(Role::Marketing->value)) {
                throw new RuntimeException("{$reference} is held by someone outside marketing.");
            }

            $lead = Lead::query()->forceCreate([
                'reference' => $reference,
                'client_id' => $client->id,
                'marketing_user_id' => $marketing->id,
                'created_at' => $createdAt,
                'updated_at' => Carbon::parse($decidedAt ?? $leadData['created_at']),
            ]);

            $this->leadHistory($lead, null, LeadStatus::Idle, ($leadData['opened_by_inquiry'] ?? false) ? null : $marketing, $createdAt);

            $this->assignThread($support, $members, $client, $marketing, $createdAt);

            $leads[$reference] = $lead;
        }

        return $leads;
    }

    /**
     * The walk-ins with no account, as `direct` client records with their
     * contacts, normalised as the app stores them. Keyed by their data id,
     * with who took them down.
     *
     * @param  list<array<string, mixed>>  $directClientsData
     * @return array<string, array{client: Client, created_by: string}>
     */
    private function seedDirectClients(array $directClientsData): array
    {
        $records = [];

        foreach ($directClientsData as $clientData) {
            $client = Client::query()->forceCreate([
                'kind' => ClientKind::Direct,
                'user_id' => null,
                'full_name' => $clientData['full_name'],
                'created_by' => $this->user($clientData['created_by'])->id,
            ]);

            foreach ([ContactKind::Phone->value => $clientData['phone'], ContactKind::Email->value => $clientData['email'] ?? null] as $kind => $value) {
                if ($value !== null) {
                    $client->contacts()->create(['kind' => ContactKind::from($kind), 'value' => ContactKind::from($kind)->normalise($value)]);
                }
            }

            $records[$clientData['id']] = ['client' => $client, 'created_by' => $clientData['created_by']];
        }

        return $records;
    }

    /** Give the client's Regray Team thread to `$marketing` at `$at`, once, with its "assigned" notice. */
    private function assignThread(SupportConversationService $support, ConversationMemberRepositoryInterface $members, User $client, User $marketing, Carbon $at): void
    {
        $thread = $support->ensureFor($client);

        if ($thread->assigned_to !== null) {
            return;
        }

        $thread->forceFill(['assigned_to' => $marketing->id, 'assigned_at' => $at])->save();
        $members->upsert($thread->id, $marketing->id, ConversationMemberRole::Member);

        Message::query()->create([
            'conversation_id' => $thread->id,
            'type' => MessageType::System,
            'system_event' => SystemEvent::Assigned,
            'system_user_id' => $marketing->id,
            'created_at' => $at,
        ]);
    }

    /**
     * Play the inquiry's events against the flow, its lead's won or lost among
     * them, and keep every distinct snapshot (display status and the three
     * role statuses) with when it started. Throws on anything the flow does
     * not allow.
     *
     * A direct client's inquiry has no lead (`$lead` null): sales starts with
     * it, by `$openedBy`, who took the client down.
     *
     * @param  array<string, mixed>  $inquiryData
     * @param  array<string, mixed>|null  $lead
     * @return array{final: array{marketing: ?LeadStatus, marketing_at: ?string, marketing_by: ?string, sales: ?SalesStatus, payment: ?PaymentStatus, process: ?ProcessStatus, quote_revision: ?int, quoted_total: ?string, contract_total: ?string, changed: array<string, ?string>, sales_start: ?array{at: string, by: string}, cancelled_at: ?string, last_at: string}, snapshots: list<array{at: string, status: InquiryStatus, sales: ?SalesStatus, payment: ?PaymentStatus, process: ?ProcessStatus}>}
     */
    private function replay(array $inquiryData, ?array $lead, ?string $openedBy = null): array
    {
        $reference = $inquiryData['reference'];
        $createdAt = $inquiryData['created_at'];

        if ($lead === null) {
            $lead = ['reference' => null, 'created_at' => $createdAt, 'opened_by_inquiry' => true];
        }

        if (strcmp($lead['created_at'], $createdAt) > 0 && ! $this->isFirstLead($lead)) {
            throw new RuntimeException("{$reference} is older than {$lead['reference']}, but the client had a lead before it.");
        }

        $state = [
            'marketing' => null,
            'marketing_at' => null,
            'marketing_by' => null,
            'sales' => null,
            'payment' => null,
            'process' => null,
            'quote_revision' => null,
            'quoted_total' => null,
            'contract_total' => null,
            'changed' => ['sales' => null, 'payment' => null, 'process' => null],
            'sales_start' => null,
            'cancelled_at' => null,
            'last_at' => $createdAt,
        ];
        $hasJobOrder = false;
        $paidCentavos = 0;
        $refundedCentavos = 0;
        $snapshots = [];

        // Sent once marketing had won the client: marketing wins it as it
        // arrives, which starts its sales.
        if (isset($lead['won_at']) && strcmp($lead['won_at'], $createdAt) <= 0) {
            $state['marketing'] = LeadStatus::Won;
            $state['marketing_at'] = $createdAt;
            $state['marketing_by'] = $lead['marketing'];
            $state['sales'] = SalesStatus::Negotiation;
            $state['changed']['sales'] = $createdAt;
            $state['sales_start'] = ['at' => $createdAt, 'by' => $lead['marketing']];
        }

        // A direct client's: no lead, and sales starts with it, by staff.
        if ($lead['reference'] === null) {
            $state['sales'] = SalesStatus::Negotiation;
            $state['changed']['sales'] = $createdAt;
            $state['sales_start'] = ['at' => $createdAt, 'by' => $openedBy ?? throw new RuntimeException("{$reference}: who opened it?")];
        }

        $snapshot = function (string $at) use (&$state, &$hasJobOrder, &$snapshots): void {
            $current = [
                'status' => InquiryStatusCalculator::calculate($state['marketing'], $state['sales'], $state['process'], $hasJobOrder, $state['payment']),
                'sales' => $state['sales'],
                'payment' => $state['payment'],
                'process' => $state['process'],
            ];

            $last = end($snapshots);

            if ($last === false || array_diff_key($last, ['at' => true]) != $current) {
                $snapshots[] = ['at' => $at, ...$current];
            }
        };

        $snapshot($createdAt);

        $events = $inquiryData['events'];

        foreach (['won_at' => 'won', 'lost_at' => 'lost'] as $key => $to) {
            if (isset($lead[$key]) && strcmp($lead[$key], $createdAt) > 0) {
                $events[] = ['at' => $lead[$key], 'by' => $lead['marketing'], 'lead' => $to];
            }
        }

        usort($events, fn (array $first, array $second): int => strcmp($first['at'], $second['at']));

        foreach ($events as $event) {
            $at = $event['at'];
            $fail = fn (string $why) => throw new RuntimeException("{$reference} at {$at}: {$why}");

            if (strcmp($at, $state['last_at']) <= 0) {
                $fail('events have to run forward in time.');
            }

            $state['last_at'] = $at;
            $approved = $state['sales'] === SalesStatus::Won;

            if ($state['cancelled_at'] !== null) {
                $fail('nothing happens to a cancelled inquiry.');
            }

            if (isset($event['lead'])) {
                $state['marketing'] = LeadStatus::from($event['lead']);
                $state['marketing_at'] = $at;
                $state['marketing_by'] = $event['by'];

                // Marketing winning the inquiry starts sales, at negotiation.
                if ($state['marketing'] === LeadStatus::Won) {
                    $state['sales'] = SalesStatus::Negotiation;
                    $state['changed']['sales'] = $at;
                    $state['sales_start'] = ['at' => $at, 'by' => $event['by']];
                } else {
                    $state['cancelled_at'] = $at;
                }
            } elseif (isset($event['sales'])) {
                $to = SalesStatus::from($event['sales']);

                $refundable = in_array($state['payment'], [PaymentStatus::Partial, PaymentStatus::FullyPaid], true) && $state['process'] !== ProcessStatus::Completed;

                if (! in_array($to->value, InquiryStatusRules::allowedTargets(InquiryStatusTrack::Sales, $state['sales']?->value, isset($state['quote_revision']), null, $refundable), true)) {
                    $fail("sales does not move from {$state['sales']?->value} to {$to->value}.");
                }

                $state['sales'] = $to;
                $state['changed']['sales'] = $at;

                if ($to === SalesStatus::Rejected) {
                    $state['cancelled_at'] = $at;
                }

                // Quoting, first or revised, is a new revision with its total.
                if (InquiryStatusRules::isQuote($to->value)) {
                    if (! isset($event['quote'])) {
                        $fail('quoting carries the quoted total.');
                    }

                    $state['quote_revision'] = ($state['quote_revision'] ?? 0) + 1;
                    $state['quoted_total'] = $event['quote'];
                }

                // The client's approval comes with the job order.
                if ($to === SalesStatus::Won && isset($event['job_order'])) {
                    $hasJobOrder = true;
                }
            } elseif (isset($event['job_order'])) {
                // The job order after the win, by sales: it starts logistics
                // only when a payment is already in.
                if ($state['sales'] !== SalesStatus::Won || $hasJobOrder) {
                    $fail('the job order comes once, after the win.');
                }

                $hasJobOrder = true;

                if (in_array($state['payment'], [PaymentStatus::Partial, PaymentStatus::FullyPaid], true)) {
                    $state['process'] = ProcessStatus::OrderReceived;
                    $state['changed']['process'] = $at;
                }
            } elseif (isset($event['payment_status'])) {
                $to = PaymentStatus::from($event['payment_status']);

                if (! in_array($state['sales'], [SalesStatus::Won, SalesStatus::ForRefund], true) || ! in_array($to->value, InquiryStatusRules::allowedTargets(InquiryStatusTrack::Payment, $state['payment']?->value, false, $state['sales']->value), true)) {
                    $fail("payment does not move from {$state['payment']?->value} to {$to->value}, or the inquiry is not won.");
                }

                if ($to === PaymentStatus::Awaiting) {
                    // Accounting bills the quoted total as the contract.
                    $state['contract_total'] = $state['quoted_total'];
                } elseif (InquiryStatusRules::isRefund($to->value)) {
                    // A refund returns part of what is held, or all of it.
                    $refundedCentavos += $this->centavos($event['refund'] ?? '0');
                    $held = $paidCentavos - $refundedCentavos;

                    if (! isset($event['refund']) || $held < 0 || ($to === PaymentStatus::Refunded) !== ($held === 0)) {
                        $fail("refunds of {$refundedCentavos} centavos against {$paidCentavos} paid are not {$to->value}.");
                    }
                } else {
                    $paidCentavos += $this->centavos($event['payment'] ?? '0');
                    $total = $this->centavos($state['contract_total']);

                    if (($to === PaymentStatus::FullyPaid) !== ($paidCentavos === $total) || $paidCentavos > $total || ! isset($event['payment'])) {
                        $fail("payments of {$paidCentavos} centavos against {$total} are not {$to->value}.");
                    }
                }

                $state['payment'] = $to;
                $state['changed']['payment'] = $at;

                // A full refund rejects sales, which cancels the inquiry.
                if ($to === PaymentStatus::Refunded) {
                    $state['sales'] = SalesStatus::Rejected;
                    $state['changed']['sales'] = $at;
                    $state['cancelled_at'] = $at;
                }

                // The first payment on an inquiry with its job order starts logistics.
                if (in_array($to, [PaymentStatus::Partial, PaymentStatus::FullyPaid], true) && $hasJobOrder && $state['process'] === null) {
                    $state['process'] = ProcessStatus::OrderReceived;
                    $state['changed']['process'] = $at;
                }
            } elseif (isset($event['process'])) {
                $to = ProcessStatus::from($event['process']);

                if (! in_array($to->value, InquiryStatusRules::allowedTargets(InquiryStatusTrack::Process, $state['process']?->value), true)) {
                    $fail("logistics does not move from {$state['process']?->value} to {$to->value}.");
                }

                $state['process'] = $to;
                $state['changed']['process'] = $at;
            } else {
                $fail('unknown event.');
            }

            $snapshot($at);
        }

        $shows = InquiryStatusCalculator::calculate($state['marketing'], $state['sales'], $state['process'], $hasJobOrder, $state['payment']);

        if ($shows->value !== $inquiryData['shows']) {
            throw new RuntimeException("{$reference} shows {$shows->value}, not {$inquiryData['shows']}.");
        }

        return ['final' => $state, 'snapshots' => $snapshots];
    }

    /**
     * Whether the lead is its client's first: an inquiry sent before its lead
     * existed waited, unlinked, for the claim that created the lead. A later
     * lead is opened by the inquiry itself, so nothing can be older than it.
     *
     * @param  array<string, mixed>  $lead
     */
    private function isFirstLead(array $lead): bool
    {
        return ! ($lead['opened_by_inquiry'] ?? false);
    }

    /**
     * The inquiry at its final statuses, on its lead, with the project it is:
     * its service, its stones and the client's attachments.
     *
     * @param  array<string, mixed>  $inquiryData
     * @param  array{marketing: ?LeadStatus, marketing_at: ?string, marketing_by: ?string, sales: ?SalesStatus, payment: ?PaymentStatus, process: ?ProcessStatus, quote_revision: ?int, quoted_total: ?string, contract_total: ?string, changed: array<string, ?string>, sales_start: ?array{at: string, by: string}, cancelled_at: ?string, last_at: string}  $final
     */
    private function seedInquiry(InquiryFileUploads $fileUploads, array $inquiryData, ?User $client, Client $record, ?Lead $lead, array $final, ?User $openedBy): Inquiry
    {
        $project = $inquiryData['project'];
        $createdAt = Carbon::parse($inquiryData['created_at']);
        $service = Service::query()->with('detailFields.options')->where('slug', $project['service'])->firstOrFail();
        $asked = $service->detailFields->keyBy('slug');

        $inquiry = Inquiry::query()->forceCreate([
            'reference' => $inquiryData['reference'],
            'client_id' => $client?->id,
            'client_record_id' => $record->id,
            'lead_id' => $lead?->id,
            'created_by' => $openedBy?->id,
            'owner_user_id' => $client === null ? $this->user($inquiryData['owner'] ?? $inquiryData['handlers'][0] ?? throw new RuntimeException("{$inquiryData['reference']}: a walk-in's inquiry needs an owner."))->id : null,
            'marketing_status' => $final['marketing'],
            'marketing_status_changed_at' => $this->time($final['marketing_at']),
            'name' => $project['name'],
            'service_id' => $service->id,
            ...$this->fulfillment($inquiryData['reference'], $project, $service),
            'notes' => $project['notes'],
            'sales_status' => $final['sales'],
            'sales_status_changed_at' => $this->time($final['changed']['sales']),
            'payment_status' => $final['payment'],
            'payment_status_changed_at' => $this->time($final['changed']['payment']),
            'process_status' => $final['process'],
            'process_status_changed_at' => $this->time($final['changed']['process']),
            'completed_at' => $final['process'] === ProcessStatus::Completed ? $this->time($final['changed']['process']) : null,
            'quoted_total' => $final['quoted_total'],
            'contract_total' => $final['contract_total'],
            'quote_revision' => $final['quote_revision'],
            'created_at' => $createdAt,
            'updated_at' => Carbon::parse($final['last_at']),
        ]);

        $inquiry->setRelation('lead', $lead);

        if ($lead !== null && $final['marketing'] !== null) {
            $this->leadHistory($lead, null, $final['marketing'], $this->user($final['marketing_by']), Carbon::parse($final['marketing_at']), $inquiry->id);
        }

        if ($client === null && $project['attachments'] !== []) {
            throw new RuntimeException("{$inquiryData['reference']}: a direct client has no account to upload attachments.");
        }

        $brandIds = [];

        foreach ($project['stones'] as $stoneSort => $stoneSelection) {
            $stone = Stone::query()
                ->whereHas('type', fn ($query) => $query->where('slug', $stoneSelection['stone_slug']))
                ->orderBy('sort_order')->orderBy('id')->firstOrFail();

            // Only the answers its service asks for, as the Inquire page sends them.
            $inquiry->stones()->create([
                'stone_id' => $stone->id,
                'edge_profile_id' => $asked->has(DetailField::EDGE_PROFILE) ? EdgeProfile::where('name', $stoneSelection['edge'])->firstOrFail()->id : null,
                'finish_id' => $asked->has(DetailField::FINISH) ? Finish::where('name', $stoneSelection['finish'])->firstOrFail()->id : null,
                'quantity' => $asked->has(DetailField::QUANTITY) ? $stoneSelection['quantity'] : null,
                // A slab size and thickness only where asked, which the data then has to give.
                ...$this->measurements($asked->has(DetailField::SLAB_SIZE), $asked->has(DetailField::THICKNESS), $stoneSelection, $inquiryData['reference']),
                'sort_order' => $stoneSort,
            ]);

            $brandIds[] = $stone->brand_id;
        }

        $this->seedBrands($inquiry, $project, $brandIds);
        $this->seedAnswers($inquiry, $service, $inquiryData['reference'], $project['answers']);

        foreach ($client === null ? [] : $project['attachments'] as $fileData) {
            $this->seedFile($fileUploads, $inquiry, $client, DocumentType::ClientAttachment, $fileData, $createdAt);
        }

        return $inquiry;
    }

    /**
     * The inquiry's delivery method and the copy of its address, as the
     * Inquire page sends them for its service: a delivery unless the data says
     * pickup (no place), no method for an installed service.
     *
     * @param  array<string, mixed>  $project
     * @return array<string, mixed>
     */
    private function fulfillment(string $reference, array $project, Service $service): array
    {
        $method = $service->fulfillment->asksDeliveryMethod() ? DeliveryMethod::from($project['delivery_method'] ?? DeliveryMethod::Delivery->value) : null;
        $place = $project['place'] ?? null;

        if (($method === DeliveryMethod::Pickup) !== ($place === null)) {
            throw new RuntimeException("{$reference}: a pickup has no place, and a delivery or an installation needs one.");
        }

        if (! $service->fulfillment->asksDeliveryMethod() && isset($project['delivery_method'])) {
            throw new RuntimeException("{$reference}: {$service->slug} is installed on site and asks no delivery method.");
        }

        return [
            'delivery_method' => $method,
            ...($place === null ? PhAddress::emptyColumns(Inquiry::ADDRESS_PREFIX) : DemoPlaces::address($place)->toColumns(Inquiry::ADDRESS_PREFIX)),
        ];
    }

    /**
     * The brands the client would like: the data's `brands`, else those of
     * its stones; No preference when it says so or has no stones.
     *
     * @param  array<string, mixed>  $project
     * @param  list<int>  $stoneBrandIds
     */
    private function seedBrands(Inquiry $inquiry, array $project, array $stoneBrandIds): void
    {
        $brandIds = isset($project['brands'])
            ? Brand::query()->whereIn('slug', $project['brands'])->pluck('id')->all()
            : array_values(array_unique($stoneBrandIds));

        if (($project['brand_no_preference'] ?? false) || $brandIds === []) {
            $inquiry->forceFill(['brand_no_preference' => true])->saveQuietly();

            return;
        }

        $inquiry->brands()->sync($brandIds);
    }

    /**
     * A stone row's slab size and thickness columns, from the data's
     * `[width, length, unit]` and `[value, unit]`: null where the service does
     * not ask them, refused where it does and the row leaves them out.
     *
     * @param  array<string, mixed>  $stoneSelection
     * @return array{slab_width: ?string, slab_length: ?string, slab_unit: ?SizeUnit, thickness: ?string, thickness_unit: ?ThicknessUnit}
     */
    private function measurements(bool $asksSize, bool $asksThickness, array $stoneSelection, string $reference): array
    {
        if (($asksSize && ! isset($stoneSelection['slab_size'])) || ($asksThickness && ! isset($stoneSelection['thickness']))) {
            throw new RuntimeException("{$reference}: a {$stoneSelection['stone_slug']} row needs its slab size and thickness.");
        }

        $size = $asksSize ? $stoneSelection['slab_size'] : null;
        $thickness = $asksThickness ? $stoneSelection['thickness'] : null;

        return [
            'slab_width' => $size === null ? null : Measurement::number($size[0]),
            'slab_length' => $size === null ? null : Measurement::number($size[1]),
            'slab_unit' => $size === null ? null : SizeUnit::from($size[2]),
            'thickness' => $thickness === null ? null : Measurement::number($thickness[0]),
            'thickness_unit' => $thickness === null ? null : ThicknessUnit::from($thickness[1]),
        ];
    }

    /**
     * The project's answers to its service's project-scope questions, as
     * sending the inquiry stores them. Like the Inquire page, it refuses a
     * required question left unanswered and an answer to one the service does
     * not ask.
     *
     * @param  array<string, string|int|float|bool>  $answers  By question slug; a closed option by its slug.
     */
    private function seedAnswers(Inquiry $inquiry, Service $service, string $reference, array $answers): void
    {
        $questions = $service->detailFields->filter(fn (DetailField $field): bool => $field->scope === DetailFieldScope::Project)->keyBy('slug');

        foreach (array_diff(array_keys($answers), $questions->keys()->all()) as $slug) {
            throw new RuntimeException("{$reference}: {$service->slug} does not ask {$slug}.");
        }

        foreach ($questions as $slug => $field) {
            if (! array_key_exists($slug, $answers)) {
                if ($field->isRequiredByService()) {
                    throw new RuntimeException("{$reference}: {$service->slug} needs an answer to {$slug}.");
                }

                continue;
            }

            $inquiry->detailAnswers()->create($field->answerAttributes($answers[$slug]));
        }
    }

    /**
     * Every status update, in order, as staff made it in the Update Status
     * dialog: its history row with its note and the files it carried, which
     * point to that row.
     *
     * - Sales starts at negotiation, from nothing (when the lead was won, by
     *   whoever won it; with the inquiry, by its client or by staff).
     * - Each `quoted` carries the quotation (the next revision), and the job
     *   order when sales has it; the job order, with the quote or later, starts
     *   logistics at order_received, from nothing.
     * - Payment starts at awaiting with the billing statement; each payment
     *   after is a `payment_transactions` row with a receipt and an invoice.
     * - A full refund rejects sales right after, by the same user, as the
     *   Update Status dialog does.
     *
     * @param  array<string, mixed>  $inquiryData
     * @param  array{sales_start: ?array{at: string, by: string}}  $final
     */
    private function seedEvents(InquiryFileUploads $fileUploads, Inquiry $inquiry, array $inquiryData, array $final): void
    {
        $current = ['sales' => null, 'payment' => null, 'process' => null];
        $revision = 0;
        $receipt = 0;
        $refund = 0;
        $hasJobOrder = false;

        $move = function (InquiryStatusTrack $track, string $to, User $by, Carbon $at, ?string $note) use ($inquiry, &$current): InquiryStatusHistory {
            $history = $this->history($inquiry, $track, $current[$track->value], $to, $by, $at, $note);
            $current[$track->value] = $to;

            return $history;
        };

        if ($final['sales_start'] !== null) {
            $move(InquiryStatusTrack::Sales, SalesStatus::Negotiation->value, $this->user($final['sales_start']['by']), Carbon::parse($final['sales_start']['at']), null);
        }

        foreach ($inquiryData['events'] as $event) {
            $at = Carbon::parse($event['at']);
            $by = $this->user($event['by']);
            $note = $event['note'] ?? null;

            if (isset($event['sales'])) {
                $history = $move(InquiryStatusTrack::Sales, $event['sales'], $by, $at, $note);

                if (InquiryStatusRules::isQuote($event['sales'])) {
                    $revision++;

                    $this->quotations[$inquiry->id][] = [
                        'file' => $this->seedFile($fileUploads, $inquiry, $by, DocumentType::Quotation, [
                            'name' => "{$inquiry->reference}-quotation-r{$revision}.pdf",
                            'size_bytes' => 180224 + $revision * 4096,
                            'mime_type' => 'application/pdf',
                        ], $at, $revision, $history),
                        'at' => $event['at'],
                    ];
                }

                if ($event['sales'] === SalesStatus::Won->value && isset($event['job_order'])) {
                    $this->seedJobOrder($fileUploads, $inquiry, $by, $event['job_order'], $at, $history);
                    $hasJobOrder = true;
                }

                if ($event['sales'] === SalesStatus::ForRefund->value) {
                    $this->seedFile($fileUploads, $inquiry, $by, DocumentType::RefundRequest, [
                        'name' => "{$inquiry->reference}-refund-request.pdf",
                        'size_bytes' => 65536,
                        'mime_type' => 'application/pdf',
                    ], $at, null, $history);
                }
            } elseif (isset($event['job_order'])) {
                $paid = in_array($current['payment'], [PaymentStatus::Partial->value, PaymentStatus::FullyPaid->value], true);
                $history = $paid ? $move(InquiryStatusTrack::Process, ProcessStatus::OrderReceived->value, $by, $at, $note) : null;
                $this->seedJobOrder($fileUploads, $inquiry, $by, $event['job_order'], $at, $history);
                $hasJobOrder = true;
            } elseif (isset($event['payment_status'])) {
                if (isset($event['payment'])) {
                    PaymentTransaction::query()->forceCreate([
                        'inquiry_id' => $inquiry->id,
                        'kind' => 'payment',
                        'amount' => $event['payment'],
                        'reference' => $event['reference'],
                        'recorded_by' => $by->id,
                        'received_at' => $at,
                        'created_at' => $at,
                    ]);
                }

                if (isset($event['refund'])) {
                    PaymentTransaction::query()->forceCreate([
                        'inquiry_id' => $inquiry->id,
                        'kind' => 'refund',
                        'amount' => $event['refund'],
                        'reference' => $event['reference'] ?? null,
                        'recorded_by' => $by->id,
                        'received_at' => $at,
                        'created_at' => $at,
                    ]);
                }

                $history = $move(InquiryStatusTrack::Payment, $event['payment_status'], $by, $at, $note);

                if (InquiryStatusRules::isRefund($event['payment_status'])) {
                    $refund++;

                    $this->seedFile($fileUploads, $inquiry, $by, DocumentType::RefundSettlement, [
                        'name' => "{$inquiry->reference}-refund-settled-{$refund}.pdf",
                        'size_bytes' => 81920,
                        'mime_type' => 'application/pdf',
                    ], $at, null, $history);

                    if ($event['payment_status'] === PaymentStatus::Refunded->value) {
                        $move(InquiryStatusTrack::Sales, SalesStatus::Rejected->value, $by, $at, InquiryStatusUpdateService::REFUNDED_NOTE);
                    }
                } elseif ($event['payment_status'] === PaymentStatus::Awaiting->value) {
                    $this->seedFile($fileUploads, $inquiry, $by, DocumentType::BillingStatement, [
                        'name' => "{$inquiry->reference}-billing-statement.pdf",
                        'size_bytes' => 86016,
                        'mime_type' => 'application/pdf',
                    ], $at, null, $history);
                } else {
                    $receipt++;

                    $this->seedFile($fileUploads, $inquiry, $by, DocumentType::Receipt, [
                        'name' => "{$inquiry->reference}-receipt-{$receipt}-{$event['reference']}.pdf",
                        'size_bytes' => 98304,
                        'mime_type' => 'application/pdf',
                    ], $at, null, $history);
                    $this->seedFile($fileUploads, $inquiry, $by, DocumentType::Invoice, [
                        'name' => "{$inquiry->reference}-invoice-{$receipt}.pdf",
                        'size_bytes' => 73728,
                        'mime_type' => 'application/pdf',
                    ], $at, null, $history);

                    // The first payment on an inquiry with its job order starts logistics.
                    if ($hasJobOrder && $current['process'] === null) {
                        $move(InquiryStatusTrack::Process, ProcessStatus::OrderReceived->value, $by, $at, $note);
                    }
                }
            } elseif (isset($event['process'])) {
                $move(InquiryStatusTrack::Process, $event['process'], $by, $at, $note);
            }
        }
    }

    /** The job order, internal, in the group of the update it came with. */
    private function seedJobOrder(InquiryFileUploads $fileUploads, Inquiry $inquiry, User $by, string $name, Carbon $at, ?InquiryStatusHistory $update): void
    {
        $this->seedFile($fileUploads, $inquiry, $by, DocumentType::JobOrder, [
            'name' => "{$name}.pdf",
            'size_bytes' => 241664,
            'mime_type' => 'application/pdf',
        ], $at, null, $update);
    }

    /**
     * The group chats marketing made for some inquiries, by
     * GroupConversationService's rules: the inquiry's lead's marketing user
     * creates it and is its admin, picks sales and technical staff, and every
     * owner and the client are in it. Later a sales member adds accounting and
     * logistics.
     *
     * @param  list<array<string, mixed>>  $groupsData
     * @param  array<string, Inquiry>  $inquiries
     * @return array<int, array{conversation: Conversation, created_at: string}> Keyed by inquiry id.
     */
    private function seedGroups(ConversationMemberRepositoryInterface $members, array $groupsData, array $inquiries): array
    {
        $groups = [];

        foreach ($groupsData as $groupData) {
            $inquiry = $inquiries[$groupData['inquiry']] ?? throw new RuntimeException("Group {$groupData['name']}: unknown inquiry {$groupData['inquiry']}.");
            $creator = Lead::query()->whereKey($inquiry->lead_id)->firstOrFail()->marketingUser()->firstOrFail();
            $createdAt = Carbon::parse($groupData['created_at']);

            if ($createdAt->lessThan($inquiry->created_at)) {
                throw new RuntimeException("Group {$groupData['name']} is older than {$inquiry->reference}.");
            }

            // As GroupConversationService::create(): a group chat waits for marketing to win the inquiry.
            if (! $inquiry->refresh()->isWonByMarketing()) {
                throw new RuntimeException("Group {$groupData['name']}: {$inquiry->reference} is not won.");
            }

            $conversation = Conversation::query()->forceCreate([
                'type' => ConversationType::Group,
                'name' => $groupData['name'],
                'inquiry_id' => $inquiry->id,
                'created_by' => $creator->id,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            $this->requireOneEach($groupData['staff'], GroupConversationService::GROUP_STAFF_ROLES, $groupData['name']);

            foreach ($groupData['staff'] as $key) {
                $members->upsert($conversation->id, $this->user($key)->id, ConversationMemberRole::Member);
            }

            $this->assignHandlers($inquiry, $groupData['staff'], $creator, $createdAt);

            $members->upsert($conversation->id, $inquiry->client_id, ConversationMemberRole::Member);
            $members->upsert($conversation->id, $creator->id, ConversationMemberRole::Admin);

            if (isset($groupData['later'])) {
                $adder = $this->user($groupData['later']['by']);

                if (! in_array($groupData['later']['by'], $groupData['staff'], true) || ! $adder->hasRole(Role::Sales->value)) {
                    throw new RuntimeException("Group {$groupData['name']}: only a sales member adds people later.");
                }

                $this->requireOneEach($groupData['later']['add'], GroupConversationService::LATER_STAFF_ROLES, $groupData['name']);

                foreach ($groupData['later']['add'] as $key) {
                    $members->upsert($conversation->id, $this->user($key)->id, ConversationMemberRole::Member);
                }

                $this->assignHandlers($inquiry, $groupData['later']['add'], $adder, Carbon::parse($groupData['later']['at']));
            }

            $groups[$inquiry->id] = ['conversation' => $conversation, 'created_at' => $groupData['created_at']];
        }

        return $groups;
    }

    /**
     * Exactly one person for each of `$roles`, and nobody else.
     *
     * @param  list<string>  $keys
     * @param  list<Role>  $roles
     */
    private function requireOneEach(array $keys, array $roles, string $group): void
    {
        $users = array_map(fn (string $key): User => $this->user($key), $keys);

        foreach ($roles as $role) {
            $holders = array_filter($users, fn (User $user): bool => $user->hasRole($role->value));

            if (count($holders) !== 1) {
                throw new RuntimeException("Group {$group} takes exactly one {$role->value} member.");
            }
        }

        if (count($users) !== count($roles)) {
            throw new RuntimeException("Group {$group}: only one member for each of its roles may be added.");
        }
    }

    /**
     * Assign the group's staff to its inquiry, by whoever put them in it. One
     * already assigned from the inquiry's own data stays as they were.
     *
     * @param  list<string>  $keys
     */
    private function assignHandlers(Inquiry $inquiry, array $keys, User $by, Carbon $at): void
    {
        InquiryHandler::query()->insertOrIgnore(array_map(fn (string $key): array => [
            'inquiry_id' => $inquiry->id,
            'user_id' => $this->user($key)->id,
            'assigned_by' => $by->id,
            'assigned_at' => $at,
        ], $keys));
    }

    /**
     * One file on the inquiry, by whoever the uploader rule allows.
     *
     * @param  array{name: string, size_bytes: int, mime_type: string}  $fileData
     */
    private function seedFile(InquiryFileUploads $fileUploads, Inquiry $inquiry, User $uploader, DocumentType $type, array $fileData, Carbon $at, ?int $revision = null, ?InquiryStatusHistory $update = null): InquiryFile
    {
        $fileUploads->authorize($uploader, $type, $inquiry);
        $storedName = Str::uuid()->toString().'.'.strtolower(pathinfo($fileData['name'], PATHINFO_EXTENSION));

        return $inquiry->files()->forceCreate([
            'document_type' => $type,
            'status_history_id' => $update?->id,
            'revision' => $revision,
            'disk' => 'local',
            'path' => 'inquiries/'.$inquiry->reference.'/'.$storedName,
            'stored_name' => $storedName,
            'original_name' => $fileData['name'],
            'mime_type' => $fileData['mime_type'],
            'size_bytes' => $fileData['size_bytes'],
            'sha256' => hash('sha256', $inquiry->reference.'/'.$fileData['name']),
            'uploaded_by' => $uploader->id,
            'created_at' => $at,
        ]);
    }

    private function history(Inquiry $inquiry, InquiryStatusTrack $track, ?string $from, string $to, User $by, Carbon $at, ?string $note = null): InquiryStatusHistory
    {
        return InquiryStatusHistory::query()->create([
            'inquiry_id' => $inquiry->id,
            'track' => $track,
            'from_status' => $from,
            'to_status' => $to,
            'changed_by' => $by->id,
            'note' => $note,
            'changed_at' => $at,
        ]);
    }

    private function leadHistory(Lead $lead, ?LeadStatus $from, LeadStatus $to, ?User $by, Carbon $at, ?int $inquiryId = null): void
    {
        LeadStatusHistory::query()->create([
            'lead_id' => $lead->id,
            'inquiry_id' => $inquiryId,
            'from_status' => $from,
            'to_status' => $to,
            'changed_by' => $by?->id,
            'changed_at' => $at,
        ]);
    }

    /**
     * Every snapshot into the thread it would have gone to at the time (the
     * inquiry's group chat once it existed, otherwise its client's Regray Team
     * thread), oldest first across all the inquiries, so a client with several
     * reads them in the order they happened. A quotation's card follows the
     * inquiry card of the move that sent it.
     *
     * @param  list<array{inquiry: Inquiry, at: string, status: InquiryStatus, sales: ?SalesStatus, payment: ?PaymentStatus, process: ?ProcessStatus}|array{inquiry: Inquiry, at: string, quotation: InquiryFile}>  $snapshots
     * @param  array<int, array{conversation: Conversation, created_at: string}>  $groups
     */
    private function postInquiryCards(SupportConversationService $support, MessageRepositoryInterface $messages, array $snapshots, array $groups): void
    {
        usort($snapshots, fn (array $first, array $second): int => [$first['at'], $first['inquiry']->id, isset($first['quotation'])] <=> [$second['at'], $second['inquiry']->id, isset($second['quotation'])]);

        foreach ($snapshots as $snapshot) {
            $group = $groups[$snapshot['inquiry']->id] ?? null;
            $thread = $group !== null && strcmp($group['created_at'], $snapshot['at']) <= 0
                ? $group['conversation']
                : $support->ensureFor($snapshot['inquiry']->client()->firstOrFail());

            if (isset($snapshot['quotation'])) {
                $messages->createQuotationCardOnce(
                    $thread->id,
                    $snapshot['inquiry']->id,
                    $snapshot['quotation']->id,
                    QuotationCardSnapshot::of($snapshot['quotation']),
                    Carbon::parse($snapshot['at']),
                );

                continue;
            }

            $messages->createInquiryCardOnce(
                $thread->id,
                $snapshot['inquiry']->id,
                $snapshot['status'],
                $snapshot['sales'],
                $snapshot['payment'],
                $snapshot['process'],
                Carbon::parse($snapshot['at']),
            );
        }
    }

    private function time(?string $at): ?Carbon
    {
        return $at === null ? null : Carbon::parse($at);
    }

    private function centavos(string $amount): int
    {
        return (int) round((float) $amount * 100);
    }

    private function user(string $key): User
    {
        return $this->users[$key] ??= User::query()
            ->where('email', self::STAFF_EMAILS[$key] ?? self::CLIENT_EMAILS[$key])
            ->firstOrFail();
    }

    private function setSequence(string $sequenceName, int $nextValue): void
    {
        DB::statement("SELECT setval('{$sequenceName}', {$nextValue})");
    }
}
