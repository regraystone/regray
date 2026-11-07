<?php

use App\Enums\InquiryStatus;
use App\Models\Stone;
use App\Models\StoneType;
use App\Services\BestSellerService;

beforeEach(function (): void {
    seedInquiryCatalog();
    $this->client = inquiryClient();
    // One stone from each type, so each stands for its type below.
    $this->stones = Stone::query()->orderBy('id')->get()->unique('stone_type_id')->values();
});

/** `$count` inquiries for the stone, each shown at `$status`. */
function inquiriesFor(Stone $stone, int $count, InquiryStatus $status = InquiryStatus::Closed): void
{
    foreach (range(1, $count) as $ignored) {
        makeInquiry(test()->client, ['stones' => [$stone->slug]], ['status' => $status]);
    }
}

/**
 * Whether each stone's type is a best seller, in the stones' order.
 *
 * @return list<bool>
 */
function bestSellerFlags(iterable $stones): array
{
    $service = app(BestSellerService::class);

    return collect($stones)->map(fn (Stone $stone): bool => $service->isBestSeller($stone->stone_type_id))->values()->all();
}

test('the three types in the most completed inquiries are best sellers', function (): void {
    $this->stones->take(3)->each(fn (Stone $stone) => inquiriesFor($stone, 2));
    inquiriesFor($this->stones[3], 1);

    expect(bestSellerFlags($this->stones->take(4)))->toBe([true, true, true, false]);
});

test('types tied with the third place are best sellers too', function (): void {
    $this->stones->take(2)->each(fn (Stone $stone) => inquiriesFor($stone, 3));
    inquiriesFor($this->stones[2], 2);
    inquiriesFor($this->stones[3], 2);
    inquiriesFor($this->stones[4], 1);

    expect(bestSellerFlags($this->stones->take(5)))->toBe([true, true, true, true, false]);
});

test('an inquiry that is not completed does not count', function (InquiryStatus $status): void {
    inquiriesFor($this->stones[0], 3, $status);
    inquiriesFor($this->stones[1], 1);

    expect(bestSellerFlags($this->stones->take(2)))->toBe([false, true]);
})->with([
    'pending' => InquiryStatus::Pending,
    'quoted' => InquiryStatus::Quoted,
    'won' => InquiryStatus::Won,
    'in installation' => InquiryStatus::Installation,
    'cancelled' => InquiryStatus::Cancelled,
    'for refund' => InquiryStatus::ForRefund,
]);

test('every stone in a best-selling type is a best seller, sold or not', function (): void {
    $sold = $this->stones[0];
    $sibling = Stone::query()->where('stone_type_id', $sold->stone_type_id)->whereKeyNot($sold->id)->firstOrFail();
    inquiriesFor($sold, 1);

    expect($sibling->is_best_seller)->toBeTrue()
        ->and(StoneType::query()->findOrFail($sold->stone_type_id)->is_best_seller)->toBeTrue()
        ->and($this->stones[1]->is_best_seller)->toBeFalse();
});

test('different stones in a type add up', function (): void {
    $this->stones->take(3)->each(fn (Stone $stone) => inquiriesFor($stone, 1));
    $first = $this->stones[3];
    $second = Stone::query()->where('stone_type_id', $first->stone_type_id)->whereKeyNot($first->id)->firstOrFail();
    inquiriesFor($first, 1);
    inquiriesFor($second, 1);
    inquiriesFor($this->stones[0], 1);
    inquiriesFor($this->stones[1], 1);

    expect(bestSellerFlags($this->stones->take(4)))->toBe([true, true, false, true]);
});

test('two stones of a type in one completed inquiry count once', function (): void {
    $this->stones->slice(1, 3)->each(fn (Stone $stone) => inquiriesFor($stone, 2));
    $first = $this->stones[0];
    $second = Stone::query()->where('stone_type_id', $first->stone_type_id)->whereKeyNot($first->id)->firstOrFail();
    makeInquiry($this->client, ['stones' => [$first->slug, $second->slug]], ['status' => InquiryStatus::Closed]);

    expect(bestSellerFlags([$first]))->toBe([false]);
});

test('an inactive stone does not count, leaving the place to the next type', function (): void {
    $this->stones->take(3)->each(fn (Stone $stone) => inquiriesFor($stone, 2));
    inquiriesFor($this->stones[3], 1);
    Stone::query()->where('stone_type_id', $this->stones[0]->stone_type_id)->update(['is_active' => false]);

    expect(bestSellerFlags($this->stones->take(4)))->toBe([false, true, true, true]);
});

test('an inactive type is not a best seller, leaving its place to the next', function (): void {
    $this->stones->take(3)->each(fn (Stone $stone) => inquiriesFor($stone, 2));
    inquiriesFor($this->stones[3], 1);
    StoneType::query()->whereKey($this->stones[0]->stone_type_id)->update(['is_active' => false]);

    expect(bestSellerFlags($this->stones->take(4)))->toBe([false, true, true, true]);
});
