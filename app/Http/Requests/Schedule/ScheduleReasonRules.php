<?php

namespace App\Http\Requests\Schedule;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The reason an appointment is updated or cancelled: required, 3 to 255
 * characters once trimmed (the length of `change_reason` and `cancel_reason`).
 * Mirrors `reasonSchema` in resources/js/lib/booking-schema.ts.
 *
 * @mixin FormRequest
 */
trait ScheduleReasonRules
{
    public const REASON_MIN = 3;

    public const REASON_MAX = 255;

    /** Trim the reason before it is checked, so spaces alone are no reason. */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('reason'))) {
            $this->merge(['reason' => trim($this->input('reason'))]);
        }
    }

    /** @return array<string, array<int, string>> */
    protected function reasonRules(): array
    {
        return ['reason' => ['required', 'string', 'min:'.self::REASON_MIN, 'max:'.self::REASON_MAX]];
    }

    /** @return array<string, string> */
    protected function reasonMessages(string $required): array
    {
        return [
            'reason.required' => $required,
            'reason.string' => $required,
            'reason.min' => 'Give a reason of at least '.self::REASON_MIN.' characters',
            'reason.max' => 'Keep the reason to '.self::REASON_MAX.' characters or fewer',
        ];
    }

    public function reason(): string
    {
        return $this->string('reason')->trim()->value();
    }
}
