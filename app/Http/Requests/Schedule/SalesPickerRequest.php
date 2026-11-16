<?php

namespace App\Http\Requests\Schedule;

use App\Services\ScheduleService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The sales picker's search, and the time the appointment is for, to warn of
 * a sales member already booked then: `date` (`Y-m-d`) and `time` (`H:i`), in
 * the studio's zone, both or neither. `scheduleId` is the appointment being
 * assigned, left out of the clashes. The route middleware decides access.
 */
class SalesPickerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'date' => ['nullable', 'date_format:Y-m-d', 'required_with:time'],
            'time' => ['nullable', 'date_format:H:i', 'required_with:date'],
            'scheduleId' => ['nullable', 'integer'],
        ];
    }

    public function search(): ?string
    {
        $search = $this->validated('search');

        return is_string($search) ? $search : null;
    }

    /** The appointment's time, or null when none was given. */
    public function arrivalAt(): ?CarbonImmutable
    {
        $date = $this->validated('date');
        $time = $this->validated('time');

        return is_string($date) && is_string($time) ? ScheduleService::arrivalAt($date, $time) : null;
    }

    public function scheduleId(): ?int
    {
        $id = $this->validated('scheduleId');

        return $id === null ? null : (int) $id;
    }
}
