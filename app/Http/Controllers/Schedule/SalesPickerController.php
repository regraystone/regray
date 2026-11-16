<?php

namespace App\Http\Controllers\Schedule;

use App\Http\Controllers\Controller;
use App\Http\Requests\Schedule\SalesPickerRequest;
use App\Models\Schedule;
use App\Models\User;
use App\Services\ScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * The sales members marketing may assign an appointment to, from Book an
 * appointment and from an appointment's Assign dialog: id, name, how many
 * appointments are still to happen with them, and, given the appointment's
 * time, the appointment of theirs it would overlap (`clash`), a warning only:
 * marketing may still choose them.
 */
class SalesPickerController extends Controller
{
    public function __construct(
        public readonly ScheduleService $scheduleService,
    ) {}

    public function index(SalesPickerRequest $request): JsonResponse
    {
        $sales = $this->scheduleService->assignableSales($request->search());
        // The appointment being assigned, when it is one this reader reads: its time, unless another is given.
        $schedule = $request->scheduleId() === null ? null : Schedule::query()->visibleTo($request->user())->find($request->scheduleId());
        $arrivalAt = $request->arrivalAt() ?? $schedule?->arrival_at->toImmutable();
        $clashes = $arrivalAt === null ? [] : $this->scheduleService->salesClashes(array_values($sales->map(fn (User $user): int => $user->id)->all()), $arrivalAt, $schedule?->id);

        return response()->json([
            'sales' => $sales
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'openAppointmentCount' => (int) $user->getAttribute('open_appointment_count'),
                    'clash' => isset($clashes[$user->id]) ? self::clashOf($clashes[$user->id]) : null,
                ])
                ->values()
                ->all(),
        ]);
    }

    /**
     * For the sales member about to confirm `$schedule`: the appointment of
     * theirs it would overlap, if any, to warn them before they confirm. Only
     * who may confirm it now (SchedulePolicy::confirm()).
     */
    public function clash(Schedule $schedule): JsonResponse
    {
        Gate::authorize('confirm', $schedule);

        $salesId = (int) $schedule->assigned_to;
        $clashes = $this->scheduleService->salesClashes([$salesId], $schedule->arrival_at->toImmutable(), $schedule->id);

        return response()->json(['clash' => isset($clashes[$salesId]) ? self::clashOf($clashes[$salesId]) : null]);
    }

    /** @return array{arrivalAt: string, time: string, title: string} `time` is `H:i` in the studio's zone. */
    private static function clashOf(Schedule $schedule): array
    {
        $at = $schedule->arrival_at->toImmutable()->setTimezone(ScheduleService::timezone());

        return [
            'arrivalAt' => $at->toIso8601String(),
            'time' => $at->format('H:i'),
            'title' => $schedule->title,
        ];
    }
}
