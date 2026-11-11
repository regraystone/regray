<?php

use App\Enums\BookedByType;
use App\Enums\InquiryStatus;
use App\Enums\MessageType;
use App\Enums\Role;
use App\Enums\ScheduleStatus;
use App\Models\Inquiry;
use App\Models\Schedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake(config('inquiry.disk'));
    seedInquiryCatalog();
    $this->client = inquiryClient();
});

it('gives each inquiry sent one confirmed schedule, at 10:00', function (): void {
    foreach ([
        inquiryProjectInput(['name' => 'Kitchen', 'neededBy' => '2030-03-04']),
        inquiryProjectInput(['name' => 'Survey', 'location' => 'Taguig', 'neededBy' => '2030-03-05']),
    ] as $project) {
        $this->actingAs($this->client)
            ->post(route('quote.inquire.store'), ['projects' => [$project]])
            ->assertSessionHasNoErrors();
    }

    [$kitchen, $survey] = Inquiry::query()->with('schedule')->orderBy('id')->get()->all();

    expect(Inquiry::query()->count())->toBe(2)
        ->and(Schedule::query()->count())->toBe(2)
        ->and($kitchen->name)->toBe('Kitchen')
        ->and($survey->name)->toBe('Survey')
        ->and($kitchen->reference)->not->toBe($survey->reference)
        ->and($kitchen->schedule)->toMatchArray([
            'client_id' => $this->client->id,
            'booked_by' => $this->client->id,
            'booked_by_type' => BookedByType::Client->value,
            'inquiry_id' => $kitchen->id,
            // An inquiry's visit needs no approval: it is confirmed from the start.
            'status' => ScheduleStatus::Confirmed->value,
            'title' => 'Kitchen',
            'notes' => "Inquiry {$kitchen->reference} · Kitchen · Makati",
        ])
        ->and($kitchen->schedule->arrival_at->equalTo(CarbonImmutable::parse('2030-03-04 10:00', 'Asia/Manila')))->toBeTrue()
        ->and($survey->schedule->inquiry_id)->toBe($survey->id)
        ->and($survey->schedule->title)->toBe('Survey')
        ->and($survey->schedule->notes)->toBe("Inquiry {$survey->reference} · Survey · Taguig")
        ->and($survey->schedule->arrival_at->equalTo(CarbonImmutable::parse('2030-03-05 10:00', 'Asia/Manila')))->toBeTrue();
});

it('cancels the inquiry\'s open schedule when the inquiry is cancelled, and leaves others alone', function (): void {
    foreach ([1, 2] as $inquiryNumber) {
        $this->actingAs($this->client)
            ->post(route('quote.inquire.store'), ['projects' => [inquiryProjectInput()]])
            ->assertSessionHasNoErrors();
    }

    [$inquiry, $other] = Inquiry::query()->orderBy('id')->get()->all();
    [$open, $untouched] = Schedule::query()->orderBy('id')->get()->all();

    $this->actingAs($this->client)->patch(route('quote.cancel', $inquiry->reference))->assertRedirect();

    expect($inquiry->fresh()->status)->toBe(InquiryStatus::Cancelled)
        ->and($open->fresh())->toMatchArray([
            'status' => ScheduleStatus::Cancelled->value,
            'cancel_reason' => 'Inquiry cancelled',
        ])
        ->and($open->fresh()->cancelled_at)->not->toBeNull()
        ->and($other->fresh()->status)->toBe(InquiryStatus::Pending)
        ->and($untouched->fresh()->status)->toBe(ScheduleStatus::Confirmed);
});

it('leaves a served schedule alone when the inquiry is cancelled', function (): void {
    $this->actingAs($this->client)
        ->post(route('quote.inquire.store'), ['projects' => [inquiryProjectInput()]])
        ->assertSessionHasNoErrors();

    $inquiry = Inquiry::query()->sole();
    $completed = Schedule::query()->sole();
    $completed->forceFill(['status' => ScheduleStatus::Served])->save();

    $this->actingAs($this->client)->patch(route('quote.cancel', $inquiry->reference))->assertRedirect();

    expect($completed->fresh()->status)->toBe(ScheduleStatus::Served);
});

it('shows a sent inquiry as a card in the Regray Team chat and as a visit on the Schedule page', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-14 08:00', 'Asia/Manila'));
    $thread = supportThreadFor($this->client);

    $this->actingAs($this->client)
        ->post(route('quote.inquire.store'), ['projects' => [
            inquiryProjectInput(['name' => 'Kitchen', 'neededBy' => '2026-10-20']),
        ]])
        ->assertSessionHasNoErrors();

    $inquiry = Inquiry::query()->with('schedule')->sole();
    $schedule = $inquiry->schedule;

    $this->actingAs($this->client)->getJson(route('inbox.thread', $thread))
        ->assertOk()
        ->assertJsonPath('messages.0.type', MessageType::InquiryCard->value)
        ->assertJsonPath('messages.0.inquiryCard.reference', $inquiry->reference)
        ->assertJsonPath('messages.0.inquiryCard.name', 'Kitchen');

    $expected = [
        'id' => $schedule->id,
        'title' => 'Kitchen',
        'arrivalAt' => CarbonImmutable::parse('2026-10-20 10:00', 'Asia/Manila')->toIso8601String(),
        'status' => ScheduleStatus::Confirmed->value,
        'notes' => "Inquiry {$inquiry->reference} · Kitchen · Makati",
        'inquiryReference' => $inquiry->reference,
        // An inquiry's visit follows its inquiry: no schedule action applies.
        'can' => ['update' => false, 'cancel' => false, 'approve' => false, 'serve' => false, 'noShow' => false],
    ];

    $clientPage = $this->actingAs($this->client)->get(route('profile.schedule'))->assertOk()->viewData('page')['props'];
    // Staff see an inquiry's visit wherever they read the inquiry.
    $marketing = messagingStaff(Role::Marketing);
    letStaffRead($inquiry, $marketing);
    $staffPage = $this->actingAs($marketing)->get(route('schedule.index'))->assertOk()->viewData('page')['props'];

    foreach ([$clientPage, $staffPage] as $props) {
        expect(collect($props['schedules'])->firstWhere('id', $schedule->id))->toMatchArray($expected)
            ->and(collect($props['upcoming'])->firstWhere('id', $schedule->id))->toMatchArray($expected);
    }
});
