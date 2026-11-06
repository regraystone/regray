<?php

use App\Enums\DeliveryMethod;
use App\Enums\InquiryStatus;
use App\Models\Inquiry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake(config('inquiry.disk'));
    seedInquiryCatalog();
    $this->client = inquiryClient();
});

it('creates the whole row graph for each inquiry sent', function (): void {
    $plan = UploadedFile::fake()->create('plan.pdf', 50, 'application/pdf');

    $this->actingAs($this->client)
        ->post(route('quote.inquire.store'), ['projects' => [
            inquiryProjectInput([
                'attachments' => [$plan],
            ]),
        ]])
        ->assertSessionHasNoErrors();

    $this->actingAs($this->client)
        ->post(route('quote.inquire.store'), ['projects' => [inquiryProjectInput(['stones' => [], 'stoneAdvice' => '1'])]])
        ->assertSessionHasNoErrors();

    [$first, $advice] = Inquiry::query()->with(['workItems.service', 'stones', 'files', 'schedule', 'statusHistory'])->orderBy('id')->get()->all();

    foreach ([$first, $advice] as $inquiry) {
        expect($inquiry->reference)->toStartWith('INQ-')
            ->and($inquiry->client_id)->toBe($this->client->id)
            ->and($inquiry->status)->toBe(InquiryStatus::Pending)
            // No role status has started, so there is no history yet.
            ->and($inquiry->statusHistory)->toHaveCount(0);
    }

    expect($first->name)->toBe('Kitchen counters')
        ->and($first->location)->toBe('Makati')
        ->and($first->landmark)->toBe('Near the church')
        ->and((float) $first->area_sqm)->toBe(12.5)
        ->and($first->notes)->toBe('Ground floor.')
        ->and($first->delivery_method)->toBe(DeliveryMethod::Delivery)
        ->and($first->workItems->pluck('service.slug')->all())->toBe(['supply-only'])
        ->and($first->stones)->toHaveCount(1)
        ->and($first->stones->first()->quantity)->toBe(4)
        ->and($first->files)->toHaveCount(1)
        ->and($first->files->first()->path)->toStartWith("inquiries/{$first->reference}/")
        ->and($advice->stones)->toBeEmpty()
        ->and($advice->files)->toBeEmpty()
        ->and($advice->schedule->arrival_at->setTimezone('Asia/Manila')->format('H:i'))->toBe('10:00');

    Storage::disk(config('inquiry.disk'))->assertExists($first->files->first()->path);
});

it('numbers inquiries from the sequence', function (): void {
    foreach ([1, 2] as $_) {
        $this->actingAs($this->client)->post(route('quote.inquire.store'), ['projects' => [inquiryProjectInput()]]);
    }

    $numbers = Inquiry::query()->orderBy('id')->pluck('reference')->map(fn (string $reference): int => (int) substr($reference, 4));

    expect($numbers[1])->toBe($numbers[0] + 1);
});
