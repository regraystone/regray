<?php

use App\Enums\DocumentType;
use App\Enums\InquiryStatus;
use App\Enums\InquiryStatusGroup;
use App\Enums\MarketingStatus;
use App\Enums\ProcessStatus;
use App\Enums\SalesStatus;
use App\Models\Inquiry;
use App\Services\InquiryStatusCalculator;
use Illuminate\Support\Facades\DB;

it('takes the first rule that matches, NULL being a stage not started', function (MarketingStatus $marketing, ?SalesStatus $sales, ?ProcessStatus $process, bool $hasJobOrder, InquiryStatus $expected): void {
    expect(InquiryStatusCalculator::calculate($marketing, $sales, $process, $hasJobOrder))->toBe($expected);
})->with([
    'marketing pending, sales not started' => [MarketingStatus::Pending, null, null, false, InquiryStatus::Pending],
    'marketing won, sales negotiating' => [MarketingStatus::Won, SalesStatus::Negotiation, null, false, InquiryStatus::Negotiation],
    'marketing won, site visited' => [MarketingStatus::Won, SalesStatus::SiteVisited, null, false, InquiryStatus::SiteVisited],
    'marketing won, sales on hold' => [MarketingStatus::Won, SalesStatus::OnHold, null, false, InquiryStatus::OnHold],
    'marketing lost, sales not started' => [MarketingStatus::Lost, null, null, false, InquiryStatus::Cancelled],
    'marketing lost wins over a job order' => [MarketingStatus::Lost, SalesStatus::Quoted, ProcessStatus::Production, true, InquiryStatus::Cancelled],
    'sales rejected' => [MarketingStatus::Won, SalesStatus::Rejected, null, false, InquiryStatus::Cancelled],
    'sales rejected wins over logistics completed' => [MarketingStatus::Won, SalesStatus::Rejected, ProcessStatus::Completed, true, InquiryStatus::Cancelled],
    'sales quoted, process not started' => [MarketingStatus::Won, SalesStatus::Quoted, null, false, InquiryStatus::Quoted],
    'sales quoted, process received, no job order yet' => [MarketingStatus::Won, SalesStatus::Quoted, ProcessStatus::Received, false, InquiryStatus::Quoted],
    'job order in, process received' => [MarketingStatus::Won, SalesStatus::Quoted, ProcessStatus::Received, true, InquiryStatus::Received],
    'job order in, in production' => [MarketingStatus::Won, SalesStatus::Quoted, ProcessStatus::Production, true, InquiryStatus::Production],
    'job order in, logistics on hold' => [MarketingStatus::Won, SalesStatus::Quoted, ProcessStatus::OnHold, true, InquiryStatus::OnHold],
    'job order in, delayed' => [MarketingStatus::Won, SalesStatus::Quoted, ProcessStatus::Delayed, true, InquiryStatus::Delayed],
    'job order in, process not started' => [MarketingStatus::Won, SalesStatus::Quoted, null, true, InquiryStatus::Quoted],
    'logistics completed' => [MarketingStatus::Won, SalesStatus::Quoted, ProcessStatus::Completed, true, InquiryStatus::Closed],
    'logistics completed wins over everything but a cancel' => [MarketingStatus::Pending, null, ProcessStatus::Completed, false, InquiryStatus::Closed],
]);

it('shows every logistics value but completed as itself once the job order is in', function (ProcessStatus $process): void {
    expect(InquiryStatusCalculator::calculate(MarketingStatus::Won, SalesStatus::Quoted, $process, true)->value)->toBe($process->value);
})->with(array_filter(ProcessStatus::cases(), fn (ProcessStatus $process): bool => $process !== ProcessStatus::Completed));

it('files every display status under one Quote list tab', function (): void {
    $grouped = collect(InquiryStatus::cases())->groupBy(fn (InquiryStatus $status): string => $status->group()->value)->map->count();

    expect(InquiryStatus::Pending->group())->toBe(InquiryStatusGroup::Pending)
        ->and(InquiryStatus::Closed->group())->toBe(InquiryStatusGroup::Closed)
        ->and(InquiryStatus::Cancelled->group())->toBe(InquiryStatusGroup::Cancelled)
        ->and(InquiryStatus::Quoted->group())->toBe(InquiryStatusGroup::InProgress)
        ->and(InquiryStatus::Delayed->group())->toBe(InquiryStatusGroup::InProgress)
        ->and($grouped->all())->toBe(['pending' => 1, 'cancelled' => 1, 'closed' => 1, 'in_progress' => 13]);
});

it('agrees with the SQL scope on every combination of the role statuses, NULL included', function (): void {
    seedInquiryCatalog();
    $client = inquiryClient();
    $rows = [];
    $expected = [];
    $sequence = 0;

    foreach (MarketingStatus::cases() as $marketing) {
        foreach ([null, ...SalesStatus::cases()] as $sales) {
            foreach ([null, ...ProcessStatus::cases()] as $process) {
                foreach ([false, true] as $hasJobOrder) {
                    $reference = sprintf('INQ-X%04d', ++$sequence);
                    $expected[$reference] = InquiryStatusCalculator::calculate($marketing, $sales, $process, $hasJobOrder)->value;
                    $rows[] = [
                        'reference' => $reference,
                        'client_id' => $client->id,
                        'name' => $reference,
                        'location' => 'Makati',
                        'marketing_status' => $marketing->value,
                        'sales_status' => $sales?->value,
                        'process_status' => $process?->value,
                        'marketing_status_changed_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                        'job_order' => $hasJobOrder,
                    ];
                }
            }
        }
    }

    DB::table('inquiries')->insert(array_map(fn (array $row): array => array_diff_key($row, ['job_order' => true]), $rows));

    $ids = Inquiry::query()->pluck('id', 'reference');
    $files = [];

    foreach ($rows as $row) {
        if ($row['job_order']) {
            $files[] = ['inquiry_id' => $ids[$row['reference']], 'document_type' => DocumentType::JobOrder->value, 'disk' => 'local', 'path' => 'x.pdf', 'original_name' => 'x.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 1, 'created_at' => now()];
        }
    }

    DB::table('inquiry_files')->insert($files);

    $fromSql = Inquiry::query()->withDisplayStatus()->pluck('display_status', 'reference')->all();
    $fromModel = Inquiry::query()->withJobOrderFlag()->get()->mapWithKeys(fn (Inquiry $inquiry): array => [$inquiry->reference => $inquiry->status->value])->all();

    expect($fromSql)->toEqual($expected)
        ->and($fromModel)->toEqual($expected);

    foreach (InquiryStatusGroup::cases() as $group) {
        expect(Inquiry::query()->inStatusGroup($group)->pluck('reference')->sort()->values()->all())
            ->toBe(collect($expected)->filter(fn (string $status): bool => InquiryStatus::from($status)->group() === $group)->keys()->sort()->values()->all(), $group->value);
    }
});

it('does not count a removed job order file', function (): void {
    seedInquiryCatalog();
    $inquiry = makeInquiry(inquiryClient(), attributes: ['status' => InquiryStatus::Production]);

    $inquiry->files()->where('document_type', DocumentType::JobOrder)->sole()->delete();

    expect($inquiry->fresh()->status)->toBe(InquiryStatus::Quoted)
        ->and(Inquiry::query()->withDisplayStatus()->whereKey($inquiry->id)->value('display_status'))->toBe('quoted');
});
