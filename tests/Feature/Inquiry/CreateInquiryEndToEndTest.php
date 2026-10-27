<?php

use App\Enums\InquiryStatus;
use App\Models\Inquiry;
use App\Models\InquiryFile;
use App\Models\InquiryStone;
use App\Models\ProjectCategory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake(config('inquiry.disk'));
    seedInquiryCatalog();
    $this->client = inquiryClient();
});

it('creates the whole row graph, one inquiry per project', function (): void {
    $applications = ProjectCategory::query()->orderBy('id')->limit(2)->pluck('slug')->all();
    $plan = UploadedFile::fake()->create('plan.pdf', 50, 'application/pdf');

    $this->actingAs($this->client)
        ->post(route('quote.inquire.store'), ['projects' => [
            inquiryProjectInput([
                'serviceIds' => ['natural-stone-supply', 'custom-stone-fabrication'],
                'applicationIds' => array_reverse($applications),
                'attachments' => [$plan],
            ]),
            inquiryVisitInput(),
        ]])
        ->assertSessionHasNoErrors();

    [$first, $visit] = Inquiry::query()->with(['services', 'categories', 'stones', 'files', 'schedule', 'statusHistory'])->orderBy('id')->get()->all();

    foreach ([$first, $visit] as $inquiry) {
        expect($inquiry->reference)->toStartWith('INQ-')
            ->and($inquiry->client_id)->toBe($this->client->id)
            ->and($inquiry->status)->toBe(InquiryStatus::Pending)
            ->and($inquiry->statusHistory)->toHaveCount(1);
    }

    expect($first->name)->toBe('Kitchen counters')
        ->and($first->location)->toBe('Makati')
        ->and($first->landmark)->toBe('Near the church')
        ->and((float) $first->area_sqm)->toBe(12.5)
        ->and($first->notes)->toBe('Ground floor.')
        ->and($first->services->pluck('slug')->all())->toEqualCanonicalizing(['natural-stone-supply', 'custom-stone-fabrication'])
        ->and($first->categories->pluck('slug')->all())->toBe(array_reverse($applications))
        ->and($first->stones)->toHaveCount(1)
        ->and($first->stones->first()->quantity)->toBe(4)
        ->and($first->files)->toHaveCount(1)
        ->and($first->files->first()->path)->toStartWith("inquiries/{$first->reference}/")
        ->and($visit->stones)->toBeEmpty()
        ->and($visit->files)->toBeEmpty()
        ->and($visit->schedule->arrival_at->setTimezone('Asia/Manila')->format('H:i'))->toBe('12:00');

    Storage::disk(config('inquiry.disk'))->assertExists($first->files->first()->path);
});

it('writes a standalone-only project with no stones', function (): void {
    $this->actingAs($this->client)
        ->post(route('quote.inquire.store'), ['projects' => [inquiryVisitInput()]])
        ->assertSessionHasNoErrors();

    $inquiry = Inquiry::query()->sole();

    expect($inquiry->stones()->count())->toBe(0)
        ->and($inquiry->categories()->count())->toBe(0)
        ->and($inquiry->services()->pluck('slug')->all())->toBe(['stone-relocation-dismantling-reinstallation']);
});

it('keeps stones and applications picked as extras on a standalone project', function (): void {
    $extras = inquiryProjectInput();

    $this->actingAs($this->client)
        ->post(route('quote.inquire.store'), ['projects' => [inquiryVisitInput([
            'stones' => $extras['stones'],
            'applicationIds' => $extras['applicationIds'],
        ])]])
        ->assertSessionHasNoErrors();

    $inquiry = Inquiry::query()->sole();

    expect($inquiry->stones()->count())->toBe(1)
        ->and($inquiry->categories()->count())->toBe(1);
});

it('returns 422-style errors for a regular service with no stones and writes nothing', function (): void {
    $this->actingAs($this->client)
        ->postJson(route('quote.inquire.store'), ['projects' => [inquiryProjectInput(['stones' => []])]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('projects.0.stones');

    expect(Inquiry::query()->count())->toBe(0)
        ->and(InquiryStone::query()->count())->toBe(0)
        ->and(InquiryFile::query()->count())->toBe(0);
});

it('numbers inquiries from the sequence', function (): void {
    foreach ([1, 2] as $_) {
        $this->actingAs($this->client)->post(route('quote.inquire.store'), ['projects' => [inquiryProjectInput()]]);
    }

    $numbers = Inquiry::query()->orderBy('id')->pluck('reference')->map(fn (string $reference): int => (int) substr($reference, 4));

    expect($numbers[1])->toBe($numbers[0] + 1);
});
