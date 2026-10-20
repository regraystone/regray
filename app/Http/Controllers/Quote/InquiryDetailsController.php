<?php

namespace App\Http\Controllers\Quote;

use App\Actions\Quote\ShowInquiryDetailsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Quote\ShowInquiryDetailsRequest;
use App\Http\Resources\InquiryDetailsResource;
use Inertia\Inertia;
use Inertia\Response;

class InquiryDetailsController extends Controller
{
    public function __construct(
        public readonly ShowInquiryDetailsAction $showInquiryDetails,
    ) {}

    /**
     * One inquiry, read only, loaded by its reference and scoped to the signed-in
     * user. Both `quote.show` and `inquiry.show` land here.
     *
     * TODO: leave the parameter a plain `{reference}` string and never type-hint
     * `Inquiry` on this method. SubstituteBindings is prioritized ahead of the
     * permission middleware, so a bound model would 404 before authorizing and
     * let a reader who may not open an inquiry tell which references exist. The
     * 404 comes from the scoped lookup (`whereBelongsTo` the user), never from
     * the route.
     *
     * TODO: `inquiry.show` is the staff list's twin of this route and is scoped
     * to the signed-in user as well, which is right for a client and wrong for
     * staff reading someone else's inquiry. Staff scoping (handlers, assignment)
     * is a separate change; until it lands, staff see only inquiries they sent.
     *
     * TODO: before this reaches staff, add `resources/js/pages/not-found.tsx` and
     * a 404 handler beside the 403 one in bootstrap/app.php. Today a 404 renders
     * Laravel's plain "Not Found" page instead of the Inertia UI.
     */
    public function show(ShowInquiryDetailsRequest $request): Response
    {
        $result = $this->showInquiryDetails->execute($request->user(), $request->validated('reference'));

        return Inertia::render('inquiry-details', [
            'inquiry' => InquiryDetailsResource::make($result['inquiry'])->withConversation($result['conversation'])->resolve($request),
        ]);
    }
}
