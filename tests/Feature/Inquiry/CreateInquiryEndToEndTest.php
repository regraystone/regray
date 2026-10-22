<?php

use App\Enums\InquiryStatus;
use App\Models\Inquiry;
use App\Models\InquiryFile;
use App\Models\InquiryProject;
use App\Models\InquiryProjectStone;
use App\Models\ProjectCategory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake(config('inquiry.disk'));
    seedInquiryCatalog();
    $this->client = inquiryClient();
});

it('creates the whole row graph', function (): void {
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

    $inquiry = Inquiry::query()->with(['projects.services', 'projects.categories', 'projects.stones', 'projects.files', 'projects.schedule', 'statusHistory'])->sole();

    expect($inquiry->reference)->toStartWith('INQ-')
        ->and($inquiry->client_id)->toBe($this->client->id)
        ->and($inquiry->status)->toBe(InquiryStatus::Pending)
        ->and($inquiry->statusHistory)->toHaveCount(1)
        ->and($inquiry->projects)->toHaveCount(2);

    [$first, $visit] = $inquiry->projects->all();

    expect($first->name)->toBe('Kitchen counters')
        ->and($first->sort_order)->toBe(0)
        ->and($first->services->pluck('slug')->all())->toEqualCanonicalizing(['natural-stone-supply', 'custom-stone-fabrication'])
        ->and($first->categories->pluck('slug')->all())->toBe(array_reverse($applications))
        ->and($first->stones)->toHaveCount(1)
        ->and($first->stones->first()->quantity)->toBe(4)
        ->and($first->files)->toHaveCount(1)
        ->and($first->files->first()->path)->toStartWith("inquiries/{$inquiry->reference}/{$first->id}/")
        ->and($visit->sort_order)->toBe(1)
        ->and($visit->stones)->toBeEmpty()
        ->and($visit->schedule->arrival_at->setTimezone('Asia/Manila')->format('H:i'))->toBe('12:00');

    Storage::disk(config('inquiry.disk'))->assertExists($first->files->first()->path);
});

it('writes a standalone-only project with no stones', function (): void {
    $this->actingAs($this->client)
        ->post(route('quote.inquire.store'), ['projects' => [inquiryVisitInput()]])
        ->assertSessionHasNoErrors();

    $project = InquiryProject::query()->sole();

    expect($project->stones()->count())->toBe(0)
        ->and($project->categories()->count())->toBe(0)
        ->and($project->services()->pluck('slug')->all())->toBe(['site-ocular-technical-assessment']);
});

it('keeps stones and applications picked as extras on a standalone project', function (): void {
    $extras = inquiryProjectInput();

    $this->actingAs($this->client)
        ->post(route('quote.inquire.store'), ['projects' => [inquiryVisitInput([
            'stones' => $extras['stones'],
            'applicationIds' => $extras['applicationIds'],
        ])]])
        ->assertSessionHasNoErrors();

    $project = InquiryProject::query()->sole();

    expect($project->stones()->count())->toBe(1)
        ->and($project->categories()->count())->toBe(1);
});

it('returns 422-style errors for a regular service with no stones and writes nothing', function (): void {
    $this->actingAs($this->client)
        ->postJson(route('quote.inquire.store'), ['projects' => [inquiryProjectInput(['stones' => []])]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('projects.0.stones');

    expect(Inquiry::query()->count())->toBe(0)
        ->and(InquiryProjectStone::query()->count())->toBe(0)
        ->and(InquiryFile::query()->count())->toBe(0);
});

it('numbers inquiries from the sequence', function (): void {
    foreach ([1, 2] as $_) {
        $this->actingAs($this->client)->post(route('quote.inquire.store'), ['projects' => [inquiryProjectInput()]]);
    }

    $numbers = Inquiry::query()->orderBy('id')->pluck('reference')->map(fn (string $reference): int => (int) substr($reference, 4));

    expect($numbers[1])->toBe($numbers[0] + 1);
});
