<?php

namespace App\Http\Controllers\Inquiry;

use App\Actions\Inquiry\CreateInquiryAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inquiry\StoreInquiryRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Throwable;

class InquiryController extends Controller
{
    public function __construct(
        public readonly CreateInquiryAction $createInquiry,
    ) {}

    /**
     * Send the inquiry the Inquire page built.
     *
     * Validation failures are the form request's and come back as Inertia
     * errors. Anything else is reported once and flashed as an error, and the
     * redirect leaves the page where it was so the form keeps its contents.
     */
    public function store(StoreInquiryRequest $request): RedirectResponse
    {
        $input = $request->inquiryInput();

        try {
            $inquiry = $this->createInquiry->execute($request->user(), $input);
        } catch (Throwable $exception) {
            report($exception);

            Inertia::flash('inquiry', [
                'status' => 'error',
                'message' => __('We could not save your inquiry. Your answers are still here, so you can try again.'),
            ]);

            return back();
        }

        Inertia::flash('inquiry', [
            'status' => 'success',
            'title' => $input['projects'][0]['name'],
            'reference' => $inquiry->reference,
        ]);

        return back();
    }
}
