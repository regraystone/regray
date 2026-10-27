<?php

namespace Database\Seeders;

use App\Enums\DocumentType;
use App\Enums\InquiryStatus;
use App\Enums\InquiryStatusTrack;
use App\Enums\MarketingStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProcessStatus;
use App\Enums\SalesStatus;
use App\Models\EdgeProfile;
use App\Models\Finish;
use App\Models\Inquiry;
use App\Models\InquiryStatusHistory;
use App\Models\PaymentTransaction;
use App\Models\ProjectCategory;
use App\Models\Service;
use App\Models\Stone;
use App\Models\User;
use App\Policies\InquiryFileUploads;
use App\Repositories\Contracts\MessageRepositoryInterface;
use App\Services\InquiryStatusCalculator;
use App\Services\Message\SupportConversationService;
use App\Services\ScheduleService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The demo clients' inquiries and everything that happened to them, from
 * database/seeders/data/inquiries.php. One inquiry is one project.
 *
 * Each inquiry's events are replayed first, in memory, against the flow in
 * docs/database/regray-erd.md: a step the flow does not allow, a time out of
 * order, payments that do not add up, or an inquiry that does not end at the
 * status its data says it `shows`, stops the seeder. Then it is written in this
 * order: the inquiry and its project, handlers, its schedule (through
 * ScheduleService, the path sending an inquiry takes), marketing and sales
 * history, quotation issues, the client's approval, the job order file,
 * payments with their receipts, logistics history. Every file goes through the
 * uploader rule (InquiryFileUploads). A cancelled inquiry's visits are
 * cancelled with it.
 *
 * A role's status is set only when its stage starts: marketing at creation
 * (pending), sales when marketing is won (negotiation), payment and process
 * together when the client approves the quotation (awaiting and received).
 * Until then they are NULL, in the inquiry, its history and its cards.
 *
 * Last, the inquiry cards: one per distinct snapshot of each inquiry, posted
 * into its client's Regray Team thread (SupportConversationService::cardThreadFor),
 * every client's cards in the order they happened.
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
    ];

    /** The order logistics moves through. On hold and delayed sit beside it. */
    private const PROCESS_STEPS = ['received', 'measure', 'sourcing', 'production', 'quality_check', 'ready', 'dispatched', 'installation', 'completed'];

    /** @var array<string, User> */
    private array $users = [];

    public function run(
        ScheduleService $scheduleService,
        SupportConversationService $support,
        MessageRepositoryInterface $messages,
        InquiryFileUploads $fileUploads,
    ): void {
        if (app()->isProduction()) {
            throw new RuntimeException(
                'InquirySeeder cannot run in production. It writes demo inquiries for the demo clients.'
            );
        }

        $data = require database_path('seeders/data/inquiries.php');
        $cards = [];

        foreach ($data['inquiries'] as $inquiryData) {
            $client = $this->user($inquiryData['client_id']);
            $replay = $this->replay($inquiryData);

            $inquiry = $this->seedInquiry($fileUploads, $inquiryData, $client, $replay['final']);

            foreach ($inquiryData['handlers'] as $handlerName) {
                $inquiry->handlers()->create([
                    'user_id' => $this->user($handlerName)->id,
                    'assigned_by' => $client->id,
                    'assigned_at' => Carbon::parse($inquiryData['created_at']),
                ]);
            }

            $scheduleService->createForInquiry(
                $inquiry,
                $client,
                $inquiryData['project']['deadline_date'],
                $inquiryData['project']['deadline_time'],
            );

            $this->seedHistory($inquiry, $client, $inquiryData);
            $this->seedQuotations($fileUploads, $inquiry, $inquiryData['events']);
            $this->seedApproval($inquiry, $inquiryData['events']);
            $this->seedJobOrder($fileUploads, $inquiry, $inquiryData['events']);
            $this->seedPayments($fileUploads, $inquiry, $inquiryData['events']);
            $this->seedLogistics($inquiry, $inquiryData['events']);

            if ($inquiry->status === InquiryStatus::Cancelled) {
                $scheduleService->cancelForInquiry($inquiry, Carbon::parse(end($inquiryData['events'])['at']));
            }

            foreach ($replay['snapshots'] as $snapshot) {
                $cards[] = ['inquiry' => $inquiry, ...$snapshot];
            }
        }

        $this->postInquiryCards($support, $messages, $cards);

        $this->setSequence('inquiries_id_seq', (Inquiry::max('id') ?? 0) + 1);

        $lastReference = Inquiry::query()->pluck('reference')
            ->map(fn (string $reference): int => (int) preg_replace('/\D/', '', $reference))
            ->max();

        if ($lastReference !== null) {
            $this->setSequence('inquiry_reference_seq', $lastReference);
        }
    }

    /**
     * Play the inquiry's events against the flow, from the four defaults, and
     * keep every distinct snapshot (display status and the four role statuses)
     * with when it started. Throws on anything the flow does not allow.
     *
     * @param  array<string, mixed>  $inquiryData
     * @return array{final: array{marketing: MarketingStatus, sales: ?SalesStatus, payment: ?PaymentStatus, process: ?ProcessStatus, quote_revision: ?int, quoted_total: ?string, changed: array<string, ?string>, last_at: string}, snapshots: list<array{at: string, status: InquiryStatus, marketing: MarketingStatus, sales: ?SalesStatus, payment: ?PaymentStatus, process: ?ProcessStatus}>}
     */
    private function replay(array $inquiryData): array
    {
        $reference = $inquiryData['reference'];
        $createdAt = $inquiryData['created_at'];

        $state = [
            'marketing' => MarketingStatus::Pending,
            'sales' => null,
            'payment' => null,
            'process' => null,
            'quote_revision' => null,
            'quoted_total' => null,
            'changed' => ['marketing' => $createdAt, 'sales' => null, 'payment' => null, 'process' => null],
            'last_at' => $createdAt,
        ];
        $hasJobOrder = false;
        $paidCentavos = 0;
        $snapshots = [];

        $snapshot = function (string $at) use (&$state, &$hasJobOrder, &$snapshots): void {
            $current = [
                'status' => InquiryStatusCalculator::calculate($state['marketing'], $state['sales'], $state['process'], $hasJobOrder),
                'marketing' => $state['marketing'],
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

        foreach ($inquiryData['events'] as $event) {
            $at = $event['at'];
            $fail = fn (string $why) => throw new RuntimeException("{$reference} at {$at}: {$why}");

            if (strcmp($at, $state['last_at']) <= 0) {
                $fail('events have to run forward in time.');
            }

            $state['last_at'] = $at;
            $cancelled = $state['marketing'] === MarketingStatus::Lost || $state['sales'] === SalesStatus::Rejected;
            $approved = $state['sales'] === SalesStatus::Quoted;

            if ($cancelled) {
                $fail('nothing happens to a cancelled inquiry.');
            }

            if (isset($event['marketing'])) {
                if ($state['marketing'] !== MarketingStatus::Pending) {
                    $fail('marketing only moves from pending.');
                }

                $state['marketing'] = MarketingStatus::from($event['marketing']);
                $state['changed']['marketing'] = $at;

                // Winning the client starts sales, at negotiation.
                if ($state['marketing'] === MarketingStatus::Won) {
                    $state['sales'] = SalesStatus::Negotiation;
                    $state['changed']['sales'] = $at;
                }
            } elseif (isset($event['sales'])) {
                $to = SalesStatus::from($event['sales']);

                if ($state['sales'] === null || $approved) {
                    $fail('sales moves only once its stage has started, and not after approval.');
                }

                if ($to === SalesStatus::Quoted && $state['quote_revision'] === null) {
                    $fail('the client can only approve a quotation that was issued.');
                }

                $state['sales'] = $to;
                $state['changed']['sales'] = $at;

                // The client's approval starts accounting and logistics together.
                if ($to === SalesStatus::Quoted) {
                    $state['payment'] = PaymentStatus::Awaiting;
                    $state['process'] = ProcessStatus::Received;
                    $state['changed']['payment'] = $at;
                    $state['changed']['process'] = $at;
                }
            } elseif (isset($event['quote'])) {
                if ($state['sales'] === null || $approved) {
                    $fail('sales issues quotes once its stage has started and before approval.');
                }

                $state['quote_revision'] = ($state['quote_revision'] ?? 0) + 1;
                $state['quoted_total'] = $event['quote'];
            } elseif (isset($event['job_order'])) {
                if (! $approved || $hasJobOrder) {
                    $fail('the job order is uploaded once, after the client has approved.');
                }

                $hasJobOrder = true;
            } elseif (isset($event['payment'])) {
                if (! $approved) {
                    $fail('accounting starts once the client has approved.');
                }

                $paidCentavos += $this->centavos($event['payment']);
                $total = $this->centavos($state['quoted_total']);
                $to = PaymentStatus::from($event['payment_status']);
                $expected = $paidCentavos >= $total ? PaymentStatus::FullyPaid : PaymentStatus::Partial;

                if ($paidCentavos > $total || $to !== $expected) {
                    $fail("payments of {$paidCentavos} centavos against {$total} are not {$to->value}.");
                }

                if ($to !== $state['payment']) {
                    $state['payment'] = $to;
                    $state['changed']['payment'] = $at;
                }
            } elseif (isset($event['process'])) {
                $to = ProcessStatus::from($event['process']);
                $from = $state['process'] ?? ProcessStatus::Received;

                if (! $hasJobOrder) {
                    $fail('logistics starts from the job order.');
                }

                $isStep = in_array($to->value, self::PROCESS_STEPS, true);
                $fromStep = array_search($from->value, self::PROCESS_STEPS, true);
                $toStep = array_search($to->value, self::PROCESS_STEPS, true);
                // Installation is skipped when the job has none.
                $nextStep = $isStep && $fromStep !== false
                    && ($toStep === $fromStep + 1 || ($to === ProcessStatus::Completed && $from === ProcessStatus::Dispatched));

                if ($from === ProcessStatus::Completed || ($isStep && ! $nextStep && $fromStep !== false) || (! $isStep && $fromStep === false)) {
                    $fail("logistics does not move from {$from->value} to {$to->value}.");
                }

                $state['process'] = $to;
                $state['changed']['process'] = $at;
            } else {
                $fail('unknown event.');
            }

            $snapshot($at);
        }

        $shows = InquiryStatusCalculator::calculate($state['marketing'], $state['sales'], $state['process'], $hasJobOrder);

        if ($shows->value !== $inquiryData['shows']) {
            throw new RuntimeException("{$reference} shows {$shows->value}, not {$inquiryData['shows']}.");
        }

        return ['final' => $state, 'snapshots' => $snapshots];
    }

    /**
     * The inquiry at its final statuses, with the project it is: its stones,
     * services, applications and the client's attachments.
     *
     * @param  array<string, mixed>  $inquiryData
     * @param  array{marketing: MarketingStatus, sales: ?SalesStatus, payment: ?PaymentStatus, process: ?ProcessStatus, quote_revision: ?int, quoted_total: ?string, changed: array<string, ?string>, last_at: string}  $final
     */
    private function seedInquiry(InquiryFileUploads $fileUploads, array $inquiryData, User $client, array $final): Inquiry
    {
        $project = $inquiryData['project'];
        $createdAt = Carbon::parse($inquiryData['created_at']);

        $inquiry = Inquiry::query()->forceCreate([
            'reference' => $inquiryData['reference'],
            'client_id' => $client->id,
            'name' => $project['name'],
            'location' => $project['location'],
            'landmark' => $project['landmark'],
            'area_sqm' => $project['area_sqm'],
            'notes' => $project['notes'],
            'marketing_status' => $final['marketing'],
            'marketing_status_changed_at' => Carbon::parse($final['changed']['marketing']),
            'sales_status' => $final['sales'],
            'sales_status_changed_at' => $this->time($final['changed']['sales']),
            'payment_status' => $final['payment'],
            'payment_status_changed_at' => $this->time($final['changed']['payment']),
            'process_status' => $final['process'],
            'process_status_changed_at' => $this->time($final['changed']['process']),
            'quoted_total' => $final['quoted_total'],
            'quote_revision' => $final['quote_revision'],
            'created_at' => $createdAt,
            'updated_at' => Carbon::parse($final['last_at']),
        ]);

        foreach ($project['stones'] as $stoneSort => $stoneSelection) {
            $stone = Stone::query()
                ->whereHas('category', fn ($query) => $query->where('slug', $stoneSelection['stone_slug']))
                ->orderBy('sort_order')->orderBy('id')->firstOrFail();

            $inquiry->stones()->create([
                'stone_id' => $stone->id,
                'edge_profile_id' => EdgeProfile::where('name', $stoneSelection['edge'])->firstOrFail()->id,
                'finish_id' => Finish::where('name', $stoneSelection['finish'])->firstOrFail()->id,
                'quantity' => $stoneSelection['quantity'],
                'sort_order' => $stoneSort,
            ]);
        }

        foreach ($project['services'] as $serviceName) {
            $inquiry->services()->attach(Service::where('name', $serviceName)->firstOrFail()->id);
        }

        foreach ($project['categories'] as $categorySort => $categorySlug) {
            $inquiry->categories()->attach(ProjectCategory::where('slug', $categorySlug)->firstOrFail()->id, ['sort_order' => $categorySort]);
        }

        foreach ($project['attachments'] as $fileData) {
            $this->seedFile($fileUploads, $inquiry, $client, DocumentType::ClientAttachment, $fileData, $createdAt);
        }

        return $inquiry;
    }

    /**
     * Marketing's first entry, pending from nothing, by the client who sent it;
     * then every marketing change, sales starting at negotiation from nothing
     * when marketing is won, and every sales change before the client's
     * approval.
     *
     * @param  array<string, mixed>  $inquiryData
     */
    private function seedHistory(Inquiry $inquiry, User $client, array $inquiryData): void
    {
        $createdAt = Carbon::parse($inquiryData['created_at']);

        $this->history($inquiry, InquiryStatusTrack::Marketing, null, MarketingStatus::Pending->value, $client, $createdAt);

        $marketing = MarketingStatus::Pending->value;
        $sales = null;

        foreach ($inquiryData['events'] as $event) {
            if (isset($event['marketing'])) {
                $this->history($inquiry, InquiryStatusTrack::Marketing, $marketing, $event['marketing'], $this->user($event['by']), Carbon::parse($event['at']));
                $marketing = $event['marketing'];

                if ($marketing === MarketingStatus::Won->value) {
                    $this->history($inquiry, InquiryStatusTrack::Sales, null, SalesStatus::Negotiation->value, $this->user($event['by']), Carbon::parse($event['at']));
                    $sales = SalesStatus::Negotiation->value;
                }
            }

            if (isset($event['sales']) && $event['sales'] !== SalesStatus::Quoted->value) {
                $this->history($inquiry, InquiryStatusTrack::Sales, $sales, $event['sales'], $this->user($event['by']), Carbon::parse($event['at']));
                $sales = $event['sales'];
            }
        }
    }

    /**
     * Each quotation issued, as the file sales uploaded, carrying its revision.
     *
     * @param  list<array<string, mixed>>  $events
     */
    private function seedQuotations(InquiryFileUploads $fileUploads, Inquiry $inquiry, array $events): void
    {
        $revision = 0;

        foreach ($events as $event) {
            if (isset($event['quote'])) {
                $revision++;

                $this->seedFile($fileUploads, $inquiry, $this->user($event['by']), DocumentType::Quotation, [
                    'name' => "{$inquiry->reference}-quotation-r{$revision}.pdf",
                    'size_bytes' => 180224 + $revision * 4096,
                    'mime_type' => 'application/pdf',
                ], Carbon::parse($event['at']), $revision);
            }
        }
    }

    /**
     * The client's approval, in one step: sales becomes quoted, and accounting
     * and logistics start, at awaiting and received, from nothing.
     *
     * @param  list<array<string, mixed>>  $events
     */
    private function seedApproval(Inquiry $inquiry, array $events): void
    {
        foreach ($events as $event) {
            if (($event['sales'] ?? null) === SalesStatus::Quoted->value) {
                $from = InquiryStatusHistory::query()
                    ->where('inquiry_id', $inquiry->id)
                    ->where('track', InquiryStatusTrack::Sales)
                    ->orderByDesc('changed_at')->orderByDesc('id')
                    ->value('to_status');

                $by = $this->user($event['by']);
                $at = Carbon::parse($event['at']);

                $this->history($inquiry, InquiryStatusTrack::Sales, $from, SalesStatus::Quoted->value, $by, $at);
                $this->history($inquiry, InquiryStatusTrack::Payment, null, PaymentStatus::Awaiting->value, $by, $at);
                $this->history($inquiry, InquiryStatusTrack::Process, null, ProcessStatus::Received->value, $by, $at);
            }
        }
    }

    /** @param  list<array<string, mixed>>  $events */
    private function seedJobOrder(InquiryFileUploads $fileUploads, Inquiry $inquiry, array $events): void
    {
        foreach ($events as $event) {
            if (isset($event['job_order'])) {
                $this->seedFile($fileUploads, $inquiry, $this->user($event['by']), DocumentType::JobOrder, [
                    'name' => "{$event['job_order']}.pdf",
                    'size_bytes' => 241664,
                    'mime_type' => 'application/pdf',
                ], Carbon::parse($event['at']));
            }
        }
    }

    /**
     * Each payment accounting recorded: the transaction, its receipt, and the
     * payment status when it moved.
     *
     * @param  list<array<string, mixed>>  $events
     */
    private function seedPayments(InquiryFileUploads $fileUploads, Inquiry $inquiry, array $events): void
    {
        $status = PaymentStatus::Awaiting->value;
        $receipt = 0;

        foreach ($events as $event) {
            if (! isset($event['payment'])) {
                continue;
            }

            $at = Carbon::parse($event['at']);
            $accountant = $this->user($event['by']);

            PaymentTransaction::query()->forceCreate([
                'inquiry_id' => $inquiry->id,
                'kind' => 'payment',
                'amount' => $event['payment'],
                'reference' => $event['reference'],
                'recorded_by' => $accountant->id,
                'received_at' => $at,
                'created_at' => $at,
            ]);

            $receipt++;

            $this->seedFile($fileUploads, $inquiry, $accountant, DocumentType::Receipt, [
                'name' => "{$inquiry->reference}-receipt-{$receipt}-{$event['reference']}.pdf",
                'size_bytes' => 98304,
                'mime_type' => 'application/pdf',
            ], $at);

            if ($event['payment_status'] !== $status) {
                $this->history($inquiry, InquiryStatusTrack::Payment, $status, $event['payment_status'], $accountant, $at);
                $status = $event['payment_status'];
            }
        }
    }

    /** @param  list<array<string, mixed>>  $events */
    private function seedLogistics(Inquiry $inquiry, array $events): void
    {
        $status = ProcessStatus::Received->value;

        foreach ($events as $event) {
            if (isset($event['process'])) {
                $this->history($inquiry, InquiryStatusTrack::Process, $status, $event['process'], $this->user($event['by']), Carbon::parse($event['at']));
                $status = $event['process'];
            }
        }
    }

    /**
     * One file on the inquiry, by whoever the uploader rule allows.
     *
     * @param  array{name: string, size_bytes: int, mime_type: string}  $fileData
     */
    private function seedFile(InquiryFileUploads $fileUploads, Inquiry $inquiry, User $uploader, DocumentType $type, array $fileData, Carbon $at, ?int $revision = null): void
    {
        $fileUploads->authorize($uploader, $type, $inquiry);

        $inquiry->files()->forceCreate([
            'document_type' => $type,
            'revision' => $revision,
            'disk' => 'local',
            'path' => 'inquiries/'.$inquiry->reference.'/'.$fileData['name'],
            'original_name' => $fileData['name'],
            'mime_type' => $fileData['mime_type'],
            'size_bytes' => $fileData['size_bytes'],
            'sha256' => hash('sha256', $inquiry->reference.'/'.$fileData['name']),
            'uploaded_by' => $uploader->id,
            'created_at' => $at,
        ]);
    }

    private function history(Inquiry $inquiry, InquiryStatusTrack $track, ?string $from, string $to, User $by, Carbon $at): void
    {
        InquiryStatusHistory::query()->create([
            'inquiry_id' => $inquiry->id,
            'track' => $track,
            'from_status' => $from,
            'to_status' => $to,
            'changed_by' => $by->id,
            'changed_at' => $at,
        ]);
    }

    /**
     * Every snapshot into its inquiry's thread, oldest first across all the
     * inquiries, so a client with several reads them in the order they happened.
     *
     * @param  list<array{inquiry: Inquiry, at: string, status: InquiryStatus, marketing: MarketingStatus, sales: ?SalesStatus, payment: ?PaymentStatus, process: ?ProcessStatus}>  $cards
     */
    private function postInquiryCards(SupportConversationService $support, MessageRepositoryInterface $messages, array $cards): void
    {
        usort($cards, fn (array $first, array $second): int => [$first['at'], $first['inquiry']->id] <=> [$second['at'], $second['inquiry']->id]);

        foreach ($cards as $card) {
            $messages->createInquiryCardOnce(
                $support->cardThreadFor($card['inquiry'])->id,
                $card['inquiry']->id,
                $card['status'],
                $card['marketing'],
                $card['sales'],
                $card['payment'],
                $card['process'],
                Carbon::parse($card['at']),
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
