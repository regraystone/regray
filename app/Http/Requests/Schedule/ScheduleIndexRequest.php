<?php

namespace App\Http\Requests\Schedule;

use App\Services\ScheduleService;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * The span of time the Schedule page asks for: `from` up to, not including,
 * `to`. ISO 8601 dates or datetimes; a datetime may carry its own offset.
 *
 * Nothing here says whose visits are read. That comes from the signed-in user.
 */
class ScheduleIndexRequest extends FormRequest
{
    /** The route middleware decides access before this request is resolved. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'required_with:to', 'date'],
            'to' => ['nullable', 'required_with:from', 'date', 'after:from'],
        ];
    }

    /**
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty() || ! $this->filled('from')) {
                    return;
                }

                $maxDays = (int) config('schedule.max_range_days');
                $range = $this->range();

                if ($range['from']->diffInSeconds($range['to']) > $maxDays * 86_400) {
                    $validator->errors()->add('to', "A range can cover {$maxDays} days at most.");
                }
            },
        ];
    }

    /**
     * The range asked for, or by default the six weeks the mini calendar shows
     * for this month in the studio's zone, which holds the current week too.
     *
     * A date without an offset is read in the studio's zone. Both ends come
     * back in the app's zone, ready to bind in a query.
     *
     * @return array{from: CarbonImmutable, to: CarbonImmutable}
     */
    public function range(): array
    {
        if ($this->filled('from')) {
            $from = CarbonImmutable::parse((string) $this->input('from'), ScheduleService::timezone());
            $to = CarbonImmutable::parse((string) $this->input('to'), ScheduleService::timezone());
        } else {
            $from = CarbonImmutable::now(ScheduleService::timezone())
                ->startOfMonth()
                ->startOfWeek(CarbonImmutable::MONDAY);
            $to = $from->addWeeks(6);
        }

        return [
            'from' => $from->setTimezone(config('app.timezone')),
            'to' => $to->setTimezone(config('app.timezone')),
        ];
    }
}
