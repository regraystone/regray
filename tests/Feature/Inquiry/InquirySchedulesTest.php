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

it('books one pending schedule per project, at the visit time or 10:00 otherwise', function (): void {
    $this->actingAs($this->client)
        ->post(route('quote.inquire.store'), ['projects' => [
            inquiryProjectInput(['name' => 'Kitchen', 'neededBy' => '2030-03-04', 'neededByTime' => '15:45']),
            inquiryVisitInput(['name' => 'Survey', 'location' => 'Taguig', 'neededBy' => '2030-03-05', 'neededByTime' => '14:00']),
        ]])
        ->assertSessionHasNoErrors();

    $inquiry = Inquiry::query()->with('projects.schedule')->sole();
    [$kitchen, $survey] = $inquiry->projects->all();

    expect(Schedule::query()->count())->toBe(2)
        ->and($kitchen->schedule)->toMatchArray([
            'client_id' => $this->client->id,
            'booked_by' => $this->client->id,
            'booked_by_type' => BookedByType::Client->value,
            'inquiry_id' => $inquiry->id,
            'status' => ScheduleStatus::Pending->value,
            'title' => 'Kitchen',
            'notes' => "Inquiry {$inquiry->reference} · Kitchen · Makati",
        ])
        ->and($kitchen->schedule->arrival_at->equalTo(CarbonImmutable::parse('2030-03-04 10:00', 'Asia/Manila')))->toBeTrue()
        ->and($survey->schedule->title)->toBe('Survey')
        ->and($survey->schedule->notes)->toBe("Inquiry {$inquiry->reference} · Survey · Taguig")
        ->and($survey->schedule->arrival_at->equalTo(CarbonImmutable::parse('2030-03-05 14:00', 'Asia/Manila')))->toBeTrue();
});

it('cancels the inquiry\'s open schedules when the inquiry is cancelled', function (): void {
    $this->actingAs($this->client)
        ->post(route('quote.inquire.store'), ['projects' => [inquiryProjectInput(), inquiryProjectInput()]])
        ->assertSessionHasNoErrors();

    $inquiry = Inquiry::query()->sole();
    [$open, $completed] = Schedule::query()->orderBy('id')->get()->all();
    $completed->forceFill(['status' => ScheduleStatus::Completed])->save();

    $this->actingAs($this->client)->patch(route('quote.cancel', $inquiry->reference))->assertRedirect();

    expect($inquiry->fresh()->status)->toBe(InquiryStatus::Cancelled)
        ->and($open->fresh())->toMatchArray([
            'status' => ScheduleStatus::Cancelled->value,
            'cancel_reason' => 'Inquiry cancelled',
        ])
        ->and($open->fresh()->cancelled_at)->not->toBeNull()
        ->and($completed->fresh()->status)->toBe(ScheduleStatus::Completed);
});

it('shows a sent inquiry as a card in the Regray Team chat and as a visit on the Schedule page', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-14 08:00', 'Asia/Manila'));
    $thread = supportThreadFor($this->client);

    $this->actingAs($this->client)
        ->post(route('quote.inquire.store'), ['projects' => [
            inquiryProjectInput(['name' => 'Kitchen', 'neededBy' => '2026-10-20']),
        ]])
        ->assertSessionHasNoErrors();

    $inquiry = Inquiry::query()->with('projects.schedule')->sole();
    $schedule = $inquiry->projects->sole()->schedule;

    $this->actingAs($this->client)->getJson(route('message.thread', $thread))
        ->assertOk()
        ->assertJsonPath('messages.0.type', MessageType::InquiryCard->value)
        ->assertJsonPath('messages.0.inquiryCard.reference', $inquiry->reference)
        ->assertJsonPath('messages.0.inquiryCard.projectCount', 1);

    $expected = [
        'id' => $schedule->id,
        'title' => 'Kitchen',
        'arrivalAt' => CarbonImmutable::parse('2026-10-20 10:00', 'Asia/Manila')->utc()->toIso8601String(),
        'status' => ScheduleStatus::Pending->value,
        'notes' => "Inquiry {$inquiry->reference} · Kitchen · Makati",
        'inquiryReference' => $inquiry->reference,
    ];

    $clientPage = $this->actingAs($this->client)->get(route('profile.schedule'))->assertOk()->viewData('page')['props'];
    $staffPage = $this->actingAs(messagingStaff(Role::Marketing))->get(route('schedule.index'))->assertOk()->viewData('page')['props'];

    foreach ([$clientPage, $staffPage] as $props) {
        expect($props['schedules'])->toContain($expected)
            ->and($props['upcoming'])->toContain($expected);
    }
});
