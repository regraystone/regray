<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\SaveClientAddressRequest;
use App\Http\Resources\ClientAddressResource;
use App\Models\ClientAddress;
use App\Models\User;
use App\Repositories\Contracts\ClientAddressRepositoryInterface;
use App\Services\ClientAddressService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The client's address book under the Profile hub (Addresses): up to five
 * addresses, one of them the default the Inquire page starts from.
 *
 * Every address is looked up among the signed-in user's own, so an id that is
 * another account's is a 404 for anyone; ClientAddressPolicy then says the
 * same. Each change goes back to the page with a toast.
 *
 * `?from=inquire` is how the Inquire page sends a client here to add or change
 * an address: the page then offers the way back to their request, which the
 * Inquire page restores from its saved draft.
 */
class ClientAddressController extends Controller
{
    public function __construct(
        public readonly ClientAddressRepositoryInterface $addresses,
        public readonly ClientAddressService $addressBook,
    ) {}

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('profile/addresses', [
            'addresses' => ClientAddressResource::collection($this->addresses->forUser($user))->resolve($request),
            'limit' => ClientAddress::LIMIT,
            'quickLabels' => ClientAddress::QUICK_LABELS,
            'returnTo' => $request->query('from') === 'inquire' ? route('quote.inquire', absolute: false) : null,
        ]);
    }

    public function store(SaveClientAddressRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->addressBook->add($user, $request->label(), $request->address(), $request->ip(), $request->userAgent());

        return $this->saved($request, __('Address added.'));
    }

    public function update(SaveClientAddressRequest $request, int $address): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $saved = $this->own($user, $address);

        Gate::authorize('update', $saved);

        $this->addressBook->update($user, $saved, $request->label(), $request->address(), $request->ip(), $request->userAgent());

        return $this->saved($request, __('Address saved.'));
    }

    public function makeDefault(Request $request, int $address): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $saved = $this->own($user, $address);

        Gate::authorize('update', $saved);

        $this->addressBook->makeDefault($user, $saved, $request->ip(), $request->userAgent());

        return $this->saved($request, __('Default address changed.'));
    }

    public function destroy(Request $request, int $address): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $saved = $this->own($user, $address);

        Gate::authorize('delete', $saved);

        $this->addressBook->remove($user, $saved, $request->ip(), $request->userAgent());

        return $this->saved($request, __('Address deleted.'));
    }

    private function own(User $user, int $id): ClientAddress
    {
        return $this->addresses->findForUser($user, $id) ?? abort(404);
    }

    /** Back to the address book, keeping the way back to the Inquire page. */
    private function saved(Request $request, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('profile.addresses.index', $request->query('from') === 'inquire' ? ['from' => 'inquire'] : []);
    }
}
