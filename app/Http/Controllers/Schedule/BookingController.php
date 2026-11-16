<?php

namespace App\Http\Controllers\Schedule;

use App\Actions\Schedule\BookScheduleAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Schedule\StoreBookingRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Throwable;

class BookingController extends Controller
{
    public function __construct(
        public readonly BookScheduleAction $bookSchedule,
    ) {}

    /**
     * Save the booking the Book an appointment page built: a client's own,
     * pending, or one marketing made for the client it chose, awaiting the
     * sales member it is assigned to.
     *
     * Validation failures are the form request's and come back as Inertia
     * errors. Anything else is reported once and flashed as an error, and the
     * redirect leaves the page where it was so the form keeps its contents.
     */
    public function store(StoreBookingRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            if ($request->isStaffBooking()) {
                /** @var User $client after() refused a request naming no client account. */
                $client = $request->client();
                /** @var User $sales after() refused a request naming no sales member. */
                $sales = $request->sales();
                $this->bookSchedule->executeForStaff($user, $client, $sales, $request->purpose(), $request->arrivalAt(), $request->notes());
            } else {
                $this->bookSchedule->execute($user, $request->purpose(), $request->arrivalAt(), $request->notes());
            }
        } catch (Throwable $exception) {
            report($exception);

            Inertia::flash('booking', [
                'status' => 'error',
                'message' => __('We could not save the appointment. Your answers are still here, so you can try again.'),
            ]);

            return back();
        }

        Inertia::flash('booking', ['status' => 'success']);

        return back();
    }
}
