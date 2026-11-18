<?php

namespace App\Http\Requests\Schedule;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Decline an appointment assigned to you: why, which marketing reads before
 * assigning it again. Mirrors `declineScheduleSchema` in
 * resources/js/lib/booking-schema.ts.
 */
class DeclineScheduleRequest extends FormRequest
{
    use ScheduleReasonRules;

    /** SchedulePolicy decides access in the controller. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return $this->reasonRules();
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return $this->reasonMessages('Say why you are declining the appointment');
    }
}
