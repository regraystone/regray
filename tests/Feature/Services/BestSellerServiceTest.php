<?php

use App\Enums\InquiryStatus;
use App\Models\Finish;
use App\Models\Stone;
use App\Services\BestSellerService;

beforeEach(function (): void {
    seedInquiryCatalog();
    $this->client = inquiryClient();
    $this->stones = Stone::query()->orderBy('id')->limit(12)->get();
});

/** `$count` inquiries for the stone, each shown at `$status`. */
function inquiriesFor(Stone $stone, int $count, InquiryStatus $status = InquiryStatus::Closed): void
{
    foreach (range(1, $count) as $ignored) {
        makeInquiry(test()->client, ['stones' => [$stone->slug]], ['status' => $status]);
    }
}

/** Whether each stone is a best seller, keyed by slug. */
function bestSellerFlags(iterable $stones): array
{
    $service = app(BestSellerService::class);

    return collect($stones)->mapWithKeys(fn (Stone $stone): array => [$stone->slug => $service->isBestSeller($stone->id)])->all();
}

test('the ten stones in the most completed inquiries are best sellers', function (): void {
    $this->stones->take(10)->each(fn (Stone $stone) => inquiriesFor($stone, 2));
    inquiriesFor($this->stones[10], 1);

    expect(bestSellerFlags($this->stones->take(11)))
        ->toBe([...array_fill_keys($this->stones->take(10)->pluck('slug')->all(), true), $this->stones[10]->slug => false]);
});

test('stones tied with the tenth place are best sellers too', function (): void {
    $this->stones->take(9)->each(fn (Stone $stone) => inquiriesFor($stone, 3));
    inquiriesFor($this->stones[9], 2);
    inquiriesFor($this->stones[10], 2);
    inquiriesFor($this->stones[11], 1);

    expect(bestSellerFlags($this->stones))
        ->toBe([...array_fill_keys($this->stones->take(11)->pluck('slug')->all(), true), $this->stones[11]->slug => false]);
});

test('an inquiry that is not completed does not count', function (InquiryStatus $status): void {
    inquiriesFor($this->stones[0], 3, $status);
    inquiriesFor($this->stones[1], 1);

    expect(bestSellerFlags($this->stones->take(2)))
        ->toBe([$this->stones[0]->slug => false, $this->stones[1]->slug => true]);
})->with([
    'pending' => InquiryStatus::Pending,
    'quoted' => InquiryStatus::Quoted,
    'won' => InquiryStatus::Won,
    'in installation' => InquiryStatus::Installation,
    'cancelled' => InquiryStatus::Cancelled,
    'for refund' => InquiryStatus::ForRefund,
    'refunded' => InquiryStatus::Refunded,
]);

test('a stone listed twice in one completed inquiry counts once', function (): void {
    $this->stones->take(10)->each(fn (Stone $stone) => inquiriesFor($stone, 2));
    $twice = $this->stones[10];
    $inquiry = makeInquiry($this->client, ['stones' => [$twice->slug]], ['status' => InquiryStatus::Closed]);
    $first = $inquiry->stones()->firstOrFail();
    $inquiry->stones()->create([
        'stone_id' => $twice->id,
        'edge_profile_id' => $first->edge_profile_id,
        'finish_id' => Finish::query()->whereKeyNot($first->finish_id)->value('id'),
        'quantity' => 1,
        'sort_order' => 1,
    ]);

    expect(bestSellerFlags([$twice]))->toBe([$twice->slug => false]);
});

test('an inactive stone is not a best seller and leaves its place to the next', function (): void {
    $this->stones->take(10)->each(fn (Stone $stone) => inquiriesFor($stone, 2));
    inquiriesFor($this->stones[10], 1);
    $this->stones[0]->update(['is_active' => false]);

    expect(bestSellerFlags([$this->stones[0], $this->stones[10]]))
        ->toBe([$this->stones[0]->slug => false, $this->stones[10]->slug => true]);
});
