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
 * Mirrors `bookingSchema` in resources/js/lib/booking-schema.ts, plus the time
 * window the browser cannot be trusted with. Dates and times are read in the
 * studio's zone.
 *
 * Only these four fields are read. A client or user id in the payload is
 * ignored: the booking is always the signed-in client's.
 */
class StoreBookingRequest extends FormRequest
{
    /** Same as BOOKING_OPEN_HOUR, BOOKING_CLOSE_HOUR and SLOT_MINUTES in lib/booking-slots.ts. */
    public const OPEN_HOUR = 10;

    public const CLOSE_HOUR = 18;

    public const SLOT_MINUTES = 30;

    /** The route middleware decides access before this request is resolved. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $today = Date::now(ScheduleService::timezone())->toDateString();

        return [
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$today],
            'arrivalTime' => ['required', 'date_format:H:i', Rule::in(self::slots())],
            'purpose' => ['required', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'date.required' => 'Choose a day',
            'date.date_format' => 'Choose a day',
            'date.after_or_equal' => 'Choose a day from today on',
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
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['date', 'arrivalTime'])) {
                    return;
                }

                if ($this->arrivalAt()->lessThanOrEqualTo(Date::now())) {
                    $validator->errors()->add('arrivalTime', 'That time has already passed');
                }
            },
        ];
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
