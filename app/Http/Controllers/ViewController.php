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

    public function quotation(Request $request): Response
    {
        return Inertia::render('quotation');
    }

    public function inquiry(Request $request): Response
    {
        return Inertia::render('inquiry');
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

    public function services(Request $request): Response
    {
        return Inertia::render('services');
    }

    public function accounts(Request $request): Response
    {
        return Inertia::render('accounts');
    }
}
