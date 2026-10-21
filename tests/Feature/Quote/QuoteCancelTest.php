<?php

use App\Enums\InquiryStatus;
use App\Enums\Role;
use App\Models\Inquiry;
use App\Models\InquiryStatusHistory;
use Illuminate\Support\Facades\DB;
use Inertia\Support\SessionKey;

beforeEach(function (): void {
    seedInquiryCatalog();
    $this->client = inquiryClient();
});

it('cancels a pending or quoted inquiry and writes the history', function (InquiryStatus $from): void {
    $inquiry = makeInquiry($this->client, attributes: ['reference' => 'INQ-C1', 'status' => $from]);

    $this->actingAs($this->client)
        ->patch(route('quote.cancel', 'INQ-C1'))
        ->assertRedirect()
        ->assertSessionHas(SessionKey::FLASH_DATA.'.toast.type', 'success');

    $inquiry->refresh();
    $history = InquiryStatusHistory::query()->sole();

    expect($inquiry->status)->toBe(InquiryStatus::Cancelled)
        ->and($history->inquiry_id)->toBe($inquiry->id)
        ->and($history->from_status)->toBe($from)
        ->and($history->to_status)->toBe(InquiryStatus::Cancelled)
        ->and($history->changed_by)->toBe($this->client->id);
})->with([
    'pending' => [InquiryStatus::Pending],
    'quoted' => [InquiryStatus::Quoted],
]);

it('refuses an inquiry that is no longer open', function (InquiryStatus $status): void {
    $inquiry = makeInquiry($this->client, attributes: ['reference' => 'INQ-C2', 'status' => $status]);

    $this->actingAs($this->client)
        ->patchJson(route('quote.cancel', 'INQ-C2'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');

    expect($inquiry->refresh()->status)->toBe($status)
        ->and(InquiryStatusHistory::query()->count())->toBe(0);
})->with([
    'paid' => [InquiryStatus::Paid],
    'completed' => [InquiryStatus::Completed],
    'cancelled' => [InquiryStatus::Cancelled],
]);

it('gives another client a 404 and changes nothing', function (): void {
    $inquiry = makeInquiry($this->client, attributes: ['reference' => 'INQ-C3']);

    $this->actingAs(inquiryClient())->patch(route('quote.cancel', 'INQ-C3'))->assertNotFound();

    expect($inquiry->refresh()->status)->toBe(InquiryStatus::Pending)
        ->and(InquiryStatusHistory::query()->count())->toBe(0);
});

it('answers an unknown and a malformed reference with the same 404', function (): void {
    $this->actingAs($this->client)->patch(route('quote.cancel', 'INQ-NOPE'))->assertNotFound();
    $this->actingAs($this->client)->patch(route('quote.cancel', 'bad ref!'))->assertNotFound();
});

it('writes one history row when cancelled twice', function (): void {
    makeInquiry($this->client, attributes: ['reference' => 'INQ-C4']);

    $this->actingAs($this->client)->patchJson(route('quote.cancel', 'INQ-C4'))->assertRedirect();
    $this->actingAs($this->client)->patchJson(route('quote.cancel', 'INQ-C4'))->assertUnprocessable();

    expect(InquiryStatusHistory::query()->count())->toBe(1)
        ->and(Inquiry::query()->where('reference', 'INQ-C4')->value('status'))->toBe(InquiryStatus::Cancelled);
});

it('takes the row lock before reading the status', function (): void {
    makeInquiry($this->client, attributes: ['reference' => 'INQ-C5']);
    $queries = [];

    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    $this->actingAs($this->client)->patch(route('quote.cancel', 'INQ-C5'));

    expect(collect($queries)->contains(fn (string $sql): bool => str_contains($sql, 'for update')))->toBeTrue();
});

it('turns away a role without quotations.view and a guest', function (): void {
    makeInquiry($this->client, attributes: ['reference' => 'INQ-C6']);

    $this->actingAs(inquiryClient(Role::Sales))->patch(route('quote.cancel', 'INQ-C6'))->assertForbidden();
    auth()->logout();
    $this->patch(route('quote.cancel', 'INQ-C6'))->assertRedirect(route('login'));
});
