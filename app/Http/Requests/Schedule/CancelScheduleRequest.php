<?php

namespace App\Http\Requests\Schedule;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Cancel an appointment: why it is being cancelled. Mirrors
 * `cancelScheduleSchema` in resources/js/lib/booking-schema.ts.
 */
class CancelScheduleRequest extends FormRequest
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
        return $this->reasonMessages('Say why the appointment is being cancelled');
    }
}
