<?php

use App\Enums\ClientKind;
use App\Enums\ConversationMemberRole;
use App\Enums\ConversationType;
use App\Enums\DocumentType;
use App\Enums\InquiryStatus;
use App\Enums\InquiryStatusTrack;
use App\Enums\LeadStatus;
use App\Enums\MessageType;
use App\Enums\PaymentStatus;
use App\Enums\ProcessStatus;
use App\Enums\Role;
use App\Enums\SalesStatus;
use App\Models\Client;
use App\Models\Conversation;
use App\Models\ConversationMember;
use App\Models\Inquiry;
use App\Models\InquiryFile;
use App\Models\InquiryStatusHistory;
use App\Models\Lead;
use App\Models\LeadStatusHistory;
use App\Models\Message;
use App\Models\PaymentTransaction;
use App\Models\User;
use Carbon\CarbonInterface;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\InquirySeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/*
 * The demo inquiries InquirySeeder writes have to be data the flow could have
 * produced (docs/database/regray-erd.md, sections 4 and 5). The rules are
 * written out here, apart from the seeder, so seeded data that breaks one
 * fails.
 */

/** The display rule, written out again: the first match wins, and NULL is a stage not started. */
function expectedDisplayStatus(Inquiry $inquiry): InquiryStatus
{
    return match (true) {
        $inquiry->marketing_status === LeadStatus::Lost, $inquiry->sales_status === SalesStatus::Rejected, $inquiry->payment_status === PaymentStatus::Refunded => InquiryStatus::Cancelled,
        $inquiry->sales_status === SalesStatus::ForRefund => InquiryStatus::ForRefund,
        $inquiry->process_status === ProcessStatus::Completed => InquiryStatus::Closed,
        $inquiry->process_status !== null && $inquiry->files->contains('document_type', DocumentType::JobOrder) => InquiryStatus::from($inquiry->process_status->value),
        $inquiry->sales_status === SalesStatus::Won => InquiryStatus::Quoted,
        $inquiry->sales_status !== null => InquiryStatus::from($inquiry->sales_status->value),
        default => InquiryStatus::Pending,
    };
}

/** When the history says a track moved to `$status`, or null if it never did. */
function movedAt(Inquiry $inquiry, InquiryStatusTrack $track, string $status): ?CarbonInterface
{
    return $inquiry->statusHistory->first(fn (InquiryStatusHistory $row): bool => $row->track === $track && $row->to_status === $status)?->changed_at;
}

/**
 * When sales had to start: when marketing won the inquiry, or with the inquiry
 * for a direct (walk-in) client. Null while marketing has not won it.
 */
function salesStartedAt(Inquiry $inquiry): ?CarbonInterface
{
    if ($inquiry->clientRecord->kind === ClientKind::Direct) {
        return $inquiry->created_at;
    }

    return $inquiry->marketing_status === LeadStatus::Won ? $inquiry->marketing_status_changed_at : null;
}

function centavos(string $amount): int
{
    return (int) round((float) $amount * 100);
}

describe('the seeded inquiries', function (): void {
    beforeEach(function (): void {
        $this->seed(DatabaseSeeder::class);
        $this->inquiries = Inquiry::query()
            ->withDisplayStatus()
            ->with(['client', 'clientRecord', 'lead', 'files.uploadedBy.roles', 'statusHistory', 'paymentTransactions', 'schedule', 'conversation'])
            ->orderBy('reference')
            ->get();
    });

    it('show the status the display rule gives, in PHP and in SQL alike', function (): void {
        expect($this->inquiries)->not->toBeEmpty();

        foreach ($this->inquiries as $inquiry) {
            expect($inquiry->status)->toBe(expectedDisplayStatus($inquiry), $inquiry->reference)
                ->and($inquiry->display_status)->toBe($inquiry->status->value, $inquiry->reference);
        }
    });

    it('cover every state the demo has to show', function (): void {
        $shown = $this->inquiries->map(fn (Inquiry $inquiry): string => $inquiry->status->value);
        $has = fn (callable $test): bool => $this->inquiries->contains($test);

        // Every status but won, which sales won shows as quoted until the first payment starts logistics.
        expect($shown->unique()->sort()->values()->all())->toEqualCanonicalizing(array_values(array_diff(InquiryStatus::values(), ['won'])))
            // Waiting on its lead for marketing's decision.
            ->and($has(fn (Inquiry $inquiry): bool => $inquiry->lead !== null && $inquiry->marketing_status === null && $inquiry->sales_status === null && $inquiry->payment_status === null && $inquiry->process_status === null && $inquiry->status === InquiryStatus::Pending))->toBeTrue()
            ->and($has(fn (Inquiry $inquiry): bool => $inquiry->status === InquiryStatus::Negotiation && $inquiry->payment_status === null && $inquiry->process_status === null))->toBeTrue()
            ->and($has(fn (Inquiry $inquiry): bool => $inquiry->marketing_status === LeadStatus::Lost && $inquiry->sales_status === null && $inquiry->status === InquiryStatus::Cancelled))->toBeTrue()
            ->and($has(fn (Inquiry $inquiry): bool => $inquiry->status === InquiryStatus::Negotiation && $inquiry->marketing_status === LeadStatus::Won))->toBeTrue()
            // Sent once the client was won, so marketing won it as it arrived.
            ->and($has(fn (Inquiry $inquiry): bool => $inquiry->marketing_status === LeadStatus::Won && $inquiry->marketing_status_changed_at->equalTo($inquiry->created_at)))->toBeTrue()
            // Sent first, and won by marketing later.
            ->and($has(fn (Inquiry $inquiry): bool => $inquiry->marketing_status === LeadStatus::Won && $inquiry->marketing_status_changed_at->greaterThan($inquiry->created_at)))->toBeTrue()
            // Quoted more than once: a new revision each time.
            ->and($has(fn (Inquiry $inquiry): bool => $inquiry->quote_revision >= 2))->toBeTrue()
            ->and($has(fn (Inquiry $inquiry): bool => $inquiry->status === InquiryStatus::OnHold && $inquiry->sales_status === SalesStatus::OnHold))->toBeTrue()
            ->and($has(fn (Inquiry $inquiry): bool => $inquiry->status === InquiryStatus::OnHold && $inquiry->process_status === ProcessStatus::OnHold))->toBeTrue()
            // A quotation sent and waiting on the client, first or revised.
            ->and($has(fn (Inquiry $inquiry): bool => $inquiry->status === InquiryStatus::Quoted && $inquiry->payment_status === null && $inquiry->process_status === null))->toBeTrue()
            ->and($has(fn (Inquiry $inquiry): bool => $inquiry->status === InquiryStatus::Revision && $inquiry->quote_revision >= 2))->toBeTrue()
            // Won and billed, with logistics waiting for the first payment.
            ->and($has(fn (Inquiry $inquiry): bool => $inquiry->status === InquiryStatus::Quoted && $inquiry->sales_status === SalesStatus::Won && $inquiry->payment_status === PaymentStatus::Awaiting && $inquiry->process_status === null))->toBeTrue()
            // Received by logistics at its first payment, and not moved on yet.
            ->and($has(fn (Inquiry $inquiry): bool => $inquiry->status === InquiryStatus::OrderReceived && $inquiry->payment_status === PaymentStatus::Partial))->toBeTrue()
            // Logistics only ever starts on an inquiry with its job order and a payment in
            // (which may have been refunded since).
            ->and($this->inquiries->every(fn (Inquiry $inquiry): bool => $inquiry->process_status === null || ($inquiry->hasJobOrder() && $inquiry->paymentTransactions->where('kind', 'payment')->isNotEmpty())))->toBeTrue()
            ->and($has(fn (Inquiry $inquiry): bool => $inquiry->sales_status === SalesStatus::Rejected && $inquiry->status === InquiryStatus::Cancelled))->toBeTrue()
            ->and($has(fn (Inquiry $inquiry): bool => $inquiry->status === InquiryStatus::Closed && $inquiry->payment_status === PaymentStatus::FullyPaid))->toBeTrue();

        foreach (PaymentStatus::cases() as $payment) {
            expect($has(fn (Inquiry $inquiry): bool => $inquiry->payment_status === $payment))->toBeTrue($payment->value);
        }

        // A refund requested on an inquiry not refunded at all yet, and one
        // refunded in full, which rejected sales and so cancelled it.
        expect($has(fn (Inquiry $inquiry): bool => $inquiry->sales_status === SalesStatus::ForRefund && $inquiry->paymentTransactions->where('kind', 'refund')->isEmpty()))->toBeTrue()
            ->and($this->inquiries->every(fn (Inquiry $inquiry): bool => $inquiry->payment_status !== PaymentStatus::Refunded || ($inquiry->sales_status === SalesStatus::Rejected && $inquiry->status === InquiryStatus::Cancelled)))->toBeTrue();
    });

    it('carry payments and refunds that add up to the payment status and never pass the quoted total', function (): void {
        foreach ($this->inquiries as $inquiry) {
            $sum = fn (string $kind): int => $inquiry->paymentTransactions->where('kind', $kind)->sum(fn (PaymentTransaction $transaction): int => centavos($transaction->amount));
            $paid = $sum('payment');
            $refunded = $sum('refund');
            $total = $inquiry->quoted_total === null ? 0 : centavos($inquiry->quoted_total);

            expect($paid)->toBeLessThanOrEqual($total, $inquiry->reference)
                ->and($refunded)->toBeLessThanOrEqual($paid, $inquiry->reference)
                // Only an inquiry for refund gives money back (rejected, once it is all back).
                ->and($refunded === 0 || $inquiry->sales_status === SalesStatus::ForRefund || $inquiry->payment_status === PaymentStatus::Refunded)->toBeTrue($inquiry->reference);

            match ($inquiry->payment_status) {
                null, PaymentStatus::Awaiting => expect($paid)->toBe(0, $inquiry->reference),
                PaymentStatus::Partial => expect($paid)->toBeGreaterThan(0)->toBeLessThan($total),
                PaymentStatus::FullyPaid => expect($paid)->toBe($total, $inquiry->reference),
                PaymentStatus::PartiallyRefunded => expect($refunded)->toBeGreaterThan(0)->toBeLessThan($paid),
                PaymentStatus::Refunded => expect($refunded)->toBe($paid, $inquiry->reference),
            };
        }
    });

    it('set no role status before its stage has started', function (): void {
        foreach ($this->inquiries as $inquiry) {
            $label = $inquiry->reference;
            $quotedAt = movedAt($inquiry, InquiryStatusTrack::Sales, SalesStatus::Quoted->value);
            $billedAt = movedAt($inquiry, InquiryStatusTrack::Payment, PaymentStatus::Awaiting->value);

            // Every inquiry is filed under its client's record. An account's
            // inquiry is on its lead, and a direct client's has no lead.
            expect($inquiry->clientRecord)->not->toBeNull($label)
                ->and($inquiry->clientRecord->user_id)->toBe($inquiry->client_id, $label)
                ->and($inquiry->clientRecord->kind === ClientKind::Direct)->toBe($inquiry->client_id === null, $label)
                ->and($inquiry->lead?->client_id)->toBe($inquiry->client_id, $label)
                ->and($inquiry->sales_status === null)->toBe(salesStartedAt($inquiry) === null, $label)
                // Logistics exists from the job order (never before the quote),
                // payment from its billing statement (never before the quote).
                ->and($inquiry->process_status === null || $quotedAt !== null)->toBeTrue($label)
                ->and($inquiry->payment_status === null)->toBe($billedAt === null, $label)
                ->and($billedAt === null || ($quotedAt !== null && $billedAt->greaterThan($quotedAt)))->toBeTrue($label);

            foreach (InquiryStatusTrack::cases() as $track) {
                $isSet = $inquiry->{$track->column()} !== null;

                expect($inquiry->{$track->column().'_changed_at'} !== null)->toBe($isSet, "{$label} {$track->value}");
            }
        }
    });

    it('start each history track when its stage starts and end it on the current value, in order', function (): void {
        foreach ($this->inquiries as $inquiry) {
            $starts = [
                InquiryStatusTrack::Sales->value => [SalesStatus::Negotiation->value, salesStartedAt($inquiry)],
                InquiryStatusTrack::Payment->value => [PaymentStatus::Awaiting->value, movedAt($inquiry, InquiryStatusTrack::Payment, PaymentStatus::Awaiting->value)],
                // Logistics starts with the first payment, the job order being in.
                InquiryStatusTrack::Process->value => [ProcessStatus::OrderReceived->value, $inquiry->statusHistory
                    ->first(fn (InquiryStatusHistory $row): bool => $row->track === InquiryStatusTrack::Payment && in_array($row->to_status, [PaymentStatus::Partial->value, PaymentStatus::FullyPaid->value], true))
                    ?->changed_at],
            ];

            foreach (InquiryStatusTrack::cases() as $track) {
                $rows = $inquiry->statusHistory->filter(fn (InquiryStatusHistory $row): bool => $row->track === $track)->values();
                $current = $inquiry->{$track->column()};
                $label = "{$inquiry->reference} {$track->value}";

                if ($current === null) {
                    expect($rows)->toBeEmpty($label);

                    continue;
                }

                $times = $rows->map(fn (InquiryStatusHistory $row): int => $row->changed_at->getTimestamp())->all();
                $sorted = $times;
                sort($sorted);
                [$startStatus, $startedAt] = $starts[$track->value];

                expect($rows->first()->from_status)->toBeNull($label)
                    ->and($rows->first()->to_status)->toBe($startStatus, $label)
                    ->and($rows->first()->changed_at->equalTo($startedAt))->toBeTrue($label)
                    ->and($rows->last()->to_status)->toBe($current->value, $label)
                    ->and($rows->last()->changed_at->equalTo($inquiry->{$track->column().'_changed_at'}))->toBeTrue($label)
                    ->and($times)->toBe($sorted, $label);

                foreach ($rows->skip(1)->values() as $index => $row) {
                    expect($row->from_status)->toBe($rows[$index]->to_status, $label);
                }
            }
        }
    });

    it('keep each update\'s files as one group, uploaded under the rule, as the flow requires', function (): void {
        foreach ($this->inquiries as $inquiry) {
            $label = $inquiry->reference;
            $updates = $inquiry->statusHistory->keyBy('id');
            $files = fn (DocumentType $type) => $inquiry->files->filter(fn (InquiryFile $file): bool => $file->document_type === $type)->values();
            $groupOf = fn (DocumentType $type) => $files($type)->map(fn (InquiryFile $file): string => $updates[$file->status_history_id]->track->value.':'.$updates[$file->status_history_id]->to_status)->all();
            $quotes = $inquiry->statusHistory->filter(fn (InquiryStatusHistory $row): bool => $row->track === InquiryStatusTrack::Sales && in_array($row->to_status, [SalesStatus::Quoted->value, SalesStatus::Revision->value], true));
            $payments = $inquiry->statusHistory->filter(fn (InquiryStatusHistory $row): bool => $row->track === InquiryStatusTrack::Payment && in_array($row->to_status, [PaymentStatus::Partial->value, PaymentStatus::FullyPaid->value], true));
            $refunds = $inquiry->statusHistory->filter(fn (InquiryStatusHistory $row): bool => $row->track === InquiryStatusTrack::Payment && in_array($row->to_status, [PaymentStatus::PartiallyRefunded->value, PaymentStatus::Refunded->value], true));
            // Asked for a refund at some point: for refund now, or rejected by the full refund.
            $askedForRefund = $inquiry->sales_status === SalesStatus::ForRefund || $inquiry->payment_status === PaymentStatus::Refunded;

            // Each quote, first or revised, is a new revision with its quotation.
            expect($quotes->count())->toBe($inquiry->quote_revision ?? 0, $label)
                ->and($files(DocumentType::Quotation)->pluck('revision')->all())->toBe($inquiry->quote_revision === null ? [] : range(1, $inquiry->quote_revision), $label)
                ->and($groupOf(DocumentType::Quotation))->toBe($quotes->map(fn (InquiryStatusHistory $row): string => 'sales:'.$row->to_status)->values()->all(), $label)
                // The job order, once, with the win or on its own.
                ->and($files(DocumentType::JobOrder)->count())->toBe($inquiry->sales_status === SalesStatus::Won || $askedForRefund ? 1 : 0, $label)
                // The refund request with for refund; a settled file with each refund.
                ->and($groupOf(DocumentType::RefundRequest))->toBe($askedForRefund ? ['sales:for_refund'] : [], $label)
                ->and($groupOf(DocumentType::RefundSettlement))->toBe($refunds->map(fn (InquiryStatusHistory $row): string => 'payment:'.$row->to_status)->values()->all(), $label)
                ->and(array_diff($groupOf(DocumentType::JobOrder), ['sales:won', 'process:order_received']))->toBe([], $label)
                // Payment starts with one billing statement; each payment has a receipt and an invoice.
                ->and($groupOf(DocumentType::BillingStatement))->toBe($inquiry->payment_status === null ? [] : ['payment:awaiting'], $label)
                ->and($files(DocumentType::Receipt)->count())->toBe($payments->count(), $label)
                ->and($files(DocumentType::Invoice)->count())->toBe($payments->count(), $label)
                ->and($inquiry->paymentTransactions->count())->toBe($payments->count() + $refunds->count(), $label);

            foreach ($inquiry->files as $file) {
                $uploader = $file->uploadedBy;

                match ($file->document_type) {
                    DocumentType::ClientAttachment => expect($uploader->id)->toBe($inquiry->client_id)->and($file->status_history_id)->toBeNull(),
                    DocumentType::Quotation, DocumentType::JobOrder, DocumentType::RefundRequest => expect($uploader->hasRole(Role::Sales->value))->toBeTrue($label),
                    DocumentType::BillingStatement, DocumentType::Receipt, DocumentType::Invoice, DocumentType::RefundSettlement => expect($uploader->hasRole(Role::Accounting->value))->toBeTrue($label),
                };

                if ($file->document_type !== DocumentType::ClientAttachment) {
                    expect($file->created_at->equalTo($updates[$file->status_history_id]->changed_at))->toBeTrue("{$label} {$file->original_name}");
                }
            }
        }
    });

    it('cancel the visit of a cancelled inquiry and give every inquiry one', function (): void {
        foreach ($this->inquiries as $inquiry) {
            expect($inquiry->schedule)->not->toBeNull($inquiry->reference);

            if ($inquiry->status === InquiryStatus::Cancelled) {
                expect($inquiry->schedule->cancelled_at)->not->toBeNull($inquiry->reference);
            }
        }
    });

    it('post each inquiry card snapshot once, in order, ending on the inquiry as it is now', function (): void {
        $cards = Message::query()->where('type', MessageType::InquiryCard)->orderBy('id')->get();

        $snapshot = fn (Message $card): string => implode('|', [
            $card->conversation_id,
            $card->inquiry_id,
            $card->announced_status,
            $card->announced_sales_status,
            $card->announced_payment_status,
            $card->announced_process_status,
        ]);

        expect($cards)->not->toBeEmpty()
            ->and($cards->map($snapshot)->duplicates()->all())->toBe([])
            // Every inquiry but a direct client's, which has no thread to post to.
            ->and($cards->pluck('inquiry_id')->unique()->sort()->values()->all())->toBe($this->inquiries->whereNotNull('client_id')->pluck('id')->sort()->values()->all());

        foreach ($cards->groupBy('conversation_id') as $threadCards) {
            $times = $threadCards->map(fn (Message $card): int => $card->created_at->getTimestamp())->all();
            $sorted = $times;
            sort($sorted);

            expect($times)->toBe($sorted);
        }

        $threads = Conversation::query()->whereIn('id', $cards->pluck('conversation_id')->unique())->get()->keyBy('id');

        foreach ($this->inquiries->whereNotNull('client_id') as $inquiry) {
            $label = $inquiry->reference;
            $inquiryCards = $cards->where('inquiry_id', $inquiry->id)->values();
            $first = $inquiryCards->first();
            $last = $inquiryCards->last();
            // Sent on a lead already won, the first card already shows sales started.
            $startedWithIt = salesStartedAt($inquiry)?->equalTo($inquiry->created_at) ?? false;

            expect([$first->announced_status, $first->announced_sales_status, $first->announced_payment_status, $first->announced_process_status])
                ->toBe($startedWithIt ? ['negotiation', 'negotiation', null, null] : ['pending', null, null, null], $label)
                ->and($first->created_at->equalTo($inquiry->created_at))->toBeTrue($label)
                ->and([$last->announced_status, $last->announced_sales_status, $last->announced_payment_status, $last->announced_process_status])
                ->toBe([$inquiry->status->value, $inquiry->sales_status?->value, $inquiry->payment_status?->value, $inquiry->process_status?->value], $label);

            // In the client's Regray Team thread until the inquiry had a group chat, then in the group.
            foreach ($inquiryCards as $card) {
                $thread = $threads[$card->conversation_id];
                $inGroup = $inquiry->conversation !== null && $card->created_at->greaterThanOrEqualTo($inquiry->conversation->created_at);

                expect($inGroup ? $thread->id === $inquiry->conversation->id : $thread->support_client_id === $inquiry->client_id)->toBeTrue("{$label} card {$card->id}");
            }
        }
    });
});

describe('the seeded leads and group chats', function (): void {
    beforeEach(function (): void {
        $this->seed(DatabaseSeeder::class);
    });

    it('give each client one lead, held by whoever holds their Regray Team thread, logging each inquiry\'s decision', function (): void {
        $leads = Lead::query()->with(['history', 'inquiries'])->orderBy('id')->get();

        expect($leads->pluck('client_id')->duplicates()->all())->toBe([])
            // Between them, the demo leads show every decision, and waiting.
            ->and($leads->contains(fn (Lead $lead): bool => $lead->tally()['won'] > 0))->toBeTrue()
            ->and($leads->contains(fn (Lead $lead): bool => $lead->tally()['lost'] > 0))->toBeTrue()
            ->and($leads->contains(fn (Lead $lead): bool => $lead->tally()['idle'] > 0))->toBeTrue();

        foreach ($leads as $lead) {
            $thread = Conversation::query()->where('type', ConversationType::Support)->where('support_client_id', $lead->client_id)->sole();
            $history = $lead->history;
            $decided = $lead->inquiries->whereNotNull('marketing_status');

            expect($lead->marketingUser()->sole()->hasRole(Role::Marketing->value))->toBeTrue($lead->reference)
                ->and($thread->assigned_to)->toBe($lead->marketing_user_id, $lead->reference)
                ->and($history->first()->only(['from_status', 'to_status', 'inquiry_id']))->toBe(['from_status' => null, 'to_status' => LeadStatus::Idle, 'inquiry_id' => null], $lead->reference)
                ->and($history->count())->toBe(1 + $decided->count(), $lead->reference)
                ->and($lead->inquiries)->not->toBeEmpty();

            // One row per decided inquiry, at its decision and when it was made.
            foreach ($decided as $inquiry) {
                $row = $history->sole(fn (LeadStatusHistory $row): bool => $row->inquiry_id === $inquiry->id);

                expect($row->to_status)->toBe($inquiry->marketing_status, $inquiry->reference)
                    ->and($row->changed_at->equalTo($inquiry->marketing_status_changed_at))->toBeTrue($inquiry->reference);
            }
        }

        expect(LeadStatusHistory::query()->count())->toBe($leads->sum(fn (Lead $lead): int => $lead->history->count()));
    });

    it('bind each group chat to one inquiry, with one sales and one technical member, the client and its marketing admin, no owner, and at most one accounting and one logistics member, each staff member assigned to the inquiry', function (): void {
        $groups = Conversation::query()->where('type', ConversationType::Group)->whereNotNull('inquiry_id')->with(['members.user.roles', 'inquiry.lead', 'inquiry.handlers'])->get();
        $owners = User::role(Role::Owner->value)->pluck('id')->all();

        expect($groups)->not->toBeEmpty();

        foreach ($groups as $group) {
            $members = $group->members;
            $admin = $members->firstWhere('role', ConversationMemberRole::Admin);
            $staff = $members->reject(fn (ConversationMember $member): bool => in_array($member->user_id, [$group->inquiry->client_id, $admin->user_id], true));
            $staffRoles = $staff->map(fn (ConversationMember $member): string => $member->user->roles->sole()->name)->countBy()->all();

            expect($admin->user_id)->toBe($group->inquiry->lead->marketing_user_id, $group->name)
                ->and($members->pluck('user_id')->all())->toContain($group->inquiry->client_id)
                ->and(array_intersect($members->pluck('user_id')->all(), $owners))->toBe([], $group->name)
                ->and(array_diff(array_keys($staffRoles), [Role::Sales->value, Role::Technical->value, Role::Accounting->value, Role::Logistics->value]))->toBe([], $group->name)
                ->and($staffRoles[Role::Sales->value] ?? 0)->toBe(1, $group->name)
                ->and($staffRoles[Role::Technical->value] ?? 0)->toBe(1, $group->name)
                ->and($staffRoles[Role::Accounting->value] ?? 0)->toBeLessThanOrEqual(1)
                ->and($staffRoles[Role::Logistics->value] ?? 0)->toBeLessThanOrEqual(1)
                ->and($group->inquiry->handlers->pluck('user_id')->all())->toContain(...$staff->pluck('user_id')->all());
        }
    });

    it('record who opened each walk-in client\'s inquiry, and nobody for a client\'s own', function (): void {
        $inquiries = Inquiry::query()->get();

        expect($inquiries->whereNull('client_id'))->not->toBeEmpty()
            ->and($inquiries->whereNull('client_id')->whereNull('created_by'))->toBeEmpty()
            ->and($inquiries->whereNotNull('client_id')->whereNotNull('created_by'))->toBeEmpty();
    });
});

describe('the seeded clients', function (): void {
    beforeEach(function (): void {
        $this->seed(DatabaseSeeder::class);
    });

    it('give each account one platform record and each walk-in a direct record with its contacts, and have no walk_in kind', function (): void {
        $clients = Client::query()->with('contacts')->get();

        expect($clients->pluck('kind')->unique()->sortBy('value')->values()->all())->toEqualCanonicalizing([ClientKind::Platform, ClientKind::Direct]);

        foreach ($clients as $client) {
            expect($client->user_id !== null)->toBe($client->kind === ClientKind::Platform, $client->full_name);

            if ($client->kind === ClientKind::Direct) {
                expect($client->contacts)->not->toBeEmpty()
                    ->and($client->inquiries()->whereNotNull('lead_id')->exists())->toBeFalse();
            }
        }

        expect($clients->whereNotNull('user_id')->pluck('user_id')->duplicates()->all())->toBe([])
            ->and(DB::table('clients')->where('kind', 'walk_in')->exists())->toBeFalse();
    });
});

describe('in production', function (): void {
    beforeEach(function (): void {
        $this->seed(RolePermissionSeeder::class);
        $this->app->detectEnvironment(fn () => 'production');
    });

    it('refuses to run InquirySeeder and writes nothing', function (): void {
        expect(fn () => app()->call([app(InquirySeeder::class), 'run']))
            ->toThrow(RuntimeException::class, 'InquirySeeder cannot run in production.');

        expect(Inquiry::query()->count())->toBe(0);
    });

    it('seeds no inquiries and no inquiry cards from DatabaseSeeder', function (): void {
        Artisan::call('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);

        expect(Inquiry::query()->count())->toBe(0)
            ->and(Message::query()->where('type', MessageType::InquiryCard)->count())->toBe(0);
    });
});
