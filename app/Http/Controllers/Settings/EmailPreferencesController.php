<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\EmailPreferencesUpdateRequest;
use App\Services\ConsentService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class EmailPreferencesController extends Controller
{
    public function __construct(private readonly ConsentService $consents) {}

    /**
     * Turn promotional emails and announcements on or off. Emails about the
     * user's own account, inquiries and visits are not affected.
     */
    public function update(EmailPreferencesUpdateRequest $request): RedirectResponse
    {
        $this->consents->setMarketingEmails($request->user(), $request->boolean('marketing_emails'), $request);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Email preferences saved.')]);

        return to_route('profile.edit');
    }
}
