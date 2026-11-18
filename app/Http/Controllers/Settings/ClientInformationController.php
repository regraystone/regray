<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Settings\SaveClientInformationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateClientInformationRequest;
use App\Http\Resources\ClientInformationResource;
use App\Models\User;
use App\Services\ClientInformationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The client's own "Client Information" page under the Profile hub.
 *
 * Every read and write starts from the signed-in user: no id appears on the
 * page, in the URL, or in the payload. The page is rendered with the saved
 * delivery choice or, before one exists, delivery with no site as its start.
 */
class ClientInformationController extends Controller
{
    public function __construct(
        public readonly ClientInformationService $clientInformation,
        public readonly SaveClientInformationAction $saveClientInformation,
    ) {}

    public function edit(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('profile/client-information', [
            'information' => ClientInformationResource::make($this->clientInformation->prefillFor($user))->resolve($request),
            'locations' => (array) config('inquiry.locations'),
        ]);
    }

    public function update(UpdateClientInformationRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->saveClientInformation->execute(
            $user,
            $request->informationInput(),
            $request->ip(),
            $request->userAgent(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Client Information saved.')]);

        return to_route('profile.client-information.edit');
    }
}
