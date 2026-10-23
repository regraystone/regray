<?php

namespace App\Http\Controllers\Schedule;

use App\Actions\Schedule\BookScheduleAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Schedule\StoreBookingRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Throwable;

class BookingController extends Controller
{
    public function __construct(
        public readonly BookScheduleAction $bookSchedule,
    ) {}

    /**
     * Save the booking the Book an appointment page built.
     *
     * Validation failures are the form request's and come back as Inertia
     * errors. Anything else is reported once and flashed as an error, and the
     * redirect leaves the page where it was so the form keeps its contents.
     */
    public function store(StoreBookingRequest $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->isClient()) {
            // TODO(staff-booking): staff book on a client's behalf. The page
            // will send the client's id; check it names a client, then:
            //
            // $client = User::query()->findOrFail($request->integer('clientId'));
            // $this->bookSchedule->executeForStaff($user, $client, $request->purpose(), $request->arrivalAt(), $request->notes());
            //
            // with booked_by = the staff member and booked_by_type = staff.
            return back();
        }

        try {
            $this->bookSchedule->execute($user, $request->purpose(), $request->arrivalAt(), $request->notes());
        } catch (Throwable $exception) {
            report($exception);

            Inertia::flash('booking', [
                'status' => 'error',
                'message' => __('We could not save your appointment. Your answers are still here, so you can try again.'),
            ]);

            return back();
        }

        Inertia::flash('booking', ['status' => 'success']);

        return back();
    }
}
