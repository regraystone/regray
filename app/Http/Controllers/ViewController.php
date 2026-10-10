<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ViewController extends Controller
{
    /**
     * One project, read only.
     *
     * The project is mock data in the front end, so the route only carries the
     * id. The parameter stays a plain string because permission has to be
     * settled before existence is — see the TODO on inquiryDetails, which is
     * written the way it is for the same reason.
     *
     * TODO: an unknown project, and a stone that is not one of that project's
     * stones, both render their page's own not-found block today rather than a
     * 404, exactly as an unknown stone id does. Give both a 404 once the
     * handler in front of the 403 exists, and keep the permission in front of
     * it so a reader who may not open a project cannot probe which ids exist.
     */
    public function project(Request $request, string $project): Response
    {
        return Inertia::render('project-profile', ['projectId' => $project]);
    }

    /**
     * One of a project's stones, read only.
     *
     * The stone is read by its place in the project rather than on its own, so
     * both ids travel as plain strings for the reason given on `project`
     * above. `saved.show` lands on `stoneProfile` instead, where the stone
     * stands alone and no project is in the address.
     */
    public function homeStone(Request $request, string $project, string $stone): Response
    {
        return Inertia::render('stone-profile', ['projectId' => $project, 'stoneId' => $stone]);
    }

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

    public function inquire(Request $request): Response
    {
        return Inertia::render('inquire');
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

    public function agenda(Request $request): Response
    {
        return Inertia::render('agenda');
    }

    public function agendaBook(Request $request): Response
    {
        return Inertia::render('agenda-book');
    }

    public function message(Request $request): Response
    {
        return Inertia::render('message');
    }

    public function stones(Request $request): Response
    {
        return Inertia::render('stones');
    }

    public function stoneProfile(Request $request, string $stone): Response
    {
        return Inertia::render('stone-profile', ['stoneId' => $stone]);
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
