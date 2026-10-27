<?php

use App\Enums\InquiryProjectStatus;
use App\Enums\InquiryStatus;
use App\Enums\JobOrderStatus;
use App\Enums\MessageType;
use App\Enums\PaymentStatus;
use App\Enums\QuotationStatus;
use App\Models\Inquiry;
use App\Models\InquiryProject;
use App\Models\JobOrder;
use App\Models\Message;
use App\Models\Payment;
use App\Models\Quotation;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\InquirySeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;

/*
 * The demo inquiries InquirySeeder writes have to be data the project rules
 * could have produced. The rules are written out here, apart from the seeder,
 * so a seeded status that does not follow from its quotations, job orders and
 * payment fails.
 */

/**
 * A project's status from what hangs off it: cancelled by any one of a
 * rejected quotation, a refunded payment or a cancelled job order; closed when
 * the live quotation is closed, the payment fully paid and every job order
 * completed; ongoing once anything has moved past a first pending quotation.
 */
function derivedProjectStatus(InquiryProject $project): InquiryProjectStatus
{
    $quotations = $project->quotations;
    $jobOrders = $project->jobOrders;
    $payment = $project->payments->first();

    if ($quotations->contains('status', QuotationStatus::Rejected)
        || $payment?->status === PaymentStatus::Refunded
        || $jobOrders->contains('status', JobOrderStatus::Cancelled)) {
        return InquiryProjectStatus::Cancelled;
    }

    if ($quotations->last()?->status === QuotationStatus::Closed
        && $payment?->status === PaymentStatus::FullyPaid
        && $jobOrders->isNotEmpty()
        && $jobOrders->every(fn (JobOrder $jobOrder): bool => $jobOrder->status === JobOrderStatus::Completed)) {
        return InquiryProjectStatus::Closed;
    }

    $workStarted = $quotations->contains(fn (Quotation $quotation): bool => $quotation->status !== QuotationStatus::Pending)
        || $jobOrders->isNotEmpty()
        || $payment !== null;

    return $workStarted ? InquiryProjectStatus::Ongoing : InquiryProjectStatus::Pending;
}

/**
 * An inquiry's status from its projects': lost when every one is cancelled,
 * closed when none is open and one is closed, pending while every one is, and
 * otherwise ongoing.
 *
 * @param  Collection<int, InquiryProjectStatus>  $statuses
 */
function derivedInquiryStatus(Collection $statuses): InquiryStatus
{
    $open = $statuses->contains(fn (InquiryProjectStatus $status): bool => in_array($status, [InquiryProjectStatus::Pending, InquiryProjectStatus::Ongoing], true));

    return match (true) {
        $statuses->every(fn (InquiryProjectStatus $status): bool => $status === InquiryProjectStatus::Cancelled) => InquiryStatus::Lost,
        ! $open && $statuses->contains(InquiryProjectStatus::Closed) => InquiryStatus::Closed,
        $statuses->every(fn (InquiryProjectStatus $status): bool => $status === InquiryProjectStatus::Pending) => InquiryStatus::Pending,
        default => InquiryStatus::Ongoing,
    };
}

describe('the seeded inquiries', function (): void {
    beforeEach(function (): void {
        $this->seed(DatabaseSeeder::class);
    });

    it('store the status each project and inquiry derives to', function (): void {
        $projects = InquiryProject::query()->with(['quotations', 'jobOrders', 'payments'])->get();
        $inquiries = Inquiry::query()->with('projects')->get();

        expect($projects->mapWithKeys(fn (InquiryProject $project): array => [$project->name => $project->status])->all())
            ->toBe($projects->mapWithKeys(fn (InquiryProject $project): array => [$project->name => derivedProjectStatus($project)])->all())
            ->and($inquiries->mapWithKeys(fn (Inquiry $inquiry): array => [$inquiry->reference => $inquiry->status])->all())
            ->toBe($inquiries->mapWithKeys(fn (Inquiry $inquiry): array => [
                $inquiry->reference => derivedInquiryStatus($inquiry->projects->pluck('status')),
            ])->all());
    });

    it('owe each payment the quotation total and add up what was paid from its transactions', function (): void {
        $payments = Payment::query()->with(['quotation', 'transactions'])->get();

        expect($payments)->not->toBeEmpty();

        foreach ($payments as $payment) {
            $paid = $payment->transactions->where('kind', 'payment')->sum(fn ($transaction): int => (int) round((float) $transaction->amount * 100));

            expect($payment->amount_due)->toBe($payment->quotation->total_amount)
                ->and((int) round((float) $payment->amount_paid * 100))->toBe($paid);
        }
    });

    it('end each inquiry history, in order, on the inquiry status', function (): void {
        foreach (Inquiry::query()->with('statusHistory')->get() as $inquiry) {
            $changedAt = $inquiry->statusHistory->pluck('changed_at')->map->getTimestamp()->all();
            $sorted = $changedAt;
            sort($sorted);

            expect($inquiry->statusHistory->last()->to_status)->toBe($inquiry->status)
                ->and($inquiry->statusHistory->last()->changed_at->equalTo($inquiry->status_changed_at))->toBeTrue()
                ->and($changedAt)->toBe($sorted);
        }
    });

    it('put job orders and payments only against a closeable or closed quotation', function (): void {
        $live = [QuotationStatus::Closeable, QuotationStatus::Closed];

        expect(JobOrder::query()->with('quotation')->get()->reject(fn (JobOrder $jobOrder): bool => in_array($jobOrder->quotation->status, $live, true)))->toBeEmpty()
            ->and(Payment::query()->with('quotation')->get()->reject(fn (Payment $payment): bool => in_array($payment->quotation->status, $live, true)))->toBeEmpty();
    });

    it('happened in order: issued before answered, work after acceptance, payments after the payment opened', function (): void {
        foreach (Quotation::query()->whereNotNull('responded_at')->get() as $quotation) {
            expect($quotation->issued_at->lessThan($quotation->responded_at))->toBeTrue();
        }

        foreach (JobOrder::query()->with('quotation')->get() as $jobOrder) {
            expect($jobOrder->created_at->greaterThanOrEqualTo($jobOrder->quotation->responded_at))->toBeTrue()
                ->and($jobOrder->status_changed_at->greaterThanOrEqualTo($jobOrder->created_at))->toBeTrue();
        }

        foreach (Payment::query()->with(['quotation', 'transactions'])->get() as $payment) {
            expect($payment->created_at->greaterThanOrEqualTo($payment->quotation->responded_at))->toBeTrue();

            foreach ($payment->transactions as $transaction) {
                expect($transaction->received_at->greaterThan($payment->created_at))->toBeTrue();
            }
        }
    });

    it('post each project card snapshot once, ending on the project as it is now', function (): void {
        $cards = Message::query()->where('type', MessageType::ProjectCard)->orderBy('id')->get();
        $projects = InquiryProject::query()->with(['quotations', 'payments'])->get()->keyBy('id');

        $snapshots = $cards->map(fn (Message $card): string => implode('|', [
            $card->conversation_id,
            $card->inquiry_project_id,
            $card->announced_status,
            $card->announced_quotation_status,
            $card->announced_payment_status,
        ]));

        expect($cards)->not->toBeEmpty()
            ->and($snapshots->duplicates()->all())->toBe([])
            ->and($cards->pluck('inquiry_project_id')->unique()->sort()->values()->all())->toBe($projects->keys()->sort()->values()->all());

        foreach ($cards->groupBy('inquiry_project_id') as $projectId => $projectCards) {
            $project = $projects[$projectId];
            $last = $projectCards->last();

            expect([$last->announced_status, $last->announced_quotation_status, $last->announced_payment_status])->toBe([
                $project->status->value,
                $project->quotations->last()->status->value,
                $project->payments->first()->status->value ?? PaymentStatus::Awaiting->value,
            ]);
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

    it('seeds no inquiries and no project cards from DatabaseSeeder', function (): void {
        Artisan::call('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);

        expect(Inquiry::query()->count())->toBe(0)
            ->and(Message::query()->where('type', MessageType::ProjectCard)->count())->toBe(0);
    });
});
