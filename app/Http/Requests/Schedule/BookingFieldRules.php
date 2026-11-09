<?php

namespace App\Http\Requests\Schedule;

use App\Services\ScheduleService;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The date, arrival time, purpose and notes of an appointment, as booking it
 * and updating it both ask for them. Mirrors `bookingFieldsSchema` in
 * resources/js/lib/booking-schema.ts, plus the time window the browser cannot
 * be trusted with. Dates and times are read in the studio's zone. A client
 * picks a day from tomorrow on, staff from today on; see
 * ScheduleService::earliestBookableDate().
 *
 * @mixin FormRequest
 */
trait BookingFieldRules
{
    /** Same as BOOKING_OPEN_HOUR, BOOKING_CLOSE_HOUR and SLOT_MINUTES in lib/booking-slots.ts. */
    public const OPEN_HOUR = 10;

    public const CLOSE_HOUR = 18;

    public const SLOT_MINUTES = 30;

    /** @return array<string, array<int, mixed>> */
    protected function bookingFieldRules(): array
    {
        $earliest = ScheduleService::earliestBookableDate($this->user())->toDateString();

        return [
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$earliest],
            'arrivalTime' => ['required', 'date_format:H:i', Rule::in(self::slots())],
            'purpose' => ['required', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    protected function bookingFieldMessages(): array
    {
        return [
            'date.required' => 'Choose a day',
            'date.date_format' => 'Choose a day',
            'date.after_or_equal' => $this->user()->isClient() ? 'Choose a day from tomorrow on' : 'Choose a day from today on',
            'arrivalTime.required' => 'Choose a time',
            'arrivalTime.date_format' => 'Choose a time',
            'arrivalTime.in' => 'Choose a time between 10:00 AM and 6:00 PM',
            'purpose.required' => 'Purpose is required',
            'purpose.max' => 'Purpose is too long',
            'notes.max' => 'Notes are too long',
        ];
    }

    /**
     * The arrival must still be ahead, as StoreInquiryRequest asks of a visit.
     *
     * @return Closure(Validator): void
     */
    protected function arrivalStillAhead(): Closure
    {
        return function (Validator $validator): void {
            if ($validator->errors()->hasAny(['date', 'arrivalTime'])) {
                return;
            }

            if ($this->arrivalAt()->lessThanOrEqualTo(Date::now())) {
                $validator->errors()->add('arrivalTime', 'That time has already passed');
            }
        };
    }

    /**
     * Every bookable arrival as `H:i`: from the opening hour to the closing
     * hour inclusive, every SLOT_MINUTES.
     *
     * @return list<string>
     */
    public static function slots(): array
    {
        $slots = [];

        for ($minutes = self::OPEN_HOUR * 60; $minutes <= self::CLOSE_HOUR * 60; $minutes += self::SLOT_MINUTES) {
            $slots[] = sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
        }

        return $slots;
    }

    /** When the client will arrive, in the studio's zone. */
    public function arrivalAt(): CarbonImmutable
    {
        return ScheduleService::arrivalAt($this->string('date')->value(), $this->string('arrivalTime')->value());
    }

    public function purpose(): string
    {
        return $this->string('purpose')->trim()->value();
    }

    public function notes(): ?string
    {
        $notes = $this->string('notes')->trim()->value();

        return $notes === '' ? null : $notes;
    }
}
