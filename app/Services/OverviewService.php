<?php

namespace App\Services;

use App\Data\OverviewRange;
use App\Enums\DueStatus;
use App\Enums\InquiryStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProcessStatus;
use App\Enums\Role;
use App\Enums\SalesStatus;
use App\Models\Conversation;
use App\Models\Inquiry;
use App\Models\PaymentTransaction;
use App\Models\Schedule;
use App\Models\User;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\OverviewInsightsRepositoryInterface;
use App\Repositories\Contracts\OverviewRepositoryInterface;
use App\Repositories\Contracts\SalesReportRepositoryInterface;
use App\Repositories\Eloquent\OverviewRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection as BaseCollection;

/**
 * The Overview page: a different summary for each staff role, of the work
 * that role does, over the dates chosen (OverviewRange).
 *
 * - Sales: what they won and lost, closing %, their pipeline and their to-dos.
 * - Marketing: their leads, won and lost decisions, closing %, and what sales
 *   then sold.
 * - Accounting: what is owed, collected and refunded.
 * - Logistics: jobs by step and against their deadlines.
 * - Technical: the inquiries they are on and the group chats waiting for them.
 * - Owner: the studio's totals and each marketing and sales person's.
 * - Admin: the owner's view and the admin's own to-dos, and may view the
 *   overview of any one staff member.
 *
 * Someone with two roles sees both. Every figure is read through the same
 * visibility rules as the lists it links to.
 *
 * Each section is `{kind, title, figures, tasks, ...}`. A figure is
 * `{key, label, value, format, previous}`: `previous` is the same figure over
 * as many days just before, or null where the figure is as of now.
 */
class OverviewService
{
    /** How many rows a to-do or list shows. */
    public const ROWS = 5;

    /** How many days "coming up" looks ahead. */
    public const AHEAD_DAYS = 7;

    public function __construct(
        private readonly OverviewRepositoryInterface $overview,
        private readonly SalesReportRepositoryInterface $reports,
        private readonly ConversationRepositoryInterface $conversations,
        private readonly OverviewInsightsRepositoryInterface $insights,
    ) {}

    /**
     * @return array{range: array<string, mixed>, showsDates: bool, subject: array{id: int, name: string, roles: list<string>}, viewAs: array{options: list<array{id: int, name: string, role: string}>}|null, sections: list<array<string, mixed>>}
     */
    public function overview(User $viewer, User $subject, OverviewRange $range, CarbonImmutable $now): array
    {
        $roles = self::roles($subject);
        $sections = [];

        if (in_array(Role::Admin, $roles, true) || in_array(Role::Owner, $roles, true)) {
            $sections[] = $this->team($subject, $range, $now);
        }

        if (in_array(Role::Admin, $roles, true)) {
            $sections[] = $this->admin($range);
        }

        foreach ($roles as $role) {
            $section = match ($role) {
                Role::Sales => $this->sales($subject, $range, $now),
                Role::Marketing => $this->marketing($subject, $range, $now),
                Role::Accounting => $this->accounting($subject, $range, $now),
                Role::Logistics => $this->logistics($subject, $range, $now),
                Role::Technical => $this->technical($subject, $now),
                default => null,
            };

            if ($section !== null) {
                $sections[] = $section;
            }
        }

        if (! $subject->is($viewer)) {
            $sections = array_map(fn (array $section): array => self::aboutThem($section, $subject->name), $sections);
        }

        return [
            'range' => $range->toArray(),
            // Technical's overview is as of now, with nothing dated to filter.
            'showsDates' => collect($sections)->contains(fn (array $section): bool => $section['kind'] !== 'technical'),
            'subject' => [
                'id' => $subject->id,
                'name' => $subject->name,
                'roles' => array_values(array_filter(array_map(fn (Role $role): ?string => $role->label() ?? ($role === Role::Admin ? 'Admin' : null), $roles))),
            ],
            'viewAs' => $viewer->hasRole(Role::superAdmin()->value) ? ['options' => $this->viewAsOptions()] : null,
            'sections' => $sections,
        ];
    }

    /**
     * A section as the admin reads it of someone else: "Ana's sales", "assigned
     * to them", rather than "you".
     *
     * @param  array<string, mixed>  $section
     * @return array<string, mixed>
     */
    private static function aboutThem(array $section, string $name): array
    {
        $them = fn (?string $text): ?string => $text === null ? null : strtr($text, [
            'assigned to you' => 'assigned to them',
            'you are assigned' => 'they are assigned',
            'you own' => 'they own',
            'you hold' => 'they hold',
            'you opened' => 'they opened',
            'your decision' => 'their decision',
            'waiting for you' => 'waiting for them',
            'you have not read' => 'they have not read',
            'Of the leads you won' => 'Of the leads they won',
        ]);

        /** @var list<array<string, mixed>> $figures */
        $figures = $section['figures'];
        /** @var list<array<string, mixed>> $tasks */
        $tasks = $section['tasks'];

        return [
            ...$section,
            'title' => preg_replace('/^Your /', $name."'s ", (string) $section['title']),
            'note' => $them((string) $section['note']),
            'figures' => array_map(fn (array $figure): array => [...$figure, 'label' => $them((string) $figure['label']), 'note' => $them($figure['note'] ?? null)], $figures),
            'tasks' => array_map(fn (array $task): array => [...$task, 'label' => $them((string) $task['label'])], $tasks),
        ];
    }

    /** Whether the admin may open this person's overview: any staff member but the admin. */
    public function canViewAs(User $user): bool
    {
        $roles = self::roles($user);

        return $roles !== [] && ! in_array(Role::Admin, $roles, true);
    }

    // --- Sales -------------------------------------------------------------

    /** @return array<string, mixed> */
    private function sales(User $rep, OverviewRange $range, CarbonImmutable $now): array
    {
        $current = $this->overview->salesResults($rep, $range);
        $previous = $this->overview->salesResults($rep, $range->previous());
        $pipeline = $this->overview->salesPipeline($rep);

        [$toConfirm, $confirmRows] = $this->overview->visitsAwaitingConfirmation($rep, self::ROWS);
        [$toMark, $markRows] = $this->overview->visitsToMarkAttended($rep, $now, self::ROWS);
        [$stale, $staleRows] = $this->overview->staleQuotes($rep, $now, self::ROWS);
        [$noJobOrder, $jobOrderRows] = $this->overview->wonWithoutJobOrder($rep, self::ROWS);

        return [
            'kind' => 'sales',
            'title' => 'Your sales',
            'note' => 'The inquiries assigned to you and the walk-ins you own.',
            'figures' => [
                self::figure('won', 'Won', $current['won'], 'count', $previous['won']),
                self::figure('value', 'Value won', $current['value'], 'money', $previous['value'], self::unbilled($current['unbilled'])),
                self::figure('lost', 'Lost', $current['lost'], 'count', $previous['lost']),
                self::figure('closing', 'Closing %', self::rate($current['won'], $current['lost']), 'percent', self::rate($previous['won'], $previous['lost'])),
                self::figure('quotations', 'Quotations sent', $current['quotations'], 'count', $previous['quotations']),
                self::figure('averageDeal', 'Average deal', self::averageDeal($current), 'money', self::averageDeal($previous)),
                self::figure('daysToClose', 'Average days to close', $current['averageDaysToClose'], 'days', $previous['averageDaysToClose']),
                self::figure('visits', 'Visits attended', $this->overview->visitsAttended($rep, $range), 'count', $this->overview->visitsAttended($rep, $range->previous())),
            ],
            'pipeline' => array_map(fn (SalesStatus $status): array => [
                'status' => $status->value,
                'label' => $status->label(),
                'count' => $pipeline[$status->value]['count'] ?? 0,
                'total' => $pipeline[$status->value]['total'] ?? '0.00',
                'url' => route('inquiry.index', ['status' => $status->value]),
            ], OverviewRepository::UNDECIDED),
            'trend' => $this->salesTrend($rep, $range),
            'tasks' => self::tasks([
                self::task('confirm', 'Visits to confirm', $toConfirm, $confirmRows->map(self::visitRow(...)), route('schedule.index')),
                self::task('attended', 'Visits to mark as attended', $toMark, $markRows->map(self::visitRow(...)), route('schedule.index')),
                self::task('staleQuotes', 'Quotes unanswered for '.OverviewRepository::STALE_DAYS.'+ days', $stale, $staleRows->map(fn (Inquiry $inquiry): array => self::inquiryRow($inquiry, 'Quoted', $inquiry->sales_status_changed_at)), route('inquiry.index')),
                self::task('jobOrder', 'Won without a job order', $noJobOrder, $jobOrderRows->map(fn (Inquiry $inquiry): array => self::inquiryRow($inquiry, 'Won', $inquiry->sales_status_changed_at)), route('inquiry.index', ['status' => 'won'])),
            ]),
            'upcoming' => $this->overview->upcomingVisits($rep, $now, self::AHEAD_DAYS, self::ROWS)->map(self::visitRow(...))->values()->all(),
            'insights' => [
                $this->funnel('rep', $rep, $range, withMarketing: false),
                $this->speed($range, [
                    ['quote', 'Days to first quote', fn (OverviewRange $span): ?float => $this->insights->daysToFirstQuote('rep', $rep, $span)],
                ]),
                $this->quotes($rep, $range),
                $this->visits($rep, $range, $now),
                $this->byService($rep, $range),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function salesTrend(?User $rep, OverviewRange $range): array
    {
        $rows = $this->overview->salesTrend($rep, $range);

        return [
            'bucket' => $range->bucket(),
            'series' => [
                ['key' => 'won', 'label' => 'Won'],
                ['key' => 'lost', 'label' => 'Lost'],
            ],
            'points' => array_map(fn (string $start): array => [
                'start' => $start,
                'won' => $rows[$start][SalesStatus::Won->value]['count'] ?? 0,
                'lost' => $rows[$start][SalesStatus::Rejected->value]['count'] ?? 0,
                'value' => $rows[$start][SalesStatus::Won->value]['total'] ?? '0.00',
            ], $range->buckets()),
        ];
    }

    // --- Marketing ---------------------------------------------------------

    /** @return array<string, mixed> */
    private function marketing(User $marketer, OverviewRange $range, CarbonImmutable $now): array
    {
        $current = $this->overview->marketingResults($marketer, $range);
        $previous = $this->overview->marketingResults($marketer, $range->previous());
        $trend = $this->overview->marketingTrend($marketer, $range);

        [$idle, $idleRows] = $this->overview->idleInquiries($marketer, self::ROWS);
        [$bookings, $declined, $bookingRows] = $this->overview->bookingsToAssign($marketer, self::ROWS);

        return [
            'kind' => 'marketing',
            'title' => 'Your leads',
            'note' => 'The leads you hold and their inquiries.',
            'figures' => [
                self::figure('newLeads', 'New leads', $current['newLeads'], 'count', $previous['newLeads']),
                self::figure('received', 'Inquiries received', $current['received'], 'count', $previous['received']),
                self::figure('opened', 'Inquiries you opened', $current['opened'], 'count', $previous['opened']),
                self::figure('won', 'Won', $current['won'], 'count', $previous['won']),
                self::figure('lost', 'Lost', $current['lost'], 'count', $previous['lost']),
                self::figure('closing', 'Closing %', self::rate($current['won'], $current['lost']), 'percent', self::rate($previous['won'], $previous['lost'])),
                self::figure('sold', 'Sold by sales', $current['sold'], 'count', $previous['sold'], 'Of the leads you won, those sales went on to win: '.self::peso($current['soldValue']).'.'),
                self::figure('hoursToDecide', 'Average time to decide', $current['averageHoursToDecide'], 'hours', $previous['averageHoursToDecide']),
            ],
            'trend' => [
                'bucket' => $range->bucket(),
                'series' => [
                    ['key' => 'newLeads', 'label' => 'New leads'],
                    ['key' => 'won', 'label' => 'Won'],
                    ['key' => 'lost', 'label' => 'Lost'],
                ],
                'points' => array_map(fn (string $start): array => [
                    'start' => $start,
                    'newLeads' => $trend[$start]['newLeads'] ?? 0,
                    'won' => $trend[$start]['won'] ?? 0,
                    'lost' => $trend[$start]['lost'] ?? 0,
                ], $range->buckets()),
            ],
            'tasks' => self::tasks([
                self::task('idle', 'Inquiries waiting for your decision', $idle, $idleRows->map(fn (Inquiry $inquiry): array => self::inquiryRow($inquiry, 'Received', $inquiry->created_at)), route('leads.index', ['status' => 'idle'])),
                self::task('assign', $declined > 0 ? "Bookings to assign ({$declined} declined by sales)" : 'Bookings to assign', $bookings, $bookingRows->map(self::visitRow(...)), route('schedule.index')),
                self::countTask('support', 'Support chats nobody has claimed', $this->overview->unclaimedSupportChats(), route('inbox.index')),
            ]),
            'upcoming' => $this->overview->upcomingVisits($marketer, $now, self::AHEAD_DAYS, self::ROWS)->map(self::visitRow(...))->values()->all(),
            'insights' => [
                $this->funnel('marketer', $marketer, $range, withMarketing: true),
                $this->sources($marketer, $range),
                $this->visits($marketer, $range, $now),
                $this->popular($marketer, $range),
            ],
        ];
    }

    // --- Accounting --------------------------------------------------------

    /** @return array<string, mixed> */
    private function accounting(User $user, OverviewRange $range, CarbonImmutable $now): array
    {
        $cash = $this->reports->cash($user, $range->from, $range->to);
        $before = $this->reports->cash($user, $range->previous()->from, $range->previous()->to);
        $byStatus = $this->reports->byPaymentStatus($user);

        [$awaiting, $awaitingRows] = $this->overview->awaitingPayment($user, self::ROWS);
        [$refunds, $refundRows] = $this->overview->forRefund($user, self::ROWS);

        return [
            'kind' => 'accounting',
            'title' => 'Payments',
            'note' => 'The inquiries assigned to you.',
            'figures' => [
                self::figure('toCollect', 'To collect', $this->reports->balanceToCollect($user)['total'], 'money', null, dated: false),
                self::figure('collected', 'Collected', $cash['payments'], 'money', $before['payments']),
                self::figure('refunded', 'Refunded', $cash['refunds'], 'money', $before['refunds']),
                self::figure('partial', 'Partially paid', $byStatus[PaymentStatus::Partial->value]['count'] ?? 0, 'count', null, dated: false),
            ],
            'paymentStatuses' => array_map(fn (PaymentStatus $status): array => [
                'status' => $status->value,
                'label' => $status->label(),
                'count' => $byStatus[$status->value]['count'] ?? 0,
                'total' => $byStatus[$status->value]['total'] ?? '0.00',
                'url' => route('inquiry.index', ['status' => $status->value]),
            ], PaymentStatus::cases()),
            'tasks' => self::tasks([
                self::task('awaiting', 'Won and awaiting payment', $awaiting, $awaitingRows->map(fn (Inquiry $inquiry): array => self::inquiryRow($inquiry, 'Awaiting since', $inquiry->payment_status_changed_at)), route('inquiry.index', ['status' => PaymentStatus::Awaiting->value])),
                self::task('refunds', 'Refund requests from sales', $refunds, $refundRows->map(fn (Inquiry $inquiry): array => self::inquiryRow($inquiry, 'Asked', $inquiry->sales_status_changed_at)), route('inquiry.index')),
            ]),
            'transactions' => $this->reports->recentTransactions($user, self::ROWS)
                ->map(fn (PaymentTransaction $transaction): array => [
                    'title' => ($transaction->kind === 'refund' ? 'Refund · ' : 'Payment · ').$transaction->inquiry->reference,
                    'detail' => self::clientName($transaction->inquiry),
                    'amount' => (string) $transaction->amount,
                    'kind' => $transaction->kind,
                    'at' => $transaction->received_at->toIso8601String(),
                    'url' => route('inquiry.show', $transaction->inquiry->reference),
                ])
                ->values()
                ->all(),
            'insights' => [
                $this->ageing($user, $now),
                $this->speed($range, [
                    ['paid', 'Days from won to fully paid', fn (OverviewRange $span): ?float => $this->insights->daysWonToPaid($user, $span)],
                ]),
            ],
        ];
    }

    // --- Logistics ---------------------------------------------------------

    /** @return array<string, mixed> */
    private function logistics(User $user, OverviewRange $range, CarbonImmutable $now): array
    {
        $timezone = $range->timezone;
        $steps = $this->overview->processCounts($user);
        $due = $this->overview->dueCounts($user, $now, $timezone);

        [$newOrders, $newRows] = $this->overview->jobsAt($user, [ProcessStatus::OrderReceived], self::ROWS);
        [$overdue, $overdueRows] = $this->overview->overdueJobs($user, $now, $timezone, self::ROWS);
        [$stuck, $stuckRows] = $this->overview->jobsAt($user, [ProcessStatus::OnHold, ProcessStatus::Delayed], self::ROWS);

        return [
            'kind' => 'logistics',
            'title' => 'Jobs',
            'note' => 'The jobs assigned to you, from the job order to completion.',
            'figures' => [
                self::figure('active', 'Active jobs', array_sum($steps), 'count', null, dated: false),
                self::figure('dueSoon', 'Due within '.DueStatus::DUE_SOON_DAYS.' days', $due['dueSoon'], 'count', null, dated: false),
                self::figure('overdue', 'Overdue', $due['overdue'], 'count', null, dated: false),
                self::figure('completed', 'Completed', $this->overview->completedJobs($user, $range), 'count', $this->overview->completedJobs($user, $range->previous())),
            ],
            'steps' => array_map(fn (ProcessStatus $status): array => [
                'status' => $status->value,
                'label' => $status->label(),
                'count' => $steps[$status->value] ?? 0,
                'url' => route('inquiry.index', ['status' => $status->value]),
            ], array_values(array_filter(ProcessStatus::cases(), fn (ProcessStatus $status): bool => $status !== ProcessStatus::Completed))),
            'tasks' => self::tasks([
                self::task('newOrders', 'New job orders to start', $newOrders, $newRows->map(fn (Inquiry $inquiry): array => self::inquiryRow($inquiry, 'Received', $inquiry->process_status_changed_at)), route('inquiry.index', ['status' => ProcessStatus::OrderReceived->value])),
                self::task('overdue', 'Overdue jobs', $overdue, $overdueRows->map(fn (Inquiry $inquiry): array => self::inquiryRow($inquiry, 'Due', $inquiry->schedule?->arrival_at)), route('inquiry.index')),
                self::task('stuck', 'On hold or delayed', $stuck, $stuckRows->map(fn (Inquiry $inquiry): array => self::inquiryRow($inquiry, $inquiry->process_status?->label() ?? '', $inquiry->process_status_changed_at)), route('inquiry.index')),
            ]),
            'upcoming' => $this->overview->deadlinesAhead($user, $now, $timezone, self::ROWS)
                ->map(fn (Inquiry $inquiry): array => self::inquiryRow($inquiry, 'Due', $inquiry->schedule?->arrival_at))
                ->values()
                ->all(),
            'insights' => [
                $this->onTime($user, $range),
                $this->speed($range, [
                    ['completed', 'Days from won to completed', fn (OverviewRange $span): ?float => $this->insights->daysWonToCompleted($user, $span)],
                ]),
            ],
        ];
    }

    // --- Technical ---------------------------------------------------------

    /** @return array<string, mixed> */
    private function technical(User $user, CarbonImmutable $now): array
    {
        $timezone = ScheduleService::timezone();
        [$open, $dueSoon, $rows] = $this->overview->openAssigned($user, $now, $timezone, self::ROWS * 2);
        $chats = $this->conversations->unreadGroupsFor($user, self::ROWS);

        return [
            'kind' => 'technical',
            'title' => 'Your inquiries',
            'note' => 'The inquiries you are assigned to, and their group chats.',
            'figures' => [
                self::figure('open', 'Assigned inquiries', $open, 'count', null, 'Still open.', dated: false),
                self::figure('dueSoon', 'Due within '.DueStatus::DUE_SOON_DAYS.' days', $dueSoon, 'count', null, 'Or overdue.', dated: false),
                self::figure('unread', 'Chats to answer', $chats->count(), 'count', null, 'Group chats with messages you have not read.', dated: false),
            ],
            'tasks' => self::tasks([
                self::task('chats', 'Group chats waiting for you', $chats->count(), $chats->map(fn (Conversation $chat): array => [
                    'title' => $chat->name ?? ($chat->inquiry_id === null ? 'Group chat' : $chat->inquiry->reference),
                    'detail' => ($unread = (int) $chat->getAttribute('unread_count')).($unread === 1 ? ' unread message' : ' unread messages'),
                    'at' => self::iso($chat->getAttribute('last_activity_at')),
                    'url' => route('inbox.index', ['conversation' => $chat->id]),
                ]), route('inbox.index')),
            ], keepEmpty: true),
            'inquiries' => $rows->map(fn (Inquiry $inquiry): array => [
                ...self::inquiryRow($inquiry, 'Due', $inquiry->schedule?->arrival_at),
                'status' => InquiryStatus::tryFrom((string) $inquiry->getAttribute('display_status'))?->label(),
            ])->values()->all(),
            'upcoming' => $this->overview->upcomingVisits($user, $now, self::AHEAD_DAYS, self::ROWS)->map(self::visitRow(...))->values()->all(),
        ];
    }

    // --- Owner and admin -----------------------------------------------------

    /**
     * `$reader` sees every inquiry (the owner or the admin), so the totals are the studio's.
     *
     * @return array<string, mixed>
     */
    private function team(User $reader, OverviewRange $range, CarbonImmutable $now): array
    {
        $previousRange = $range->previous();
        $current = $this->overview->salesResults(null, $range);
        $previous = $this->overview->salesResults(null, $previousRange);
        $cash = $this->reports->cash($reader, $range->from, $range->to);
        $cashBefore = $this->reports->cash($reader, $previousRange->from, $previousRange->to);
        $statuses = $this->overview->pipelineByStatus();
        [$refunds] = $this->overview->forRefund(null, 0);

        return [
            'kind' => 'team',
            'title' => 'The studio',
            'note' => 'Every inquiry, and each person\'s share.',
            'figures' => [
                self::figure('won', 'Won', $current['won'], 'count', $previous['won']),
                self::figure('value', 'Value won', $current['value'], 'money', $previous['value'], self::unbilled($current['unbilled'])),
                self::figure('closing', 'Closing %', self::rate($current['won'], $current['lost']), 'percent', self::rate($previous['won'], $previous['lost'])),
                self::figure('collected', 'Collected', $cash['payments'], 'money', $cashBefore['payments']),
                self::figure('toCollect', 'To collect', $this->reports->balanceToCollect($reader)['total'], 'money', null, dated: false),
            ],
            'marketingTeam' => $this->overview->staff(Role::Marketing)->map(function (User $marketer) use ($range): array {
                $results = $this->overview->marketingResults($marketer, $range);

                return [
                    'id' => $marketer->id,
                    'name' => $marketer->name,
                    'newLeads' => $results['newLeads'],
                    'won' => $results['won'],
                    'lost' => $results['lost'],
                    'closing' => self::rate($results['won'], $results['lost']),
                    'sold' => $results['sold'],
                    'soldValue' => $results['soldValue'],
                ];
            })->values()->all(),
            'salesTeam' => $this->overview->staff(Role::Sales)->map(function (User $rep) use ($range): array {
                $results = $this->overview->salesResults($rep, $range);
                $pipeline = $this->overview->salesPipeline($rep);

                return [
                    'id' => $rep->id,
                    'name' => $rep->name,
                    'won' => $results['won'],
                    'value' => $results['value'],
                    'lost' => $results['lost'],
                    'closing' => self::rate($results['won'], $results['lost']),
                    'open' => array_sum(array_column($pipeline, 'count')),
                    'openValue' => number_format(array_sum(array_map(fn (array $row): float => (float) $row['total'], $pipeline)), 2, '.', ''),
                ];
            })->values()->all(),
            'pipeline' => array_map(fn (InquiryStatus $status): array => [
                'status' => $status->value,
                'label' => $status->label(),
                'count' => $statuses[$status->value] ?? 0,
                'url' => route('inquiry.index', ['status' => $status->value]),
            ], array_values(array_filter(InquiryStatus::cases(), fn (InquiryStatus $status): bool => $status !== InquiryStatus::Won))),
            'trend' => $this->salesTrend(null, $range),
            'tasks' => self::tasks([
                self::countTask('overdue', 'Overdue jobs', $this->overview->dueCounts(null, $now, $range->timezone)['overdue'], route('inquiry.index')),
                self::countTask('refunds', 'Refunds pending', $refunds, route('inquiry.index', ['status' => InquiryStatus::ForRefund->value])),
                self::countTask('stale', 'Open inquiries with no movement for '.OverviewRepository::STALE_DAYS.'+ days', $this->overview->staleInquiries($now), route('inquiry.index')),
            ]),
            'insights' => [
                $this->funnel('reader', null, $range, withMarketing: true),
                $this->onTime(null, $range),
                $this->speed($range, [
                    ['quote', 'Days to first quote', fn (OverviewRange $span): ?float => $this->insights->daysToFirstQuote('reader', null, $span)],
                    ['completed', 'Days from won to completed', fn (OverviewRange $span): ?float => $this->insights->daysWonToCompleted(null, $span)],
                    ['paid', 'Days from won to fully paid', fn (OverviewRange $span): ?float => $this->insights->daysWonToPaid(null, $span)],
                ]),
                $this->ageing($reader, $now),
                $this->byService(null, $range),
                $this->quotes(null, $range),
                $this->visits(null, $range, $now),
                $this->sources(null, $range),
                $this->popular(null, $range),
            ],
        ];
    }

    // --- Insights ------------------------------------------------------------

    /**
     * Of the inquiries received within the dates, how far each has got by
     * now. A sales rep's start at sales, so they have no "passed to sales".
     *
     * @param  'rep'|'marketer'|'reader'  $as
     * @return array<string, mixed>
     */
    private function funnel(string $as, ?User $user, OverviewRange $range, bool $withMarketing): array
    {
        $counts = $this->insights->funnel($as, $user, $range);
        $steps = [
            'received' => $as === 'rep' ? 'Inquiries' : 'Inquiries received',
            'passed' => 'Passed to sales',
            'quoted' => 'Quoted',
            'won' => 'Won',
            'paid' => 'Payment received',
            'completed' => 'Completed',
        ];

        if (! $withMarketing) {
            unset($steps['passed']);
        }

        return [
            'kind' => 'funnel',
            'title' => 'Funnel',
            'note' => 'Of the inquiries received within the dates, how far each has got by today.',
            'steps' => array_map(fn (string $key, string $label): array => ['key' => $key, 'label' => $label, 'count' => $counts[$key]], array_keys($steps), array_values($steps)),
        ];
    }

    /**
     * How long things take, each against as many days before.
     *
     * @param  list<array{0: string, 1: string, 2: \Closure(OverviewRange): (float|null)}>  $measures
     * @return array<string, mixed>
     */
    private function speed(OverviewRange $range, array $measures): array
    {
        return [
            'kind' => 'speed',
            'title' => 'Speed',
            'note' => 'Averages over what happened within the dates.',
            'figures' => array_map(fn (array $measure): array => self::figure($measure[0], $measure[1], $measure[2]($range), 'days', $measure[2]($range->previous())), $measures),
        ];
    }

    /** @return array<string, mixed> */
    private function onTime(?User $reader, OverviewRange $range): array
    {
        $current = $this->insights->onTime($reader, $range);
        $previous = $this->insights->onTime($reader, $range->previous());
        $rate = fn (array $row): ?int => $row['completed'] === 0 ? null : (int) round($row['onTime'] * 100 / $row['completed']);

        return [
            'kind' => 'onTime',
            'title' => 'Delivered on time',
            'note' => 'Jobs completed within the dates, on or before the date they were needed by.',
            'figures' => [
                self::figure('rate', 'On time', $rate($current), 'percent', $rate($previous)),
                self::figure('completed', 'Completed', $current['completed'], 'count', $previous['completed']),
                self::figure('late', 'Late', $current['completed'] - $current['onTime'], 'count', $previous['completed'] - $previous['onTime']),
                self::figure('daysLate', 'Average days late', $current['averageDaysLate'], 'days', $previous['averageDaysLate']),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function byService(?User $rep, OverviewRange $range): array
    {
        return [
            'kind' => 'byService',
            'title' => 'By service',
            'note' => 'Won and lost within the dates, by what the client asked for.',
            'rows' => array_map(fn (array $row): array => [...$row, 'closing' => self::rate($row['won'], $row['lost'])], $this->insights->byService($rep, $range)),
        ];
    }

    /** @return array<string, mixed> */
    private function sources(?User $marketer, OverviewRange $range): array
    {
        $channels = $this->insights->channels($marketer, $range);

        return [
            'kind' => 'sources',
            'title' => 'Where inquiries come from',
            'note' => 'Inquiries received within the dates.',
            'channels' => [
                ['key' => 'online', 'label' => 'Sent by the client online', 'count' => $channels['online']],
                ['key' => 'staff', 'label' => 'Opened by staff for a client', 'count' => $channels['staff']],
                ['key' => 'walkIn', 'label' => 'Walk-in', 'count' => $channels['walkIn']],
            ],
            'places' => $this->insights->places($marketer, $range, 8),
        ];
    }

    /** @return array<string, mixed> */
    private function quotes(?User $rep, OverviewRange $range): array
    {
        $current = $this->insights->quotes($rep, $range);
        $previous = $this->insights->quotes($rep, $range->previous());

        return [
            'kind' => 'quotes',
            'title' => 'Quotes on won deals',
            'note' => 'Deals won within the dates. The contract is compared with the last quote sent.',
            'figures' => [
                self::figure('averageQuotes', 'Quotes per win', $current['averageQuotes'], 'decimal', $previous['averageQuotes']),
                self::figure('revised', 'Won after a revision', $current['revised'], 'count', $previous['revised'], $current['won'] > 0 ? "Of {$current['won']} won." : null),
                self::figure('contractVsQuote', 'Contract vs quote', $current['contractVsQuote'], 'signedPercent', $previous['contractVsQuote'], 'Average difference.'),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function visits(?User $reader, OverviewRange $range, CarbonImmutable $now): array
    {
        $current = $this->insights->visitOutcomes($reader, $range, $now);
        $previous = $this->insights->visitOutcomes($reader, $range->previous(), $now);
        $rate = fn (array $row): ?int => ($held = $row['attended'] + $row['expired'] + $row['cancelled']) === 0 ? null : (int) round($row['attended'] * 100 / $held);

        return [
            'kind' => 'visits',
            'title' => 'Visits',
            'note' => 'Visits due within the dates, up to today.',
            'figures' => [
                self::figure('attended', 'Attended', $current['attended'], 'count', $previous['attended']),
                self::figure('rate', 'Attended rate', $rate($current), 'percent', $rate($previous), 'Of those attended, expired or cancelled.'),
                self::figure('missed', 'Expired or cancelled', $current['expired'] + $current['cancelled'], 'count', $previous['expired'] + $previous['cancelled']),
                self::figure('ledToWin', 'Led to a win', $current['ledToWin'], 'count', $previous['ledToWin'], 'Attended visits whose client went on to win an inquiry.'),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function ageing(User $reader, CarbonImmutable $now): array
    {
        $labels = ['0-30' => '0–30 days', '31-60' => '31–60 days', '61-90' => '61–90 days', '90+' => 'Over 90 days'];

        return [
            'kind' => 'ageing',
            'title' => 'Owed, by age',
            'note' => 'What is still to collect, by how long ago the deal was won. As of today.',
            'buckets' => array_map(fn (array $bucket): array => [...$bucket, 'label' => $labels[$bucket['bucket']]], $this->insights->ageing($reader, $now)),
        ];
    }

    /** @return array<string, mixed> */
    private function popular(?User $marketer, OverviewRange $range): array
    {
        return [
            'kind' => 'popular',
            'title' => 'Most asked for',
            'note' => 'Stones and brands in the inquiries received within the dates, and how many of those inquiries were won.',
            'stones' => $this->insights->topStones($marketer, $range, 8),
            'brands' => $this->insights->topBrands($marketer, $range, 8),
        ];
    }

    /** @return array<string, mixed> */
    private function admin(OverviewRange $range): array
    {
        [$bookings] = $this->overview->bookingsToAssign(null, 0);

        return [
            'kind' => 'admin',
            'title' => 'Admin',
            'note' => 'Across the studio.',
            'figures' => [
                self::figure('newClients', 'New client accounts', $this->overview->newClients($range), 'count', $this->overview->newClients($range->previous())),
            ],
            'tasks' => self::tasks([
                self::countTask('support', 'Support chats nobody has claimed', $this->overview->unclaimedSupportChats(), route('inbox.index')),
                self::countTask('assign', 'Bookings waiting for a sales member', $bookings, route('schedule.index')),
            ]),
        ];
    }

    /** @return list<array{id: int, name: string, role: string}> */
    private function viewAsOptions(): array
    {
        $options = [];

        foreach ([Role::Owner, Role::Marketing, Role::Sales, Role::Accounting, Role::Logistics, Role::Technical] as $role) {
            foreach ($this->overview->staff($role) as $user) {
                $options[] = ['id' => $user->id, 'name' => $user->name, 'role' => (string) $role->label()];
            }
        }

        return $options;
    }

    // --- Shapes --------------------------------------------------------------

    /**
     * The roles this person has, in the order their sections are shown.
     *
     * @return list<Role>
     */
    private static function roles(User $user): array
    {
        $names = $user->getRoleNames()->all();

        return array_values(array_filter(
            [Role::Admin, Role::Owner, Role::Marketing, Role::Sales, Role::Accounting, Role::Logistics, Role::Technical],
            fn (Role $role): bool => in_array($role->value, $names, true),
        ));
    }

    /** won ÷ (won + lost), as a whole percent; null when nothing was decided. */
    private static function rate(int $won, int $lost): ?int
    {
        return $won + $lost === 0 ? null : (int) round($won * 100 / ($won + $lost));
    }

    /**
     * One number card. `dated` false is a figure as of now, which the dates
     * leave alone (the page tags it Now).
     *
     * @return array{key: string, label: string, value: int|float|string|null, format: string, previous: int|float|string|null, note: string|null, dated: bool}
     */
    private static function figure(string $key, string $label, int|float|string|null $value, string $format, int|float|string|null $previous, ?string $note = null, bool $dated = true): array
    {
        return ['key' => $key, 'label' => $label, 'value' => $value, 'format' => $format, 'previous' => $previous, 'note' => $note, 'dated' => $dated];
    }

    /** "+ ₱50,000 quoted, not billed yet", or null when every win is billed. */
    private static function unbilled(string $amount): ?string
    {
        return (float) $amount > 0 ? '+ ₱'.number_format((float) $amount).' quoted, not billed yet' : null;
    }

    /**
     * The average deal: billed and not yet billed, over every win.
     *
     * @param  array{won: int, value: string, unbilled: string}  $results
     */
    private static function averageDeal(array $results): ?string
    {
        return $results['won'] > 0 ? number_format(((float) $results['value'] + (float) $results['unbilled']) / $results['won'], 2, '.', '') : null;
    }

    /**
     * @template TRow of array<string, mixed>
     *
     * @param  BaseCollection<int, TRow>  $rows
     * @return array{key: string, label: string, count: int, items: list<TRow>, url: string}
     */
    private static function task(string $key, string $label, int $count, BaseCollection $rows, string $url): array
    {
        return ['key' => $key, 'label' => $label, 'count' => $count, 'items' => array_values($rows->all()), 'url' => $url];
    }

    /**
     * A to-do shown as a count, with no rows.
     *
     * @return array{key: string, label: string, count: int, items: list<array<string, mixed>>, url: string}
     */
    private static function countTask(string $key, string $label, int $count, string $url): array
    {
        return ['key' => $key, 'label' => $label, 'count' => $count, 'items' => [], 'url' => $url];
    }

    /**
     * The to-dos with something in them; all of them when `keepEmpty`, so a
     * short list still says there is nothing waiting.
     *
     * @param  list<array{key: string, label: string, count: int, items: list<array<string, mixed>>, url: string}>  $tasks
     * @return list<array{key: string, label: string, count: int, items: list<array<string, mixed>>, url: string}>
     */
    private static function tasks(array $tasks, bool $keepEmpty = false): array
    {
        return $keepEmpty ? $tasks : array_values(array_filter($tasks, fn (array $task): bool => $task['count'] > 0));
    }

    /** @return array{title: string, detail: string, at: string|null, atLabel: string, url: string} */
    private static function inquiryRow(Inquiry $inquiry, string $atLabel, mixed $at): array
    {
        return [
            'title' => $inquiry->reference.' · '.self::clientName($inquiry),
            'detail' => $inquiry->serviceLabel() ?? $inquiry->name,
            'at' => self::iso($at),
            'atLabel' => $atLabel,
            'url' => route('inquiry.show', $inquiry->reference),
        ];
    }

    /** @return array{title: string, detail: string, at: string|null, atLabel: string, url: string} */
    private static function visitRow(Schedule $visit): array
    {
        return [
            'title' => $visit->clientRecord->full_name ?? $visit->client->name ?? $visit->title,
            'detail' => $visit->title,
            'at' => self::iso($visit->arrival_at),
            'atLabel' => 'Visit',
            'url' => route('schedule.index'),
        ];
    }

    private static function clientName(Inquiry $inquiry): string
    {
        return $inquiry->clientRecord->full_name ?? $inquiry->client->name ?? '—';
    }

    private static function peso(string $amount): string
    {
        return '₱'.number_format((float) $amount, 2);
    }

    private static function iso(mixed $value): ?string
    {
        return $value === null ? null : CarbonImmutable::parse($value)->toIso8601String();
    }
}
