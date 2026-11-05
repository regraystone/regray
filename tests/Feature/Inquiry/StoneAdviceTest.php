<?php

use App\Models\Inquiry;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Storage::fake(config('inquiry.disk'));
    seedInquiryCatalog();
    $this->client = inquiryClient();
});

/** @param  array<string, mixed>  $overrides */
function sendProject(mixed $test, array $overrides): mixed
{
    return $test->actingAs($test->client)
        ->post(route('quote.inquire.store'), ['projects' => [inquiryProjectInput($overrides)]]);
}

it('takes an inquiry with no stone when the client asks for a recommendation', function (): void {
    sendProject($this, ['stones' => [], 'stoneAdvice' => '1'])->assertSessionHasNoErrors();

    $inquiry = Inquiry::query()->sole();

    expect($inquiry->stone_advice_requested)->toBeTrue()
        ->and($inquiry->stones()->count())->toBe(0);
});

it('still asks for a stone without the request for a recommendation', function (mixed $advice): void {
    sendProject($this, ['stones' => [], 'stoneAdvice' => $advice])
        ->assertSessionHasErrors(['projects.0.stones' => 'Select at least one stone.']);

    expect(Inquiry::query()->count())->toBe(0);
})->with(['not sent' => null, 'refused' => '0']);

it('still needs to know where the stone will be used', function (): void {
    sendProject($this, ['stones' => [], 'stoneAdvice' => '1', 'applicationIds' => []])
        ->assertSessionHasErrors(['projects.0.applicationIds' => 'Select at least one application.']);
});

it('does not record a recommendation when stones were chosen', function (): void {
    sendProject($this, ['stoneAdvice' => '1'])->assertSessionHasNoErrors();

    $inquiry = Inquiry::query()->sole();

    expect($inquiry->stone_advice_requested)->toBeFalse()
        ->and($inquiry->stones()->count())->toBe(1);
});

it('says so on the inquiry page and when reopening it to edit', function (): void {
    sendProject($this, ['stones' => [], 'stoneAdvice' => '1'])->assertSessionHasNoErrors();

    $reference = Inquiry::query()->sole()->reference;

    $this->actingAs($this->client)->get(route('quote.show', $reference))
        ->assertInertia(fn (Assert $page) => $page->where('inquiry.project.stoneAdviceRequested', true));

    $this->actingAs($this->client)->get(route('quote.edit', $reference))
        ->assertInertia(fn (Assert $page) => $page->where('projects.0.stoneAdviceRequested', true));
});

it('clears the request when an edit chooses a stone, and keeps it when an edit does not', function (): void {
    sendProject($this, ['stones' => [], 'stoneAdvice' => '1'])->assertSessionHasNoErrors();

    $inquiry = Inquiry::query()->sole();
    $edit = ['id' => $inquiry->id, 'name' => $inquiry->name, 'keepFileIds' => []];

    $this->actingAs($this->client)
        ->patch(route('quote.update', $inquiry->reference), ['projects' => [inquiryProjectInput([
            ...$edit, 'stones' => [], 'stoneAdvice' => '1', 'notes' => 'Changed.',
        ])]])
        ->assertSessionHasNoErrors();

    expect($inquiry->refresh()->stone_advice_requested)->toBeTrue()
        ->and($inquiry->notes)->toBe('Changed.');

    $this->actingAs($this->client)
        ->patch(route('quote.update', $inquiry->reference), ['projects' => [inquiryProjectInput([
            ...$edit, 'stoneAdvice' => '0',
        ])]])
        ->assertSessionHasNoErrors();

    expect($inquiry->refresh()->stone_advice_requested)->toBeFalse()
        ->and($inquiry->stones()->count())->toBe(1);
});
