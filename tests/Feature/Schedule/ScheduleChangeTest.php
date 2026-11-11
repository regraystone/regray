<?php

use App\Enums\AuditEvent;
use App\Enums\BookedByType;
use App\Enums\MessageType;
use App\Enums\Role;
use App\Enums\ScheduleStatus;
use App\Models\AuditLog;
use App\Models\Inquiry;
use App\Models\Message;
use App\Models\Schedule;
use App\Models\User;
use App\Services\ScheduleChangeService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Testing\TestResponse;

/*
 * Changing an appointment after it is booked: update and cancel (a client on
 * their own, marketing and sales on any), approve, mark as attended and did
 * not attend (marketing and sales), and expiry. "Now" is Wednesday 14 October
 * 2026, 13:05 in Manila.
 */

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->travelTo(CarbonImmutable::parse('2026-10-14 13:05', 'Asia/Manila'));
});

/** A client's own booking, `$status`, arriving at `$at` in Manila. */
function appointment(User $client, ScheduleStatus $status = ScheduleStatus::Pending, string $at = '2026-10-16 10:00', ?Inquiry $inquiry = null): Schedule
{
    $schedule = new Schedule;
    $schedule->forceFill([
        'client_id' => $client->id,
        'booked_by' => $client->id,
        'booked_by_type' => BookedByType::Client,
        'inquiry_id' => $inquiry?->id,
        'title' => 'Showroom visit',
        'arrival_at' => CarbonImmutable::parse($at, 'Asia/Manila')->utc(),
        'status' => $status,
        'cancelled_at' => $status === ScheduleStatus::Cancelled ? now() : null,
        'cancel_reason' => $status === ScheduleStatus::Cancelled ? 'Client asked' : null,
    ])->save();

    return $schedule;
}

/** @param  array<string, string>  $overrides */
function updateInput(array $overrides = []): array
{
    return [
        'date' => '2026-10-17',
        'arrivalTime' => '14:00',
        'purpose' => 'Showroom visit, with the architect',
        'notes' => '',
        'reason' => 'The architect can only come on Saturday',
        ...$overrides,
    ];
}

/** Ask for one change to `$schedule` as `$user`. */
function changeSchedule(mixed $test, User $user, string $action, Schedule $schedule, array $data = []): TestResponse
{
    $test->actingAs($user)->from(route('schedule.index'));

    return match ($action) {
        'update' => $test->patch(route('schedule.update', $schedule), [...updateInput(), ...$data]),
        'cancel' => $test->post(route('schedule.cancel', $schedule), ['reason' => 'Plans changed', ...$data]),
        'approve' => $test->post(route('schedule.approve', $schedule)),
        'serve' => $test->post(route('schedule.serve', $schedule)),
        'noShow' => $test->post(route('schedule.no-show', $schedule)),
    };
}

/** The schedule cards posted for `$schedule`, oldest first. */
function scheduleCards(Schedule $schedule): array
{
    return Message::query()->where('type', MessageType::ScheduleCard)->where('schedule_id', $schedule->id)
        ->orderBy('id')->get()->map(fn (Message $card): array => [
            'status' => $card->announced_status,
            'revision' => $card->schedule_revision,
            'arrival' => $card->announced_arrival_at?->setTimezone('Asia/Manila')->format('Y-m-d H:i'),
            'reason' => $card->announced_reason,
        ])->all();
}

describe('who may change an appointment', function (): void {
    it('allows exactly the roles the matrix gives each action, on a client\'s pending booking', function (Role $role, array $allowed): void {
        $client = inquiryClient();
        // The client acts on their own booking; everyone else on that same one.
        $actor = $role === Role::Client ? $client : inquiryClient($role);

        foreach (['update', 'cancel', 'approve'] as $action) {
            $schedule = appointment($client);
            $response = changeSchedule($this, $actor, $action, $schedule);

            if (in_array($action, $allowed, true)) {
                $response->assertRedirect()->assertSessionHasNoErrors();
                expect($schedule->fresh()->revision)->toBe(1, "{$role->value} may {$action}");
            } else {
                $response->assertForbidden();
                expect($schedule->fresh()->revision)->toBe(0, "{$role->value} may not {$action}");
            }
        }
    })->with([
        'client' => [Role::Client, ['update', 'cancel']],
        'marketing' => [Role::Marketing, ['update', 'cancel', 'approve']],
        'sales' => [Role::Sales, ['update', 'cancel', 'approve']],
        'admin' => [Role::Admin, ['update', 'cancel', 'approve']],
        'owner' => [Role::Owner, []],
        'accounting' => [Role::Accounting, []],
        'technical' => [Role::Technical, []],
        'logistics' => [Role::Logistics, []],
    ]);

    it('refuses a client acting on another client\'s booking, even with the permission', function (string $action): void {
        $schedule = appointment(inquiryClient());

        changeSchedule($this, inquiryClient(), $action, $schedule)->assertForbidden();

        expect($schedule->fresh()->revision)->toBe(0);
    })->with(['update', 'cancel']);

    it('keeps marking how a visit went to marketing and sales, never a client', function (): void {
        $client = inquiryClient();
        $schedule = appointment($client, ScheduleStatus::Confirmed, '2026-10-14 11:00');

        foreach ([$client, inquiryClient(Role::Technical)] as $refused) {
            changeSchedule($this, $refused, 'serve', $schedule)->assertForbidden();
            changeSchedule($this, $refused, 'noShow', $schedule)->assertForbidden();
        }

        changeSchedule($this, inquiryClient(Role::Sales), 'serve', $schedule)->assertSessionHasNoErrors();

        expect($schedule->fresh()->status)->toBe(ScheduleStatus::Served);
    });
});

describe('reasons', function (): void {
    it('asks why an appointment is updated or cancelled, 3 to 255 characters once trimmed', function (string $action, string $reason, ?string $message): void {
        $client = inquiryClient();
        $schedule = appointment($client);

        $response = changeSchedule($this, $client, $action, $schedule, ['reason' => $reason]);

        if ($message === null) {
            $response->assertSessionHasNoErrors();
        } else {
            $response->assertSessionHasErrors(['reason' => $message]);
            expect($schedule->fresh()->revision)->toBe(0);
        }
    })->with([
        'update, none' => ['update', '', 'Say why the appointment is being updated'],
        'update, spaces' => ['update', '    ', 'Say why the appointment is being updated'],
        'cancel, none' => ['cancel', '', 'Say why the appointment is being cancelled'],
        'cancel, 2 characters' => ['cancel', 'no', 'Give a reason of at least 3 characters'],
        'cancel, 256 characters' => ['cancel', str_repeat('a', 256), 'Keep the reason to 255 characters or fewer'],
        'cancel, 255 characters' => ['cancel', str_repeat('a', 255), null],
    ]);
});

describe('updating', function (): void {
    it('moves the appointment, keeps the reason, and holds a client to the booking rules', function (): void {
        $client = inquiryClient();
        $schedule = appointment($client);

        changeSchedule($this, $client, 'update', $schedule, ['date' => '2026-10-14'])
            ->assertSessionHasErrors(['date' => 'Choose a day from tomorrow on']);
        changeSchedule($this, $client, 'update', $schedule, ['arrivalTime' => '19:00'])
            ->assertSessionHasErrors('arrivalTime');

        changeSchedule($this, $client, 'update', $schedule)->assertSessionHasNoErrors();

        $schedule->refresh();
        expect($schedule->title)->toBe('Showroom visit, with the architect')
            ->and($schedule->arrival_at->equalTo(CarbonImmutable::parse('2026-10-17 14:00', 'Asia/Manila')))->toBeTrue()
            ->and($schedule->change_reason)->toBe('The architect can only come on Saturday')
            ->and($schedule->status)->toBe(ScheduleStatus::Pending);
    });

    it('lets staff move an appointment to later today', function (): void {
        $schedule = appointment(inquiryClient());

        changeSchedule($this, inquiryClient(Role::Marketing), 'update', $schedule, ['date' => '2026-10-14', 'arrivalTime' => '16:00'])
            ->assertSessionHasNoErrors();
    });

    it('sends a confirmed appointment a client moves back for approval, and keeps it confirmed when staff move it', function (): void {
        $client = inquiryClient();
        $approver = inquiryClient(Role::Sales);
        $byClient = appointment($client, ScheduleStatus::Confirmed);
        $byStaff = appointment($client, ScheduleStatus::Confirmed);
        $byClient->forceFill(['approved_by' => $approver->id, 'approved_at' => now()])->save();
        $byStaff->forceFill(['approved_by' => $approver->id, 'approved_at' => now()])->save();

        changeSchedule($this, $client, 'update', $byClient)->assertSessionHasNoErrors();
        changeSchedule($this, inquiryClient(Role::Marketing), 'update', $byStaff)->assertSessionHasNoErrors();

        expect($byClient->fresh())->status->toBe(ScheduleStatus::Pending)->approved_by->toBeNull()->approved_at->toBeNull()
            ->and($byStaff->fresh())->status->toBe(ScheduleStatus::Confirmed)->approved_by->toBe($approver->id);
    });
});

describe('what an appointment\'s state allows', function (): void {
    it('refuses changing a served, cancelled or expired appointment, for the super-admin too', function (ScheduleStatus $status, string $action): void {
        $schedule = appointment(inquiryClient(), $status);

        changeSchedule($this, inquiryClient(Role::Admin), $action, $schedule)->assertSessionHasErrors('schedule');

        expect($schedule->fresh()->status)->toBe($status);
    })->with([ScheduleStatus::Served, ScheduleStatus::Cancelled, ScheduleStatus::Expired])
        ->with(['update', 'cancel', 'approve']);

    it('approves only a pending appointment, and the second of two approvals is refused with no second card', function (): void {
        $schedule = appointment(inquiryClient());
        $marketing = inquiryClient(Role::Marketing);
        $sales = inquiryClient(Role::Sales);

        changeSchedule($this, $marketing, 'approve', $schedule)->assertSessionHasNoErrors();
        changeSchedule($this, $sales, 'approve', $schedule)
            ->assertSessionHasErrors(['schedule' => 'This appointment is already approved.']);

        expect($schedule->fresh())->status->toBe(ScheduleStatus::Confirmed)->approved_by->toBe($marketing->id)
            ->and(collect(scheduleCards($schedule))->pluck('status')->all())->toBe(['confirmed']);
    });

    it('takes no change to an appointment whose time has passed, but lets staff mark how it went', function (): void {
        $client = inquiryClient();
        $sales = inquiryClient(Role::Sales);
        $past = appointment($client, ScheduleStatus::Confirmed, '2026-10-14 11:00');
        $ahead = appointment($client, ScheduleStatus::Confirmed);

        changeSchedule($this, $client, 'cancel', $past)->assertSessionHasErrors('schedule');
        changeSchedule($this, $sales, 'noShow', $ahead)
            ->assertSessionHasErrors(['schedule' => 'You can mark this once its time has come.']);

        changeSchedule($this, $sales, 'noShow', $past)->assertSessionHasNoErrors();

        expect($past->fresh())->status->toBe(ScheduleStatus::Cancelled)->cancel_reason->toBe('Did not attend')
            ->and($past->fresh()->cancelled_at)->not->toBeNull();
    });

    it('leaves an inquiry\'s visit to its inquiry', function (string $action): void {
        $client = inquiryClient();
        $inquiry = new Inquiry;
        $inquiry->forceFill(['reference' => 'INQ-SC1', 'client_id' => $client->id, 'name' => 'Kitchen', 'location' => 'Makati'])->save();
        $visit = appointment($client, ScheduleStatus::Confirmed, inquiry: $inquiry);

        changeSchedule($this, inquiryClient(Role::Admin), $action, $visit)
            ->assertSessionHasErrors(['schedule' => 'This visit belongs to an inquiry, so it changes with the inquiry.']);
    })->with(['update', 'cancel', 'approve']);
});

describe('the record and the client\'s chat', function (): void {
    it('writes one audit entry per change, with the actor, the reason and the values before and after', function (): void {
        $client = inquiryClient();
        $schedule = appointment($client);

        changeSchedule($this, $client, 'update', $schedule)->assertSessionHasNoErrors();
        changeSchedule($this, $client, 'cancel', $schedule, ['reason' => 'Found another supplier'])->assertSessionHasNoErrors();

        [$updated, $cancelled] = AuditLog::query()->orderBy('id')->get()->all();

        expect($updated)->event->toBe(AuditEvent::Updated)->user_id->toBe($client->id)
            ->and($updated->old_values['title'])->toBe('Showroom visit')
            ->and($updated->new_values['title'])->toBe('Showroom visit, with the architect')
            ->and($updated->new_values['reason'])->toBe('The architect can only come on Saturday')
            ->and($cancelled)->event->toBe(AuditEvent::StatusChanged)
            ->and($cancelled->old_values['status'])->toBe('pending')
            ->and($cancelled->new_values['status'])->toBe('cancelled')
            ->and($cancelled->new_values['reason'])->toBe('Found another supplier');
    });

    it('posts a card per change into the client\'s Regray thread, each keeping its own time and reason', function (): void {
        $client = inquiryClient();
        $schedule = appointment($client);
        $marketing = inquiryClient(Role::Marketing);

        changeSchedule($this, $marketing, 'approve', $schedule)->assertSessionHasNoErrors();
        changeSchedule($this, $client, 'update', $schedule)->assertSessionHasNoErrors();
        changeSchedule($this, $client, 'update', $schedule, ['date' => '2026-10-19', 'reason' => 'Saturday fell through'])->assertSessionHasNoErrors();
        changeSchedule($this, $marketing, 'approve', $schedule)->assertSessionHasNoErrors();
        changeSchedule($this, $marketing, 'cancel', $schedule, ['reason' => 'Showroom closed for inventory'])->assertSessionHasNoErrors();

        expect(scheduleCards($schedule))->toBe([
            ['status' => 'confirmed', 'revision' => 1, 'arrival' => '2026-10-16 10:00', 'reason' => null],
            ['status' => 'rescheduled', 'revision' => 2, 'arrival' => '2026-10-17 14:00', 'reason' => 'The architect can only come on Saturday'],
            ['status' => 'rescheduled', 'revision' => 3, 'arrival' => '2026-10-19 14:00', 'reason' => 'Saturday fell through'],
            ['status' => 'confirmed', 'revision' => 4, 'arrival' => '2026-10-19 14:00', 'reason' => null],
            ['status' => 'cancelled', 'revision' => 5, 'arrival' => '2026-10-19 14:00', 'reason' => 'Showroom closed for inventory'],
        ]);

        $threads = Message::query()->where('schedule_id', $schedule->id)->distinct()->pluck('conversation_id');
        expect($threads)->toHaveCount(1);

        $this->actingAs($client)->getJson(route('inbox.thread', $threads->first()))
            ->assertJsonPath('messages.1.scheduleCard.changeReason', 'The architect can only come on Saturday')
            ->assertJsonPath('messages.1.scheduleCard.arrivalAt', '2026-10-17T14:00:00+08:00');
    });
});

describe('expiry', function (): void {
    it('expires a booking nobody approved before its time, tells the client, and leaves the rest alone', function (): void {
        $client = inquiryClient();
        $unapproved = appointment($client, ScheduleStatus::Pending, '2026-10-14 11:00');
        $later = appointment($client, ScheduleStatus::Pending);
        $confirmed = appointment($client, ScheduleStatus::Confirmed, '2026-10-14 11:00');

        $this->artisan('schedules:expire')->assertSuccessful();

        expect($unapproved->fresh()->status)->toBe(ScheduleStatus::Expired)
            ->and($later->fresh()->status)->toBe(ScheduleStatus::Pending)
            ->and($confirmed->fresh()->status)->toBe(ScheduleStatus::Confirmed)
            ->and(collect(scheduleCards($unapproved))->pluck('status')->all())->toBe(['expired'])
            ->and(AuditLog::query()->sole()->user_id)->toBeNull();

        expect(app(ScheduleChangeService::class)->expire($unapproved->id))->toBeFalse();
    });
});

describe('the buttons the page offers', function (): void {
    it('offers each reader only the changes they may make now', function (): void {
        $client = inquiryClient();
        $pending = appointment($client);
        $pastConfirmed = appointment($client, ScheduleStatus::Confirmed, '2026-10-14 11:00');
        $range = ['from' => '2026-10-01', 'to' => '2026-11-01'];

        $can = fn (User $user, Schedule $schedule): array => collect($this->actingAs($user)
            ->get(route($user->isClient() ? 'profile.schedule' : 'schedule.index', $range))
            ->viewData('page')['props']['schedules'])->firstWhere('id', $schedule->id)['can'];

        expect($can($client, $pending))->toBe(['update' => true, 'cancel' => true, 'approve' => false, 'serve' => false, 'noShow' => false])
            ->and($can(inquiryClient(Role::Sales), $pending))->toBe(['update' => true, 'cancel' => true, 'approve' => true, 'serve' => false, 'noShow' => false])
            ->and($can(inquiryClient(Role::Sales), $pastConfirmed))->toBe(['update' => false, 'cancel' => false, 'approve' => false, 'serve' => true, 'noShow' => true]);

        // Technical is offered nothing: it does not even read a visit booked outside an inquiry.
        $technicalIds = array_column($this->actingAs(inquiryClient(Role::Technical))->get(route('schedule.index', $range))->viewData('page')['props']['schedules'], 'id');
        expect($technicalIds)->not->toContain($pending->id);
    });
});

describe('the update page', function (): void {
    it('opens the Book an appointment page filled in with the appointment, for its client and for staff', function (): void {
        $client = inquiryClient();
        $client->update(['name' => 'Rafa Domingo']);
        $schedule = appointment($client);

        $this->actingAs($client)->get(route('profile.schedule.edit', $schedule))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('schedule-book')
                ->where('schedule.id', $schedule->id)
                ->where('schedule.title', 'Showroom visit')
                ->where('schedule.date', '2026-10-16')
                ->where('schedule.arrivalTime', '10:00')
                ->where('schedule.clientName', null)
                ->where('earliestBookableDate', '2026-10-15'));

        $this->actingAs(inquiryClient(Role::Sales))->get(route('schedule.edit', $schedule))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('schedule.clientName', 'Rafa Domingo')
                ->where('earliestBookableDate', '2026-10-14'));
    });

    it('refuses the page to another client and to the roles that cannot update', function (): void {
        $schedule = appointment(inquiryClient());

        $this->actingAs(inquiryClient())->get(route('profile.schedule.edit', $schedule))->assertForbidden();
        $this->actingAs(inquiryClient(Role::Technical))->get(route('schedule.edit', $schedule))->assertForbidden();
    });

    it('sends the reader back with the reason when the appointment can no longer be updated', function (): void {
        $client = inquiryClient();
        $schedule = appointment($client, ScheduleStatus::Served);

        $this->actingAs($client)->get(route('profile.schedule.edit', $schedule))
            ->assertRedirect(route('profile.schedule'));
    });
});
