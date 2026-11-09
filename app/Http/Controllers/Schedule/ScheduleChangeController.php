<?php

namespace App\Http\Controllers\Schedule;

use App\Actions\Schedule\ApproveScheduleAction;
use App\Actions\Schedule\CancelScheduleAction;
use App\Actions\Schedule\NoShowScheduleAction;
use App\Actions\Schedule\ServeScheduleAction;
use App\Actions\Schedule\UpdateScheduleAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Schedule\CancelScheduleRequest;
use App\Http\Requests\Schedule\UpdateScheduleRequest;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Changing an appointment from the Schedule page, the staff one and the
 * client's Profile one alike: update, cancel, approve, served, did not attend.
 *
 * The route checks the permission and SchedulePolicy who may act on this
 * appointment (a client only on their own, and only to update or cancel). A
 * change its state no longer allows is refused by ScheduleChangeService as a
 * `schedule` validation error, which the dialog shows. Success is flashed as
 * `schedule` for the page's toast.
 */
class ScheduleChangeController extends Controller
{
    public function update(UpdateScheduleRequest $request, Schedule $schedule, UpdateScheduleAction $updateSchedule): RedirectResponse
    {
        Gate::authorize('update', $schedule);

        $updateSchedule->execute($schedule, $this->actor($request), $request->purpose(), $request->arrivalAt(), $request->notes(), $request->reason(), $request->ip(), $request->userAgent());

        return $this->done(__('Appointment updated'));
    }

    public function cancel(CancelScheduleRequest $request, Schedule $schedule, CancelScheduleAction $cancelSchedule): RedirectResponse
    {
        Gate::authorize('cancel', $schedule);

        $cancelSchedule->execute($schedule, $this->actor($request), $request->reason(), $request->ip(), $request->userAgent());

        return $this->done(__('Appointment cancelled'));
    }

    public function approve(Request $request, Schedule $schedule, ApproveScheduleAction $approveSchedule): RedirectResponse
    {
        Gate::authorize('approve', $schedule);

        $approveSchedule->execute($schedule, $this->actor($request), $request->ip(), $request->userAgent());

        return $this->done(__('Appointment approved'));
    }

    public function serve(Request $request, Schedule $schedule, ServeScheduleAction $serveSchedule): RedirectResponse
    {
        Gate::authorize('serve', $schedule);

        $serveSchedule->execute($schedule, $this->actor($request), $request->ip(), $request->userAgent());

        return $this->done(__('Marked as served'));
    }

    public function noShow(Request $request, Schedule $schedule, NoShowScheduleAction $noShowSchedule): RedirectResponse
    {
        Gate::authorize('noShow', $schedule);

        $noShowSchedule->execute($schedule, $this->actor($request), $request->ip(), $request->userAgent());

        return $this->done(__('Marked as not attended'));
    }

    private function actor(Request $request): User
    {
        /** @var User */
        return $request->user();
    }

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('schedule', ['status' => 'success', 'message' => $message]);

        return back();
    }
}
