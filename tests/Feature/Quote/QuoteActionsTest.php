<?php

use App\Enums\InquiryStatus;
use App\Enums\MarketingStatus;
use App\Enums\SalesStatus;
use App\Models\InquiryStatusHistory;

beforeEach(function (): void {
    seedInquiryCatalog();
    $this->client = inquiryClient();
});

it('sends the card exactly the buttons in the table', function (string $status, array $table): void {
    makeInquiry($this->client, attributes: ['status' => InquiryStatus::from($status)]);

    $card = $this->actingAs($this->client)->get(route('quote.index'))->viewData('page')['props']['inquiries']['data'][0];

    expect($card)->not->toHaveKey('canCancel')
        ->and(array_keys($card['actions']))->toBe(['view', 'update', 'cancel', 'invoice']);

    foreach (['view', 'update', 'cancel', 'invoice'] as $action) {
        expect($card['actions'][$action])->toBe(expectedAction($table[$action]), "{$status} {$action}");
    }
})->with('inquiry button table');

it('enforces the table on the cancel endpoint', function (string $status, array $table): void {
    $inquiry = makeInquiry($this->client, attributes: ['reference' => 'INQ-A9', 'status' => InquiryStatus::from($status)]);

    $response = $this->actingAs($this->client)->patchJson(route('quote.cancel', 'INQ-A9'));

    if ($table['cancel'] === [true]) {
        // Shown and clickable: the request goes through.
        $response->assertRedirect();
        // Rejected by sales once its stage has started, lost by marketing before.
        expect($inquiry->refresh()->status)->toBe(InquiryStatus::Cancelled)
            ->and($inquiry->sales_status === SalesStatus::Rejected || $inquiry->marketing_status === MarketingStatus::Lost)->toBeTrue()
            ->and(InquiryStatusHistory::query()->count())->toBe(1);

        return;
    }

    // Not shown (or shown and locked): the request is refused.
    $response->assertUnprocessable()->assertJsonValidationErrors('status');
    expect($inquiry->refresh()->status->value)->toBe($status)
        ->and(InquiryStatusHistory::query()->count())->toBe(0);
})->with('inquiry button table');
