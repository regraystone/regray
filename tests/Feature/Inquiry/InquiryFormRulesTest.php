<?php

use App\Http\Resources\InquiryDetailFieldResource;
use App\Models\DetailField;
use App\Models\Service;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

/*
 * What the Inquire page says around a service's questions and holds its
 * answers to: a service's date lines and fewest days ahead, the furthest a
 * date may be, and a free entry's own answer named by its question.
 */

beforeEach(function (): void {
    Storage::fake(config('inquiry.disk'));
    seedInquiryCatalog();
    $this->client = inquiryClient();
});

/**
 * Send one project as the client and hand back the response.
 *
 * @param  array<string, mixed>  $overrides
 */
function sendFormInquiry(mixed $test, array $overrides): TestResponse
{
    return $test->actingAs($test->client)->post(route('quote.inquire.store'), clientInquiryInput([inquiryProjectInput($overrides)]));
}

describe('the date', function (): void {
    it('is at least a furniture service\'s fewest days ahead', function (): void {
        $soon = now('Asia/Manila')->addDays(4)->toDateString();
        $ready = now('Asia/Manila')->addDays(5)->toDateString();

        sendFormInquiry($this, ['serviceId' => 'dining-tables', 'neededBy' => $soon])
            ->assertSessionHasErrors(['projects.0.neededBy' => 'Choose a date at least 5 days from today.']);

        sendFormInquiry($this, ['serviceId' => 'dining-tables', 'neededBy' => $ready])
            ->assertSessionHasNoErrors();
    });

    it('may be any day from today for a service that names no fewest days', function (): void {
        sendFormInquiry($this, ['serviceId' => 'countertop-backsplash', 'neededBy' => now('Asia/Manila')->toDateString()])
            ->assertSessionHasNoErrors();
    });

    it('follows the fewest days the service form sets', function (): void {
        // The service's own, not its application's: Countertop and Backsplash is sent under Slab Supply and Installation.
        Service::query()->where('slug', 'slab-supply-and-installation')->update(['lead_days' => 1]);

        sendFormInquiry($this, ['serviceId' => 'countertop-backsplash', 'neededBy' => now('Asia/Manila')->toDateString()])
            ->assertSessionHasErrors(['projects.0.neededBy' => 'Choose a date at least 1 day from today.']);
    });

    it('is within the next two years', function (): void {
        sendFormInquiry($this, ['neededBy' => now('Asia/Manila')->addYears(2)->addDay()->toDateString()])
            ->assertSessionHasErrors(['projects.0.neededBy' => 'Choose a date within the next 2 years.']);
    });

    it('sends each service\'s date and delivery lines and its fewest days to the Inquire page', function (): void {
        $this->actingAs($this->client)->get(route('quote.inquire'))
            ->assertInertia(fn ($page) => $page->where('serviceTypes.1.services.2.id', 'dining-tables')
                ->where('serviceTypes.1.services.2.leadDays', 5)
                ->where('serviceTypes.1.services.2.scheduleNote', fn (?string $note): bool => str_contains((string) $note, '5 to 7 days'))
                ->where('serviceTypes.1.services.2.deliveryNote', fn (?string $note): bool => str_contains((string) $note, 'Metro Manila')));
    });
});

it('asks a table its shape and base design, but not the base\'s material', function (): void {
    $asked = Service::query()->where('slug', 'dining-tables')->firstOrFail()->detailFields()->pluck('slug')->all();

    expect($asked)->toContain('table_shape', 'table_base_design_dining')->not->toContain('table_base');

    sendFormInquiry($this, ['serviceId' => 'dining-tables', 'answers' => [...inquiryAnswers('dining-tables'), 'table_base_design_dining' => 'tubular-v1']])
        ->assertSessionHasNoErrors();
});

describe('a free entry', function (): void {
    it('names its own answer and its line, and notes each suggestion', function (): void {
        $field = Service::query()->where('slug', 'dining-tables')->firstOrFail()->detailFields()->with('options')->where('slug', 'seaters')->firstOrFail();
        $sent = InquiryDetailFieldResource::make($field)->resolve();

        expect($sent['customLabel'])->toBe('Custom number')
            ->and($sent['customHint'])->toBe('Enter how many seats you need.')
            ->and($sent['suggestions'])->toBe([
                ['value' => '6', 'label' => '6 – approx. 1.6 sqm'],
                ['value' => '8', 'label' => '8 – approx. 2.2 sqm'],
                ['value' => '12', 'label' => '12 – approx. 3.3 sqm'],
            ]);
    });

    it('takes a seaters suggestion by its value, not its note', function (): void {
        sendFormInquiry($this, ['serviceId' => 'center-tables', 'answers' => [...inquiryAnswers('center-tables'), 'seaters' => '8']])
            ->assertSessionHasNoErrors();

        expect(DetailField::query()->where('slug', 'seaters')->sole()->options()->where('slug', '8')->value('name'))->toBe('8');
    });
});
