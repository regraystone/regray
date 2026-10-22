<?php

namespace App\Http\Controllers;

use App\Actions\Inquiry\ListInquiryOptionsAction;
use App\Actions\Schedule\ListSchedulesAction;
use App\Http\Requests\Schedule\ScheduleIndexRequest;
use App\Http\Resources\InquiryFinishingResource;
use App\Http\Resources\InquiryOptionResource;
use App\Http\Resources\InquiryServiceResource;
use App\Http\Resources\InquiryStoneResource;
use App\Http\Resources\ScheduleResource;
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
     * The Schedule page, at /schedule and /profile/schedule alike.
     *
     * Both props are closures, so a partial reload that asks only for
     * `schedules` (the page stepping through weeks) does not read `upcoming`.
     */
    public function schedule(ScheduleIndexRequest $request, ListSchedulesAction $listSchedules): Response
    {
        $viewer = $request->user();
        $range = $request->range();

        return Inertia::render('schedule', [
            'schedules' => fn (): array => ScheduleResource::collection(
                $listSchedules->inRange($viewer, $range['from'], $range['to']),
            )->resolve($request),
            'upcoming' => fn (): array => ScheduleResource::collection(
                $listSchedules->upcoming($viewer),
            )->resolve($request),
        ]);
    }

    public function scheduleBook(Request $request): Response
    {
        return Inertia::render('schedule-book');
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
