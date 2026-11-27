<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Profile\UpdateClientPhotoAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfilePhotoRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * A client's profile photo, set from the avatar on their Profile page and
 * shown wherever they appear on the platform (User::avatarUrl()).
 */
class ProfilePhotoController extends Controller
{
    public function update(ProfilePhotoRequest $request, UpdateClientPhotoAction $updatePhoto): RedirectResponse
    {
        $updatePhoto->execute($request->user(), $request->file('photo'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile photo updated.')]);

        return back();
    }

    public function destroy(Request $request, UpdateClientPhotoAction $updatePhoto): RedirectResponse
    {
        abort_unless($request->user()->isClient(), 403);

        $updatePhoto->execute($request->user(), null);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile photo removed.')]);

        return back();
    }
}
