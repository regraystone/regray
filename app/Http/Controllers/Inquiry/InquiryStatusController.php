<?php

namespace App\Http\Controllers\Inquiry;

use App\Actions\Inquiry\UpdateInquiryStatusAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inquiry\UpdateInquiryStatusRequest;
use App\Http\Requests\Inquiry\UploadJobOrderRequest;
use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class InquiryStatusController extends Controller
{
    public function __construct(
        public readonly UpdateInquiryStatusAction $updateStatus,
    ) {}

    /**
     * Move one of an inquiry's status tracks, from the Update Status dialog.
     * InquiryPolicy says whether this user may move this track; the service
     * checks the move and what it carries, again, whatever the dialog allowed.
     *
     * The reference stays a plain string, so the permission is checked before
     * anything says whether the inquiry exists. An inquiry this user does not
     * read (Inquiry::scopeVisibleTo()) is the same 404 as one that does not.
     */
    public function store(UpdateInquiryStatusRequest $request, string $reference): RedirectResponse
    {
        $inquiry = $this->visibleInquiry($request, $reference);
        $track = $request->track();

        Gate::authorize('updateStatus', [$inquiry, $track]);

        $this->updateStatus->execute($request->user(), $reference, $track, $request->to(), $request->note(), $request->uploads(), $request->amount());

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$reference} updated."]);

        return back();
    }

    /**
     * Upload the job order after the quote, from its own row action: it
     * starts logistics at order_received.
     */
    public function jobOrder(UploadJobOrderRequest $request, string $reference): RedirectResponse
    {
        $inquiry = $this->visibleInquiry($request, $reference);

        Gate::authorize('uploadJobOrder', $inquiry);

        $this->updateStatus->jobOrder($request->user(), $reference, $request->note(), $request->jobOrders());

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$reference}: job order in, logistics has it."]);

        return back();
    }

    private function visibleInquiry(Request $request, string $reference): Inquiry
    {
        /** @var User $user */
        $user = $request->user();

        return Inquiry::query()->visibleTo($user)->where('reference', $reference)->first() ?? abort(404);
    }
}
