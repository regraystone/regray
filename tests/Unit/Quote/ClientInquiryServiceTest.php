<?php

use App\Data\ClientInquiryCard;
use App\Data\InquiryListFilters;
use App\Enums\InquiryStatus;
use App\Services\ClientInquiryService;

beforeEach(function (): void {
    seedInquiryCatalog();
    $this->client = inquiryClient();
    $this->service = app(ClientInquiryService::class);
});

it('escapes the characters a LIKE pattern would read as wildcards', function (?string $search, ?string $escaped): void {
    expect($this->service->escapeSearch($search))->toBe($escaped);
})->with([
    'nothing' => [null, null],
    'only spaces' => ['   ', null],
    'plain' => ['kitchen', 'kitchen'],
    'trimmed' => ['  kitchen ', 'kitchen'],
    'percent' => ['100%', '100\\%'],
    'underscore' => ['a_b', 'a\\_b'],
    'backslash' => ['a\\b', 'a\\\\b'],
]);

it('hands back cards, not models', function (): void {
    makeInquiry($this->client, ['name' => 'Kitchen'], ['reference' => 'INQ-U1']);

    $result = $this->service->listFor($this->client, new InquiryListFilters);
    $card = $result['inquiries']->first();

    expect($card)->toBeInstanceOf(ClientInquiryCard::class)
        ->and($card->reference)->toBe('INQ-U1')
        ->and($card->title)->toBe('Kitchen')
        ->and($card->status)->toBe(InquiryStatus::Pending)
        // Nothing to pay before the client approves the quotation.
        ->and($card->paymentStatus)->toBeNull()
        ->and($card->actions['cancel']['visible'])->toBeTrue()
        ->and($result['hasMore'])->toBeFalse();
});

it('only offers cancel on an open inquiry', function (InquiryStatus $status, bool $expected): void {
    makeInquiry($this->client, attributes: ['status' => $status]);

    expect($this->service->listFor($this->client, new InquiryListFilters)['inquiries']->first()->actions['cancel']['visible'])->toBe($expected);
})->with([
    [InquiryStatus::Pending, true],
    [InquiryStatus::Negotiation, true],
    [InquiryStatus::Production, true],
    [InquiryStatus::Closed, false],
    [InquiryStatus::Cancelled, false],
]);

it('reads stones, services and statuses in a fixed number of queries however many cards there are', function (): void {
    foreach (range(1, 10) as $index) {
        makeInquiry($this->client, attributes: ['status' => $index % 2 === 0 ? InquiryStatus::Production : InquiryStatus::Pending]);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->service->listFor($this->client, new InquiryListFilters);
    $withTen = count(DB::getQueryLog());
    DB::disableQueryLog();

    // count + page (with each one's job order flag) + stones + services
    expect($withTen)->toBe(4);
});
