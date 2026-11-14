<?php

use App\Enums\DocumentType;
use App\Enums\InquiryStatus;
use App\Enums\InquiryStatusGroup;
use App\Enums\LeadStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProcessStatus;
use App\Enums\SalesStatus;
use App\Models\Inquiry;
use App\Models\Lead;
use App\Services\InquiryStatusCalculator;
use Illuminate\Support\Facades\DB;

it('takes the first rule that matches, NULL being a stage not started', function (?LeadStatus $marketing, ?SalesStatus $sales, ?ProcessStatus $process, bool $hasJobOrder, InquiryStatus $expected, ?PaymentStatus $payment = null): void {
    expect(InquiryStatusCalculator::calculate($marketing, $sales, $process, $hasJobOrder, $payment))->toBe($expected);
})->with([
    'no lead yet, sales not started' => [null, null, null, false, InquiryStatus::Pending],
    'waiting for marketing, sales not started' => [LeadStatus::Idle, null, null, false, InquiryStatus::Pending],
    'marketing won, sales negotiating' => [LeadStatus::Won, SalesStatus::Negotiation, null, false, InquiryStatus::Negotiation],
    'marketing won, sales not started yet' => [LeadStatus::Won, null, null, false, InquiryStatus::Pending],
    'marketing won, site visited' => [LeadStatus::Won, SalesStatus::SiteVisited, null, false, InquiryStatus::SiteVisited],
    'marketing won, sales on hold' => [LeadStatus::Won, SalesStatus::OnHold, null, false, InquiryStatus::OnHold],
    'marketing lost, sales not started' => [LeadStatus::Lost, null, null, false, InquiryStatus::Cancelled],
    'marketing lost wins over a job order' => [LeadStatus::Lost, SalesStatus::Quoted, ProcessStatus::Production, true, InquiryStatus::Cancelled],
    'sales rejected' => [LeadStatus::Won, SalesStatus::Rejected, null, false, InquiryStatus::Cancelled],
    'sales rejected with no lead' => [null, SalesStatus::Rejected, null, false, InquiryStatus::Cancelled],
    'sales rejected wins over logistics completed' => [LeadStatus::Won, SalesStatus::Rejected, ProcessStatus::Completed, true, InquiryStatus::Cancelled],
    'sales quoted, process not started' => [LeadStatus::Won, SalesStatus::Quoted, null, false, InquiryStatus::Quoted],
    'sales quoted, process received, no job order yet' => [LeadStatus::Won, SalesStatus::Quoted, ProcessStatus::OrderReceived, false, InquiryStatus::Quoted],
    'sales won shows as quoted' => [LeadStatus::Won, SalesStatus::Won, null, false, InquiryStatus::Quoted],
    'sales won with the job order, logistics not started' => [LeadStatus::Won, SalesStatus::Won, null, true, InquiryStatus::Quoted],
    'sales won, process received, no job order yet' => [LeadStatus::Won, SalesStatus::Won, ProcessStatus::OrderReceived, false, InquiryStatus::Quoted],
    'sales won, job order in, in production' => [LeadStatus::Won, SalesStatus::Won, ProcessStatus::Production, true, InquiryStatus::Production],
    'job order in, process received' => [LeadStatus::Won, SalesStatus::Quoted, ProcessStatus::OrderReceived, true, InquiryStatus::OrderReceived],
    'job order in, in production' => [LeadStatus::Won, SalesStatus::Quoted, ProcessStatus::Production, true, InquiryStatus::Production],
    'job order in, logistics on hold' => [LeadStatus::Won, SalesStatus::Quoted, ProcessStatus::OnHold, true, InquiryStatus::OnHold],
    'job order in, delayed' => [LeadStatus::Won, SalesStatus::Quoted, ProcessStatus::Delayed, true, InquiryStatus::Delayed],
    'job order in, process not started' => [LeadStatus::Won, SalesStatus::Quoted, null, true, InquiryStatus::Quoted],
    'logistics completed' => [LeadStatus::Won, SalesStatus::Quoted, ProcessStatus::Completed, true, InquiryStatus::Closed],
    'logistics completed wins over everything but a cancel' => [LeadStatus::Idle, null, ProcessStatus::Completed, false, InquiryStatus::Closed],
    'for refund wins over logistics' => [LeadStatus::Won, SalesStatus::ForRefund, ProcessStatus::Production, true, InquiryStatus::ForRefund, PaymentStatus::FullyPaid],
    'for refund while partly refunded' => [LeadStatus::Won, SalesStatus::ForRefund, null, true, InquiryStatus::ForRefund, PaymentStatus::PartiallyRefunded],
    'cancelled once all of it went back and sales was rejected' => [LeadStatus::Won, SalesStatus::Rejected, ProcessStatus::OrderReceived, true, InquiryStatus::Cancelled, PaymentStatus::Refunded],
    'cancelled once all of it went back, sales still for refund' => [LeadStatus::Won, SalesStatus::ForRefund, ProcessStatus::OrderReceived, true, InquiryStatus::Cancelled, PaymentStatus::Refunded],
    'a full refund wins over logistics completed' => [LeadStatus::Won, SalesStatus::ForRefund, ProcessStatus::Completed, true, InquiryStatus::Cancelled, PaymentStatus::Refunded],
]);

it('shows every logistics value but completed as itself once the job order is in', function (ProcessStatus $process): void {
    expect(InquiryStatusCalculator::calculate(LeadStatus::Won, SalesStatus::Quoted, $process, true)->value)->toBe($process->value);
})->with(array_filter(ProcessStatus::cases(), fn (ProcessStatus $process): bool => $process !== ProcessStatus::Completed));

it('files every display status under one Quote list tab', function (): void {
    $grouped = collect(InquiryStatus::cases())->groupBy(fn (InquiryStatus $status): string => $status->group()->value)->map->count();

    expect(InquiryStatus::Pending->group())->toBe(InquiryStatusGroup::Pending)
        ->and(InquiryStatus::Closed->group())->toBe(InquiryStatusGroup::Closed)
        ->and(InquiryStatus::Cancelled->group())->toBe(InquiryStatusGroup::Cancelled)
        ->and(InquiryStatus::Quoted->group())->toBe(InquiryStatusGroup::InProgress)
        ->and(InquiryStatus::Won->group())->toBe(InquiryStatusGroup::InProgress)
        ->and(InquiryStatus::Delayed->group())->toBe(InquiryStatusGroup::InProgress)
        ->and(InquiryStatus::ForRefund->group())->toBe(InquiryStatusGroup::InProgress)
        ->and($grouped->all())->toBe(['pending' => 1, 'cancelled' => 1, 'closed' => 1, 'in_progress' => 16]);
});

it('agrees with the SQL scope on every combination of marketing\'s decision and the role statuses, NULL included', function (): void {
    seedInquiryCatalog();
    $client = inquiryClient();
    $lead = makeLead($client);
    $rows = [];
    $expected = [];
    $sequence = 0;

    // Marketing's decision is stored as won or lost, NULL while it waits.
    // Of payment, only a refund changes what is shown.
    foreach ([null, LeadStatus::Won, LeadStatus::Lost] as $marketing) {
        foreach ([null, ...SalesStatus::cases()] as $sales) {
            foreach ([null, ...ProcessStatus::cases()] as $process) {
                foreach ([false, true] as $hasJobOrder) {
                    foreach ([null, PaymentStatus::PartiallyRefunded, PaymentStatus::Refunded] as $payment) {
                        $reference = sprintf('INQ-X%05d', ++$sequence);
                        $expected[$reference] = InquiryStatusCalculator::calculate($marketing, $sales, $process, $hasJobOrder, $payment)->value;
                        $rows[] = [
                            'reference' => $reference,
                            'client_id' => $client->id,
                            'name' => $reference,
                            'location' => 'Makati',
                            'lead_id' => $lead->id,
                            'marketing_status' => $marketing?->value,
                            'sales_status' => $sales?->value,
                            'process_status' => $process?->value,
                            'payment_status' => $payment?->value,
                            'created_at' => now(),
                            'updated_at' => now(),
                            'job_order' => $hasJobOrder,
                        ];
                    }
                }
            }
        }
    }

    DB::table('inquiries')->insert(array_map(fn (array $row): array => array_diff_key($row, ['job_order' => true]), $rows));

    $ids = Inquiry::query()->pluck('id', 'reference');
    $files = [];

    foreach ($rows as $row) {
        if ($row['job_order']) {
            $files[] = ['inquiry_id' => $ids[$row['reference']], 'document_type' => DocumentType::JobOrder->value, 'disk' => 'local', 'path' => 'x.pdf', 'stored_name' => 'x.pdf', 'original_name' => 'x.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 1, 'created_at' => now()];
        }
    }

    DB::table('inquiry_files')->insert($files);

    $fromSql = Inquiry::query()->withDisplayStatus()->pluck('display_status', 'reference')->all();
    $fromModel = Inquiry::query()->withJobOrderFlag()->get()->mapWithKeys(fn (Inquiry $inquiry): array => [$inquiry->reference => $inquiry->status->value])->all();

    expect(count($expected))->toBe(3 * 9 * 12 * 2 * 3)
        ->and($fromSql)->toEqual($expected)
        ->and($fromModel)->toEqual($expected);

    foreach (InquiryStatusGroup::cases() as $group) {
        expect(Inquiry::query()->inStatusGroup($group)->pluck('reference')->sort()->values()->all())
            ->toBe(collect($expected)->filter(fn (string $status): bool => InquiryStatus::from($status)->group() === $group)->keys()->sort()->values()->all(), $group->value);
    }
});

it('reads marketing\'s decision from the inquiry itself, not from its lead', function (): void {
    seedInquiryCatalog();
    $client = inquiryClient();
    $lead = makeLead($client);
    $lost = makeInquiry($client, attributes: ['lead' => $lead, 'marketing_status' => LeadStatus::Lost]);
    $waiting = makeInquiry($client, attributes: ['lead' => $lead]);

    expect($lost->fresh()->status)->toBe(InquiryStatus::Cancelled)
        ->and(Inquiry::query()->withDisplayStatus()->find($lost->id)->display_status)->toBe(InquiryStatus::Cancelled->value)
        ->and($waiting->fresh()->status)->toBe(InquiryStatus::Pending)
        ->and(Lead::query()->count())->toBe(1);
});

it('does not count a removed job order file', function (): void {
    seedInquiryCatalog();
    $inquiry = makeInquiry(inquiryClient(), attributes: ['status' => InquiryStatus::Production]);

    $inquiry->files()->where('document_type', DocumentType::JobOrder)->sole()->delete();

    expect($inquiry->fresh()->status)->toBe(InquiryStatus::Quoted)
        ->and(Inquiry::query()->withDisplayStatus()->whereKey($inquiry->id)->value('display_status'))->toBe('quoted');
});
