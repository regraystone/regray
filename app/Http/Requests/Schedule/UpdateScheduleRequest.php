<?php

namespace App\Http\Requests\Schedule;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Update an appointment: the same date, arrival time, purpose and notes as
 * booking it (BookingFieldRules, so a client still picks from tomorrow on and
 * staff from today on), and why it is being updated. Mirrors
 * `updateScheduleSchema` in resources/js/lib/booking-schema.ts.
 */
class UpdateScheduleRequest extends FormRequest
{
    use BookingFieldRules;
    use ScheduleReasonRules;

    /** SchedulePolicy decides access in the controller. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [...$this->bookingFieldRules(), ...$this->reasonRules()];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [...$this->bookingFieldMessages(), ...$this->reasonMessages('Say why the appointment is being updated')];
    }

    /** @return list<Closure(Validator): void> */
    public function after(): array
    {
        return [$this->arrivalStillAhead()];
    }
}
