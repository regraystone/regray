<?php

use App\Enums\InquiryStatus;
use App\Enums\SalesStatus;
use App\Models\InquiryStatusHistory;

beforeEach(function (): void {
    seedInquiryCatalog();
    $this->client = inquiryClient();
});

it('enforces the table on the cancel endpoint', function (string $status, array $table): void {
    $inquiry = makeInquiry($this->client, attributes: ['reference' => 'INQ-A9', 'status' => InquiryStatus::from($status)]);

    $response = $this->actingAs($this->client)->patchJson(route('quote.cancel', 'INQ-A9'));

    if ($table['cancel'] === [true]) {
        // Shown and clickable: the request goes through.
        $response->assertRedirect();
        // With no lead yet, sales is rejected whether or not it had started.
        expect($inquiry->refresh()->status)->toBe(InquiryStatus::Cancelled)
            ->and($inquiry->sales_status)->toBe(SalesStatus::Rejected)
            ->and(InquiryStatusHistory::query()->count())->toBe(1);

        return;
    }

    // Not shown (or shown and locked): the request is refused.
    $response->assertUnprocessable()->assertJsonValidationErrors('status');
    // Sales won shows as quoted.
    expect($inquiry->refresh()->status->value)->toBe($status === 'won' ? 'quoted' : $status)
        ->and(InquiryStatusHistory::query()->count())->toBe(0);
})->with('inquiry button table');
