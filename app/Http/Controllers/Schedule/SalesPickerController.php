<?php

namespace App\Http\Controllers\Schedule;

use App\Http\Controllers\Controller;
use App\Http\Requests\Schedule\SalesPickerRequest;
use App\Models\User;
use App\Services\ScheduleService;
use Illuminate\Http\JsonResponse;

/**
 * The sales members marketing may assign an appointment to, from Book an
 * appointment and from an appointment's Assign dialog: id, name, and how many
 * appointments are still to happen with them.
 */
class SalesPickerController extends Controller
{
    public function __construct(
        public readonly ScheduleService $scheduleService,
    ) {}

    public function index(SalesPickerRequest $request): JsonResponse
    {
        return response()->json([
            'sales' => $this->scheduleService->assignableSales($request->search())
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'openAppointmentCount' => (int) $user->getAttribute('open_appointment_count'),
                ])
                ->values()
                ->all(),
        ]);
    }
}
