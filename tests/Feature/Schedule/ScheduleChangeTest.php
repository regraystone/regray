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
 * Changing an appointment after it is booked. A client updates and cancels
 * their own; marketing updates, cancels and assigns it to a sales member; the
 * sales member it is assigned to confirms it or declines it (back to
 * marketing, with a reason); marketing and that sales member mark how it
 * went; and an unconfirmed one expires. "Now" is Wednesday 14 October 2026,
 * 13:05 in Manila.
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

/**
 * A client's booking assigned to `$sales`: awaiting them, or `Confirmed` by
 * them. `$by` is the marketer who assigned it; with `$staffBooked` they also
 * booked it, so the client has not been told of it until it is confirmed.
 */
function assignedAppointment(User $client, User $sales, ScheduleStatus $status = ScheduleStatus::AwaitingSales, string $at = '2026-10-16 10:00', ?User $by = null, bool $staffBooked = false): Schedule
{
    $by ??= inquiryClient(Role::Marketing);
    $schedule = appointment($client, $status, $at);
    $schedule->forceFill([
        'booked_by' => $staffBooked ? $by->id : $client->id,
        'booked_by_type' => $staffBooked ? BookedByType::Staff : BookedByType::Client,
        'assigned_to' => $sales->id,
        'assigned_by' => $by->id,
        'assigned_at' => now(),
        'approved_by' => $status === ScheduleStatus::Confirmed ? $sales->id : null,
        'approved_at' => $status === ScheduleStatus::Confirmed ? now() : null,
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

/** Ask for one change to `$schedule` as `$user`. `assign` names a fresh sales member unless `$data` names one. */
function changeSchedule(mixed $test, User $user, string $action, Schedule $schedule, array $data = []): TestResponse
{
    $data = $action === 'assign' && ! array_key_exists('salesId', $data) ? ['salesId' => inquiryClient(Role::Sales)->id, ...$data] : $data;
    $test->actingAs($user)->from(route('schedule.index'));

    return match ($action) {
        'update' => $test->patch(route('schedule.update', $schedule), [...updateInput(), ...$data]),
        'cancel' => $test->post(route('schedule.cancel', $schedule), ['reason' => 'Plans changed', ...$data]),
        'assign' => $test->post(route('schedule.assign', $schedule), $data),
        'confirm' => $test->post(route('schedule.confirm', $schedule)),
        'decline' => $test->post(route('schedule.decline', $schedule), ['reason' => 'Out of the office that day', ...$data]),
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

        foreach (['update', 'cancel', 'assign'] as $action) {
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
        'marketing' => [Role::Marketing, ['update', 'cancel', 'assign']],
        'sales' => [Role::Sales, []],
        'admin' => [Role::Admin, ['update', 'cancel', 'assign']],
        'owner' => [Role::Owner, []],
        'accounting' => [Role::Accounting, []],
        'technical' => [Role::Technical, []],
        'logistics' => [Role::Logistics, []],
    ]);

    it('lets only the sales member it is assigned to (and the admin) confirm or decline it', function (string $who, bool $allowed): void {
        $client = inquiryClient();
        $sales = inquiryClient(Role::Sales);
        $actor = match ($who) {
            'its sales member' => $sales,
            'client' => $client,
            default => inquiryClient(Role::from($who)),
        };

        foreach (['confirm', 'decline'] as $action) {
            $schedule = assignedAppointment($client, $sales);
            $response = changeSchedule($this, $actor, $action, $schedule);

            if ($allowed) {
                $response->assertRedirect()->assertSessionHasNoErrors();
                expect($schedule->fresh()->revision)->toBe(1, "{$who} may {$action}");
            } else {
                expect($response->status())->toBeIn([403, 404], "{$who} may not {$action}")
                    ->and($schedule->fresh()->revision)->toBe(0);
            }
        }
    })->with([
        ['its sales member', true],
        ['admin', true],
        // Another sales member does not even read it: a 404.
        ['sales', false],
        ['marketing', false],
        ['client', false],
        ['owner', false],
    ]);

    it('refuses the sales member it was taken from, should they answer after it is reassigned', function (): void {
        $client = inquiryClient();
        $first = inquiryClient(Role::Sales);
        $second = inquiryClient(Role::Sales);
        $schedule = assignedAppointment($client, $first);
        $stale = $schedule->fresh();

        changeSchedule($this, $schedule->assignedBy, 'assign', $schedule, ['salesId' => $second->id])->assertSessionHasNoErrors();

        // Their page still showed it; the visit itself is now someone else's to answer.
        changeSchedule($this, $first, 'confirm', $stale)->assertNotFound();
        changeSchedule($this, $first, 'decline', $stale)->assertNotFound();

        expect($schedule->fresh())->status->toBe(ScheduleStatus::AwaitingSales)->assigned_to->toBe($second->id);
    });

    it('refuses a client acting on another client\'s booking, even with the permission', function (string $action): void {
        $schedule = appointment(inquiryClient());

        // Answered as missing, so a client cannot tell which visit ids are taken.
        changeSchedule($this, inquiryClient(), $action, $schedule)->assertNotFound();

        expect($schedule->fresh()->revision)->toBe(0);
    })->with(['update', 'cancel']);

    it('keeps marking how a visit went to its sales member, never marketing or a client', function (): void {
        $client = inquiryClient();
        $sales = inquiryClient(Role::Sales);
        $schedule = assignedAppointment($client, $sales, ScheduleStatus::Confirmed, '2026-10-14 11:00');

        // Marketing booked and assigned it, and still may not say how it went.
        foreach ([$client, $schedule->assignedBy, inquiryClient(Role::Technical)] as $refused) {
            changeSchedule($this, $refused, 'serve', $schedule)->assertForbidden();
            changeSchedule($this, $refused, 'noShow', $schedule)->assertForbidden();
        }

        expect($schedule->fresh()->status)->toBe(ScheduleStatus::Confirmed);

        changeSchedule($this, $sales, 'serve', $schedule)->assertSessionHasNoErrors();

        expect($schedule->fresh()->status)->toBe(ScheduleStatus::Served);

        $other = assignedAppointment($client, $sales, ScheduleStatus::Confirmed, '2026-10-14 11:00');
        changeSchedule($this, $sales, 'noShow', $other)->assertSessionHasNoErrors();

        expect($other->fresh()->status)->toBe(ScheduleStatus::Cancelled);
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

    it('asks the sales member why they decline, by the same rules', function (string $reason, ?string $message): void {
        $sales = inquiryClient(Role::Sales);
        $schedule = assignedAppointment(inquiryClient(), $sales);

        $response = changeSchedule($this, $sales, 'decline', $schedule, ['reason' => $reason]);

        if ($message === null) {
            $response->assertSessionHasNoErrors();
        } else {
            $response->assertSessionHasErrors(['reason' => $message]);
            expect($schedule->fresh()->status)->toBe(ScheduleStatus::AwaitingSales);
        }
    })->with([
        'none' => ['', 'Say why you are declining the appointment'],
        '2 characters' => ['no', 'Give a reason of at least 3 characters'],
        'enough' => ['On leave that week', null],
    ]);

    it('asks marketing which sales member to assign, and only a sales member will do', function (): void {
        $marketing = inquiryClient(Role::Marketing);
        $schedule = appointment(inquiryClient());

        changeSchedule($this, $marketing, 'assign', $schedule, ['salesId' => ''])
            ->assertSessionHasErrors(['salesId' => 'Choose a sales member to confirm this appointment']);

        foreach ([inquiryClient(Role::Marketing), inquiryClient(Role::Technical), inquiryClient()] as $notSales) {
            changeSchedule($this, $marketing, 'assign', $schedule, ['salesId' => $notSales->id])
                ->assertSessionHasErrors(['salesId' => 'Choose a sales member to confirm this appointment']);
        }

        expect($schedule->fresh()->revision)->toBe(0);
    });
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

    it('lets marketing move an appointment to later today', function (): void {
        $schedule = appointment(inquiryClient());

        changeSchedule($this, inquiryClient(Role::Marketing), 'update', $schedule, ['date' => '2026-10-14', 'arrivalTime' => '16:00'])
            ->assertSessionHasNoErrors();
    });

    it('sends a confirmed appointment back to its sales member when it moves, or when the client changes it', function (): void {
        $client = inquiryClient();
        $sales = inquiryClient(Role::Sales);
        $byClient = assignedAppointment($client, $sales, ScheduleStatus::Confirmed);
        $movedByMarketing = assignedAppointment($client, $sales, ScheduleStatus::Confirmed);
        $detailsByMarketing = assignedAppointment($client, $sales, ScheduleStatus::Confirmed);

        changeSchedule($this, $client, 'update', $byClient)->assertSessionHasNoErrors();
        changeSchedule($this, $movedByMarketing->assignedBy, 'update', $movedByMarketing)->assertSessionHasNoErrors();
        changeSchedule($this, $detailsByMarketing->assignedBy, 'update', $detailsByMarketing, ['date' => '2026-10-16', 'arrivalTime' => '10:00'])
            ->assertSessionHasNoErrors();

        expect($byClient->fresh())->status->toBe(ScheduleStatus::AwaitingSales)->assigned_to->toBe($sales->id)
            ->and($movedByMarketing->fresh())->status->toBe(ScheduleStatus::AwaitingSales)->assigned_to->toBe($sales->id)
            ->and($detailsByMarketing->fresh())->status->toBe(ScheduleStatus::Confirmed);
    });
});

describe('assigning, confirming and declining', function (): void {
    it('assigns a pending booking to a sales member, who confirms it', function (): void {
        $client = inquiryClient();
        $marketing = inquiryClient(Role::Marketing);
        $sales = inquiryClient(Role::Sales);
        $schedule = appointment($client);

        changeSchedule($this, $marketing, 'assign', $schedule, ['salesId' => $sales->id])->assertSessionHasNoErrors();

        expect($schedule->fresh())->status->toBe(ScheduleStatus::AwaitingSales)
            ->assigned_to->toBe($sales->id)->assigned_by->toBe($marketing->id)
            ->and($schedule->fresh()->assigned_at)->not->toBeNull();

        changeSchedule($this, $sales, 'confirm', $schedule)->assertSessionHasNoErrors();

        expect($schedule->fresh())->status->toBe(ScheduleStatus::Confirmed)->approved_by->toBe($sales->id);
    });

    it('sends a declined appointment back to marketing with the reason, cleared once it is assigned again', function (): void {
        $client = inquiryClient();
        $marketing = inquiryClient(Role::Marketing);
        $sales = inquiryClient(Role::Sales);
        $schedule = assignedAppointment($client, $sales, by: $marketing);

        changeSchedule($this, $sales, 'decline', $schedule, ['reason' => 'On leave that week'])->assertSessionHasNoErrors();

        expect($schedule->fresh())
            ->status->toBe(ScheduleStatus::Pending)
            ->assigned_to->toBeNull()
            ->assigned_by->toBe($marketing->id)
            ->declined_by->toBe($sales->id)
            ->decline_reason->toBe('On leave that week')
            ->and($schedule->fresh()->isVisibleTo($sales))->toBeFalse()
            ->and($schedule->fresh()->isVisibleTo($marketing))->toBeTrue();

        $other = inquiryClient(Role::Sales);
        changeSchedule($this, $marketing, 'assign', $schedule, ['salesId' => $other->id])->assertSessionHasNoErrors();

        expect($schedule->fresh())
            ->status->toBe(ScheduleStatus::AwaitingSales)
            ->assigned_to->toBe($other->id)
            ->declined_by->toBeNull()
            ->declined_at->toBeNull()
            ->decline_reason->toBeNull();
    });

    it('lets marketing reassign an appointment still awaiting sales, but not a confirmed one', function (): void {
        $marketing = inquiryClient(Role::Marketing);
        $first = inquiryClient(Role::Sales);
        $second = inquiryClient(Role::Sales);
        $waiting = assignedAppointment(inquiryClient(), $first, by: $marketing);
        $confirmed = assignedAppointment(inquiryClient(), $first, ScheduleStatus::Confirmed, by: $marketing);

        changeSchedule($this, $marketing, 'assign', $waiting, ['salesId' => $second->id])->assertSessionHasNoErrors();
        changeSchedule($this, $marketing, 'assign', $confirmed, ['salesId' => $second->id])
            ->assertSessionHasErrors(['schedule' => 'This appointment is already confirmed.']);

        expect($waiting->fresh()->assigned_to)->toBe($second->id)
            ->and($waiting->fresh()->isVisibleTo($first))->toBeFalse()
            ->and($confirmed->fresh()->assigned_to)->toBe($first->id);
    });

    it('confirms only an appointment awaiting sales, and the second of two confirmations is refused with no second card', function (): void {
        $sales = inquiryClient(Role::Sales);
        $schedule = assignedAppointment(inquiryClient(), $sales);
        $unassigned = appointment(inquiryClient());

        changeSchedule($this, $sales, 'confirm', $schedule)->assertSessionHasNoErrors();
        changeSchedule($this, $sales, 'confirm', $schedule)
            ->assertSessionHasErrors(['schedule' => 'This appointment is already confirmed.']);
        changeSchedule($this, inquiryClient(Role::Admin), 'confirm', $unassigned)
            ->assertSessionHasErrors(['schedule' => 'This appointment is not assigned to sales yet.']);

        expect($schedule->fresh())->status->toBe(ScheduleStatus::Confirmed)->approved_by->toBe($sales->id)
            ->and(collect(scheduleCards($schedule))->pluck('status')->all())->toBe(['confirmed']);
    });

    it('waits for sales to confirm before anyone marks how the visit went', function (): void {
        $sales = inquiryClient(Role::Sales);
        $schedule = assignedAppointment(inquiryClient(), $sales, at: '2026-10-14 11:00');

        changeSchedule($this, $sales, 'serve', $schedule)
            ->assertSessionHasErrors(['schedule' => 'Sales must confirm this appointment before you mark whether the client came.']);
    });
});

describe('what an appointment\'s state allows', function (): void {
    it('refuses changing a served, cancelled or expired appointment, for the super-admin too', function (ScheduleStatus $status, string $action): void {
        $schedule = appointment(inquiryClient(), $status);

        changeSchedule($this, inquiryClient(Role::Admin), $action, $schedule)->assertSessionHasErrors('schedule');

        expect($schedule->fresh()->status)->toBe($status);
    })->with([ScheduleStatus::Served, ScheduleStatus::Cancelled, ScheduleStatus::Expired])
        ->with(['update', 'cancel', 'assign', 'confirm', 'decline']);

    it('takes no change to an appointment whose time has passed, but lets staff mark how it went', function (): void {
        $client = inquiryClient();
        $sales = inquiryClient(Role::Sales);
        $past = assignedAppointment($client, $sales, ScheduleStatus::Confirmed, '2026-10-14 11:00');
        $ahead = assignedAppointment($client, $sales, ScheduleStatus::Confirmed);
        $pastWaiting = assignedAppointment($client, $sales, at: '2026-10-14 11:00');

        changeSchedule($this, $client, 'cancel', $past)->assertSessionHasErrors('schedule');
        changeSchedule($this, $sales, 'confirm', $pastWaiting)
            ->assertSessionHasErrors(['schedule' => 'This appointment\'s time has passed, so it can no longer be confirmed.']);
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
    })->with(['update', 'cancel', 'assign', 'confirm', 'decline']);
});

describe('the record and the client\'s chat', function (): void {
    it('writes one audit entry per change, with the actor, the reason and the values before and after', function (): void {
        $client = inquiryClient();
        $schedule = appointment($client);
        $marketing = inquiryClient(Role::Marketing);
        $sales = inquiryClient(Role::Sales);

        changeSchedule($this, $client, 'update', $schedule)->assertSessionHasNoErrors();
        changeSchedule($this, $marketing, 'assign', $schedule, ['salesId' => $sales->id])->assertSessionHasNoErrors();
        changeSchedule($this, $sales, 'decline', $schedule, ['reason' => 'On leave that week'])->assertSessionHasNoErrors();
        changeSchedule($this, $client, 'cancel', $schedule, ['reason' => 'Found another supplier'])->assertSessionHasNoErrors();

        [$updated, $assigned, $declined, $cancelled] = AuditLog::query()->orderBy('id')->get()->all();

        expect($updated)->event->toBe(AuditEvent::Updated)->user_id->toBe($client->id)
            ->and($updated->old_values['title'])->toBe('Showroom visit')
            ->and($updated->new_values['title'])->toBe('Showroom visit, with the architect')
            ->and($updated->new_values['reason'])->toBe('The architect can only come on Saturday')
            ->and($assigned)->event->toBe(AuditEvent::StatusChanged)->user_id->toBe($marketing->id)
            ->and($assigned->new_values['status'])->toBe('awaiting_sales')
            ->and($assigned->new_values['assigned_to'])->toBe($sales->id)
            ->and($declined)->user_id->toBe($sales->id)
            ->and($declined->new_values['status'])->toBe('pending')
            ->and($declined->new_values['reason'])->toBe('On leave that week')
            ->and($cancelled)->event->toBe(AuditEvent::StatusChanged)
            ->and($cancelled->old_values['status'])->toBe('pending')
            ->and($cancelled->new_values['status'])->toBe('cancelled')
            ->and($cancelled->new_values['reason'])->toBe('Found another supplier');
    });

    it('posts a card per change the client should hear of into their Regray thread, each keeping its own time and reason', function (): void {
        $client = inquiryClient();
        $schedule = appointment($client);
        $marketing = inquiryClient(Role::Marketing);
        $sales = inquiryClient(Role::Sales);

        changeSchedule($this, $marketing, 'assign', $schedule, ['salesId' => $sales->id])->assertSessionHasNoErrors();
        changeSchedule($this, $sales, 'confirm', $schedule)->assertSessionHasNoErrors();
        changeSchedule($this, $client, 'update', $schedule)->assertSessionHasNoErrors();
        changeSchedule($this, $client, 'update', $schedule, ['date' => '2026-10-19', 'reason' => 'Saturday fell through'])->assertSessionHasNoErrors();
        changeSchedule($this, $sales, 'decline', $schedule, ['reason' => 'On leave that week'])->assertSessionHasNoErrors();
        changeSchedule($this, $marketing, 'assign', $schedule, ['salesId' => $sales->id])->assertSessionHasNoErrors();
        changeSchedule($this, $sales, 'confirm', $schedule)->assertSessionHasNoErrors();
        changeSchedule($this, $marketing, 'cancel', $schedule, ['reason' => 'Showroom closed for inventory'])->assertSessionHasNoErrors();

        // Assigning and declining are between marketing and sales: no card.
        expect(scheduleCards($schedule))->toBe([
            ['status' => 'confirmed', 'revision' => 2, 'arrival' => '2026-10-16 10:00', 'reason' => null],
            ['status' => 'rescheduled', 'revision' => 3, 'arrival' => '2026-10-17 14:00', 'reason' => 'The architect can only come on Saturday'],
            ['status' => 'rescheduled', 'revision' => 4, 'arrival' => '2026-10-19 14:00', 'reason' => 'Saturday fell through'],
            ['status' => 'confirmed', 'revision' => 7, 'arrival' => '2026-10-19 14:00', 'reason' => null],
            ['status' => 'cancelled', 'revision' => 8, 'arrival' => '2026-10-19 14:00', 'reason' => 'Showroom closed for inventory'],
        ]);

        $threads = Message::query()->where('schedule_id', $schedule->id)->distinct()->pluck('conversation_id');
        expect($threads)->toHaveCount(1);

        $this->actingAs($client)->getJson(route('inbox.thread', $threads->first()))
            ->assertJsonPath('messages.1.scheduleCard.changeReason', 'The architect can only come on Saturday')
            ->assertJsonPath('messages.1.scheduleCard.arrivalAt', '2026-10-17T14:00:00+08:00');
    });

    it('keeps a marketing booking out of the client\'s chat until its sales member confirms it, and then tells them of every change', function (): void {
        $client = inquiryClient();
        $marketing = inquiryClient(Role::Marketing);
        $sales = inquiryClient(Role::Sales);
        $schedule = assignedAppointment($client, $sales, by: $marketing, staffBooked: true);

        changeSchedule($this, $marketing, 'update', $schedule)->assertSessionHasNoErrors();
        changeSchedule($this, $sales, 'decline', $schedule)->assertSessionHasNoErrors();
        changeSchedule($this, $marketing, 'assign', $schedule, ['salesId' => $sales->id])->assertSessionHasNoErrors();

        expect(scheduleCards($schedule))->toBe([]);

        changeSchedule($this, $sales, 'confirm', $schedule)->assertSessionHasNoErrors();
        changeSchedule($this, $marketing, 'update', $schedule, ['date' => '2026-10-19', 'reason' => 'Saturday fell through'])->assertSessionHasNoErrors();

        expect(collect(scheduleCards($schedule))->pluck('status')->all())->toBe(['confirmed', 'rescheduled']);
    });
});

describe('expiry', function (): void {
    it('expires a booking nobody confirmed before its time, tells the client of their own, and leaves the rest alone', function (): void {
        $client = inquiryClient();
        $sales = inquiryClient(Role::Sales);
        $unassigned = appointment($client, ScheduleStatus::Pending, '2026-10-14 11:00');
        $waiting = assignedAppointment($client, $sales, at: '2026-10-14 11:00');
        $staffBooked = assignedAppointment($client, $sales, at: '2026-10-14 11:00', staffBooked: true);
        $later = appointment($client, ScheduleStatus::Pending);
        $confirmed = assignedAppointment($client, $sales, ScheduleStatus::Confirmed, '2026-10-14 11:00');

        $this->artisan('schedules:expire')->assertSuccessful();

        expect($unassigned->fresh()->status)->toBe(ScheduleStatus::Expired)
            ->and($waiting->fresh()->status)->toBe(ScheduleStatus::Expired)
            ->and($staffBooked->fresh()->status)->toBe(ScheduleStatus::Expired)
            ->and($later->fresh()->status)->toBe(ScheduleStatus::Pending)
            ->and($confirmed->fresh()->status)->toBe(ScheduleStatus::Confirmed)
            ->and(collect(scheduleCards($unassigned))->pluck('status')->all())->toBe(['expired'])
            ->and(collect(scheduleCards($waiting))->pluck('status')->all())->toBe(['expired'])
            // The client never heard of it, so they are not told it expired.
            ->and(scheduleCards($staffBooked))->toBe([])
            ->and(AuditLog::query()->whereNotNull('user_id')->exists())->toBeFalse();

        expect(app(ScheduleChangeService::class)->expire($unassigned->id))->toBeFalse();
    });
});

describe('the buttons the page offers', function (): void {
    it('offers each reader only the changes they may make now', function (): void {
        $client = inquiryClient();
        $marketing = inquiryClient(Role::Marketing);
        $sales = inquiryClient(Role::Sales);
        $pending = appointment($client);
        $waiting = assignedAppointment($client, $sales, by: $marketing);
        $pastConfirmed = assignedAppointment($client, $sales, ScheduleStatus::Confirmed, '2026-10-14 11:00', $marketing);
        $range = ['from' => '2026-10-01', 'to' => '2026-11-01'];

        $read = fn (User $user, Schedule $schedule): array => collect($this->actingAs($user)
            ->get(route($user->isClient() ? 'profile.schedule' : 'schedule.index', $range))
            ->viewData('page')['props']['schedules'])->firstWhere('id', $schedule->id);
        $can = fn (string ...$allowed): array => collect(['update', 'cancel', 'assign', 'confirm', 'decline', 'serve', 'noShow'])
            ->mapWithKeys(fn (string $action): array => [$action => in_array($action, $allowed, true)])->all();

        expect($read($client, $pending)['can'])->toBe($can('update', 'cancel'))
            ->and($read($client, $waiting)['can'])->toBe($can('update', 'cancel'))
            ->and($read($marketing, $pending)['can'])->toBe($can('update', 'cancel', 'assign'))
            ->and($read($marketing, $waiting)['can'])->toBe($can('update', 'cancel', 'assign'))
            ->and($read($sales, $waiting)['can'])->toBe($can('confirm', 'decline'))
            ->and($read($sales, $pastConfirmed)['can'])->toBe($can('serve', 'noShow'))
            // Only its sales member says how the visit went.
            ->and($read($marketing, $pastConfirmed)['can'])->toBe($can());

        // Sales does not read a booking nobody has assigned to it.
        $salesIds = array_column($this->actingAs($sales)->get(route('schedule.index', $range))->viewData('page')['props']['schedules'], 'id');
        expect($salesIds)->not->toContain($pending->id);
    });

    it('shows a client "pending" while their booking awaits sales, and staff who it awaits', function (): void {
        $client = inquiryClient();
        $sales = inquiryClient(Role::Sales);
        $sales->update(['name' => 'Sam Sales']);
        $waiting = assignedAppointment($client, $sales);
        $range = ['from' => '2026-10-01', 'to' => '2026-11-01'];

        $clientView = collect($this->actingAs($client)->get(route('profile.schedule', $range))->viewData('page')['props']['schedules'])->firstWhere('id', $waiting->id);
        $staffView = collect($this->actingAs($waiting->assignedBy)->get(route('schedule.index', $range))->viewData('page')['props']['schedules'])->firstWhere('id', $waiting->id);

        expect($clientView['status'])->toBe('pending')
            ->and($clientView['assignedSalesName'])->toBeNull()
            ->and($staffView['status'])->toBe('awaiting_sales')
            ->and($staffView['assignedSalesName'])->toBe('Sam Sales');
    });
});

describe('the update page', function (): void {
    it('opens the Book an appointment page filled in with the appointment, for its client and for marketing', function (): void {
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

        $this->actingAs(inquiryClient(Role::Marketing))->get(route('schedule.edit', $schedule))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('schedule.clientName', 'Rafa Domingo')
                ->where('earliestBookableDate', '2026-10-14'));
    });

    it('refuses the page to another client and to the roles that cannot update', function (Role $role): void {
        $schedule = appointment(inquiryClient());

        $this->actingAs(inquiryClient())->get(route('profile.schedule.edit', $schedule))->assertNotFound();
        $this->actingAs(inquiryClient($role))->get(route('schedule.edit', $schedule))->assertForbidden();
    })->with([Role::Sales, Role::Technical]);

    it('sends the reader back with the reason when the appointment can no longer be updated', function (): void {
        $client = inquiryClient();
        $schedule = appointment($client, ScheduleStatus::Served);

        $this->actingAs($client)->get(route('profile.schedule.edit', $schedule))
            ->assertRedirect(route('profile.schedule'));
    });
});
