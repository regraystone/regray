<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ViewController extends Controller
{
    public function home(Request $request): Response
    {
        return Inertia::render('home');
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
     */
    public function inquiryDetails(Request $request, string $inquiry): Response
    {
        return Inertia::render('inquiry-details', ['inquiryId' => $inquiry]);
    }

    public function schedule(Request $request): Response
    {
        return Inertia::render('schedule');
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
