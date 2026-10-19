<?php

namespace App\Http\Controllers;

use App\Actions\Inquiry\ListInquiryOptionsAction;
use App\Http\Resources\InquiryFinishingResource;
use App\Http\Resources\InquiryOptionResource;
use App\Http\Resources\InquiryServiceResource;
use App\Http\Resources\InquiryStoneResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ViewController extends Controller
{
    public function overview(Request $request): Response
    {
        return Inertia::render('overview');
    }

    public function saved(Request $request): Response
    {
        return Inertia::render('saved');
    }

    public function sales(Request $request): Response
    {
        return Inertia::render('sales');
    }

    public function quote(Request $request): Response
    {
        return Inertia::render('quote');
    }

    public function inquiry(Request $request): Response
    {
        return Inertia::render('inquiry');
    }

    /**
     * The Inquire page and everything it offers: the services, the stones, the
     * applications, the edge profiles and finishes still in use, and the
     * locations. All of it is read here rather than imported by the page, so
     * the flow and the validation behind it start from the same rows.
     */
    public function inquire(Request $request, ListInquiryOptionsAction $listInquiryOptions): Response
    {
        $options = $listInquiryOptions->execute();

        return Inertia::render('inquire', [
            'services' => InquiryServiceResource::collection($options['services'])->resolve($request),
            'stones' => InquiryStoneResource::collection($options['stones'])->resolve($request),
            'applications' => InquiryOptionResource::collection($options['applications'])->resolve($request),
            'edgeProfiles' => InquiryFinishingResource::collection($options['edgeProfiles'])->resolve($request),
            'finishes' => InquiryFinishingResource::collection($options['finishes'])->resolve($request),
            'locations' => $options['locations'],
        ]);
    }

    /**
     * One inquiry, read only.
     *
     * The record itself is mock data that lives in the front end, so the route
     * only carries the id. Once inquiries are persisted this is where the record
     * is loaded, and where it is scoped to its owner: a client may only open
     * their own, whatever the permission said.
     *
     * TODO: load the record here rather than through route model binding, and
     * leave the parameter typed `string $inquiry`. SubstituteBindings is
     * prioritized ahead of the permission middleware, so an `Inquiry` type-hint
     * would 404 before authorizing and let a reader who may not open an inquiry
     * tell which ids exist.
     *
     * TODO: the lookup is
     * `Inquiry::query()->whereBelongsTo($request->user())->findOrFail($inquiry)`,
     * scoped to the current user so permission is settled before existence. The
     * 404 comes from findOrFail, never from the route. Both routes land here, so
     * this applies to `quote.show` and `inquiry.show` alike.
     *
     * TODO: before any of that ships, add `resources/js/pages/not-found.tsx` and
     * a 404 handler beside the 403 one in bootstrap/app.php. Today a 404 renders
     * Laravel's plain "Not Found" page instead of the Inertia UI.
     */
    public function inquiryDetails(Request $request, string $inquiry): Response
    {
        return Inertia::render('inquiry-details', ['inquiryId' => $inquiry]);
    }

    public function schedule(Request $request): Response
    {
        return Inertia::render('schedule');
    }

    public function scheduleBook(Request $request): Response
    {
        return Inertia::render('schedule-book');
    }

    public function message(Request $request): Response
    {
        return Inertia::render('message');
    }

    public function stones(Request $request): Response
    {
        return Inertia::render('stones');
    }

    public function services(Request $request): Response
    {
        return Inertia::render('services');
    }

    public function accounts(Request $request): Response
    {
        return Inertia::render('accounts');
    }

    public function projects(Request $request): Response
    {
        return Inertia::render('projects');
    }
}
