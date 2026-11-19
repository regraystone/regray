<?php

namespace App\Http\Controllers\Inquiry;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inquiry\AssignInquiryHandlerRequest;
use App\Http\Requests\Lead\UpdateLeadStatusRequest;
use App\Repositories\Contracts\InquiryRepositoryInterface;
use App\Services\InquiryStatusService;
use App\Services\WalkInInquiryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * What staff do to a walk-in's inquiry from its details page: add its staff,
 * and marketing's decision on it.
 *
 * The reference stays a plain string, looked up through
 * Inquiry::scopeVisibleTo(), so an inquiry the reader does not read, or one
 * that is not a walk-in's, is a 404 before anything is refused.
 */
class WalkInInquiryController extends Controller
{
    public function __construct(
        public readonly WalkInInquiryService $walkIns,
        public readonly InquiryRepositoryInterface $inquiries,
        public readonly InquiryStatusService $inquiryStatusService,
    ) {}

    /**
     * Add one staff member: technical by the owner or the marketing member who
     * opened it, one accounting and one logistics member by the owner once
     * sales has won it (WalkInInquiryService::assign()).
     */
    public function assign(AssignInquiryHandlerRequest $request, string $reference): RedirectResponse
    {
        $member = DB::transaction(fn () => $this->walkIns->assign($request->user(), $reference, $request->userId()));

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$member->name} assigned to {$reference}."]);

        return back();
    }

    /** Marketing's decision, won or lost, by the marketing member who opened it. */
    public function decide(UpdateLeadStatusRequest $request, string $reference): RedirectResponse
    {
        $status = $request->to();

        DB::transaction(function () use ($request, $reference, $status): void {
            $inquiry = $this->inquiries->findVisibleByReferenceForUpdate($request->user(), $reference);

            if ($inquiry === null || $inquiry->owner_user_id === null) {
                abort(404);
            }

            Gate::authorize('decideWalkIn', $inquiry);

            $this->inquiryStatusService->decide($inquiry, $status, $request->user(), $request->note());
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$reference} marked {$status->value}."]);

        return back();
    }
}
