<?php

use App\Enums\DocumentType;
use App\Enums\InquiryStatus;
use App\Enums\InquiryStatusTrack;
use App\Enums\MarketingStatus;
use App\Enums\MessageType;
use App\Enums\PaymentStatus;
use App\Enums\ProcessStatus;
use App\Enums\Role;
use App\Enums\SalesStatus;
use App\Models\Inquiry;
use App\Models\InquiryFile;
use App\Models\InquiryStatusHistory;
use App\Models\Message;
use App\Models\PaymentTransaction;
use Carbon\CarbonInterface;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\InquirySeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Artisan;

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
        $inquiry->marketing_status === MarketingStatus::Lost, $inquiry->sales_status === SalesStatus::Rejected => InquiryStatus::Cancelled,
        $inquiry->process_status === ProcessStatus::Completed => InquiryStatus::Closed,
        $inquiry->process_status !== null && $inquiry->files->contains('document_type', DocumentType::JobOrder) => InquiryStatus::from($inquiry->process_status->value),
        $inquiry->sales_status !== null => InquiryStatus::from($inquiry->sales_status->value),
        default => InquiryStatus::Pending,
    };
}

/** When the history says a track moved to `$status`, or null if it never did. */
function movedAt(Inquiry $inquiry, InquiryStatusTrack $track, string $status): ?CarbonInterface
{
    return $inquiry->statusHistory->first(fn (InquiryStatusHistory $row): bool => $row->track === $track && $row->to_status === $status)?->changed_at;
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
            ->with(['client', 'files.uploadedBy.roles', 'statusHistory', 'paymentTransactions', 'schedule'])
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

        expect($shown->unique()->sort()->values()->all())->toEqualCanonicalizing(InquiryStatus::values())
            ->and($has(fn (Inquiry $inquiry): bool => $inquiry->marketing_status === MarketingStatus::Pending && $inquiry->sales_status === null && $inquiry->payment_status === null && $inquiry->process_status === null && $inquiry->status === InquiryStatus::Pending))->toBeTrue()
            ->and($has(fn (Inquiry $inquiry): bool => $inquiry->status === InquiryStatus::Negotiation && $inquiry->payment_status === null && $inquiry->process_status === null))->toBeTrue()
            ->and($has(fn (Inquiry $inquiry): bool => $inquiry->marketing_status === MarketingStatus::Lost && $inquiry->sales_status === null && $inquiry->status === InquiryStatus::Cancelled))->toBeTrue()
            ->and($has(fn (Inquiry $inquiry): bool => $inquiry->status === InquiryStatus::Negotiation && $inquiry->marketing_status === MarketingStatus::Won))->toBeTrue()
            ->and($has(fn (Inquiry $inquiry): bool => $inquiry->status === InquiryStatus::Negotiation && $inquiry->quote_revision >= 2))->toBeTrue()
            ->and($has(fn (Inquiry $inquiry): bool => $inquiry->status === InquiryStatus::OnHold && $inquiry->sales_status === SalesStatus::OnHold))->toBeTrue()
            ->and($has(fn (Inquiry $inquiry): bool => $inquiry->status === InquiryStatus::OnHold && $inquiry->process_status === ProcessStatus::OnHold))->toBeTrue()
            ->and($has(fn (Inquiry $inquiry): bool => $inquiry->status === InquiryStatus::Quoted && $inquiry->payment_status === PaymentStatus::Awaiting && $inquiry->process_status === ProcessStatus::Received && ! $inquiry->hasJobOrder()))->toBeTrue()
            ->and($has(fn (Inquiry $inquiry): bool => $inquiry->sales_status === SalesStatus::Rejected && $inquiry->status === InquiryStatus::Cancelled))->toBeTrue()
            ->and($has(fn (Inquiry $inquiry): bool => $inquiry->marketing_status === MarketingStatus::Lost && $inquiry->status === InquiryStatus::Cancelled))->toBeTrue()
            ->and($has(fn (Inquiry $inquiry): bool => $inquiry->status === InquiryStatus::Closed && $inquiry->payment_status === PaymentStatus::FullyPaid))->toBeTrue();

        foreach (PaymentStatus::cases() as $payment) {
            expect($has(fn (Inquiry $inquiry): bool => $inquiry->payment_status === $payment))->toBeTrue($payment->value);
        }
    });

    it('carry payments that add up to the payment status and never pass the quoted total', function (): void {
        foreach ($this->inquiries as $inquiry) {
            $paid = $inquiry->paymentTransactions->where('kind', 'payment')->sum(fn (PaymentTransaction $transaction): int => centavos($transaction->amount));
            $total = $inquiry->quoted_total === null ? 0 : centavos($inquiry->quoted_total);

            expect($paid)->toBeLessThanOrEqual($total, $inquiry->reference);

            match ($inquiry->payment_status) {
                null, PaymentStatus::Awaiting => expect($paid)->toBe(0, $inquiry->reference),
                PaymentStatus::Partial => expect($paid)->toBeGreaterThan(0)->toBeLessThan($total),
                PaymentStatus::FullyPaid => expect($paid)->toBe($total, $inquiry->reference),
            };
        }
    });

    it('set no role status before its stage has started, and start payment and process together', function (): void {
        foreach ($this->inquiries as $inquiry) {
            $label = $inquiry->reference;
            $wonAt = movedAt($inquiry, InquiryStatusTrack::Marketing, MarketingStatus::Won->value);
            $approvedAt = movedAt($inquiry, InquiryStatusTrack::Sales, SalesStatus::Quoted->value);

            // Sales exists only once marketing has won the client.
            expect($inquiry->sales_status === null)->toBe($wonAt === null, $label)
                // Accounting and logistics exist only from the client's approval, and together.
                ->and($inquiry->payment_status === null)->toBe($inquiry->process_status === null, $label)
                ->and($inquiry->payment_status === null)->toBe($approvedAt === null, $label);

            foreach (InquiryStatusTrack::cases() as $track) {
                $isSet = $inquiry->{$track->column()} !== null;

                expect($inquiry->{$track->column().'_changed_at'} !== null)->toBe($isSet, "{$label} {$track->value}");
            }
        }
    });

    it('start each history track when its stage starts and end it on the current value, in order', function (): void {
        foreach ($this->inquiries as $inquiry) {
            $wonAt = movedAt($inquiry, InquiryStatusTrack::Marketing, MarketingStatus::Won->value);
            $approvedAt = movedAt($inquiry, InquiryStatusTrack::Sales, SalesStatus::Quoted->value);
            $starts = [
                InquiryStatusTrack::Marketing->value => [MarketingStatus::Pending->value, $inquiry->created_at],
                InquiryStatusTrack::Sales->value => [SalesStatus::Negotiation->value, $wonAt],
                InquiryStatusTrack::Payment->value => [PaymentStatus::Awaiting->value, $approvedAt],
                InquiryStatusTrack::Process->value => [ProcessStatus::Received->value, $approvedAt],
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

    it('keep the documents with the uploader the rule names, in the order the flow allows', function (): void {
        foreach ($this->inquiries as $inquiry) {
            $label = $inquiry->reference;
            $approvedAt = movedAt($inquiry, InquiryStatusTrack::Sales, SalesStatus::Quoted->value);
            $files = fn (DocumentType $type) => $inquiry->files->filter(fn (InquiryFile $file): bool => $file->document_type === $type)->values();

            // One quotation file per issue, numbered from 1, by sales.
            expect($files(DocumentType::Quotation)->pluck('revision')->all())->toBe($inquiry->quote_revision === null ? [] : range(1, $inquiry->quote_revision), $label);

            if ($inquiry->sales_status === SalesStatus::Quoted) {
                expect($inquiry->quote_revision)->toBeGreaterThanOrEqual(1, $label)
                    ->and($approvedAt)->not->toBeNull();
            }

            // The job order, by sales, after approval; logistics only moves after it.
            $jobOrder = $files(DocumentType::JobOrder);
            expect($jobOrder->count())->toBeLessThanOrEqual(1, $label);

            if ($jobOrder->isNotEmpty()) {
                expect($jobOrder->sole()->created_at->greaterThan($approvedAt))->toBeTrue($label);
            }

            foreach ($inquiry->statusHistory->filter(fn (InquiryStatusHistory $row): bool => $row->track === InquiryStatusTrack::Process && $row->from_status !== null) as $row) {
                expect($row->changed_at->greaterThanOrEqualTo($jobOrder->sole()->created_at))->toBeTrue($label);
            }

            // Accounting leaves awaiting only after approval, and each payment
            // comes with its receipt, by accounting.
            foreach ($inquiry->statusHistory->filter(fn (InquiryStatusHistory $row): bool => $row->track === InquiryStatusTrack::Payment && $row->from_status !== null) as $row) {
                expect($row->changed_at->greaterThan($approvedAt))->toBeTrue($label);
            }

            $leftAwaitingAt = $inquiry->statusHistory->first(fn (InquiryStatusHistory $row): bool => $row->track === InquiryStatusTrack::Payment && $row->from_status === PaymentStatus::Awaiting->value)?->changed_at;

            foreach ($inquiry->paymentTransactions as $transaction) {
                expect($transaction->received_at->greaterThanOrEqualTo($leftAwaitingAt))->toBeTrue($label);
            }

            expect($files(DocumentType::Receipt)->count())->toBe($inquiry->paymentTransactions->count(), $label);

            foreach ($inquiry->files as $file) {
                $uploader = $file->uploadedBy;

                match ($file->document_type) {
                    DocumentType::ClientAttachment => expect($uploader->id)->toBe($inquiry->client_id),
                    DocumentType::Quotation, DocumentType::JobOrder => expect($uploader->hasRole(Role::Sales->value))->toBeTrue($label),
                    DocumentType::Receipt => expect($uploader->hasRole(Role::Accounting->value))->toBeTrue($label),
                };
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
            $card->announced_marketing_status,
            $card->announced_sales_status,
            $card->announced_payment_status,
            $card->announced_process_status,
        ]);

        expect($cards)->not->toBeEmpty()
            ->and($cards->map($snapshot)->duplicates()->all())->toBe([])
            ->and($cards->pluck('inquiry_id')->unique()->sort()->values()->all())->toBe($this->inquiries->pluck('id')->sort()->values()->all());

        foreach ($cards->groupBy('conversation_id') as $threadCards) {
            $times = $threadCards->map(fn (Message $card): int => $card->created_at->getTimestamp())->all();
            $sorted = $times;
            sort($sorted);

            expect($times)->toBe($sorted);
        }

        foreach ($this->inquiries as $inquiry) {
            $inquiryCards = $cards->where('inquiry_id', $inquiry->id)->values();
            $first = $inquiryCards->first();
            $last = $inquiryCards->last();

            expect([$first->announced_status, $first->announced_marketing_status, $first->announced_sales_status, $first->announced_payment_status, $first->announced_process_status])
                ->toBe(['pending', 'pending', null, null, null], $inquiry->reference)
                ->and($first->created_at->equalTo($inquiry->created_at))->toBeTrue($inquiry->reference)
                ->and([$last->announced_status, $last->announced_marketing_status, $last->announced_sales_status, $last->announced_payment_status, $last->announced_process_status])
                ->toBe([$inquiry->status->value, $inquiry->marketing_status->value, $inquiry->sales_status?->value, $inquiry->payment_status?->value, $inquiry->process_status?->value], $inquiry->reference)
                ->and(Message::query()->with('conversation')->find($first->id)->conversation->support_client_id)->toBe($inquiry->client_id, $inquiry->reference);
        }
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
