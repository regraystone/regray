<?php

namespace App\Http\Controllers\Inquiry;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inquiry\StoreDirectClientRequest;
use App\Http\Requests\Message\SearchPeopleRequest;
use App\Models\Client;
use App\Models\User;
use App\Services\ClientService;
use Illuminate\Http\JsonResponse;

/**
 * Who staff open an inquiry for, before the stepper: a client account to pick
 * (Platform), or a walk-in to take down (Direct). Guarded like the staff
 * stepper, so a client never reaches either.
 */
class StaffClientController extends Controller
{
    public function __construct(
        public readonly ClientService $clientService,
    ) {}

    /**
     * The client accounts the user may find (User::scopeClientsVisibleTo())
     * matching a search: id, name, email, and the `label` a result is shown
     * by (User::searchLabel()).
     */
    public function accounts(SearchPeopleRequest $request): JsonResponse
    {
        $search = $request->search();

        return response()->json([
            'accounts' => $this->clientService->searchAccounts($request->user(), $search)
                ->map(fn (User $user): array => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'label' => $user->searchLabel($search)])
                ->values()
                ->all(),
        ]);
    }

    /**
     * Take down a direct client. When one of the contacts is already a
     * walk-in's on file, nothing is created and that record is sent back with
     * `existing` true (200), and the new inquiry goes ahead on it; a new one
     * is 201. Whoever takes the walk-in down sees the record (its id, name and
     * kind) and, through the inquiry they open on it, its name, phone and
     * address; never the other inquiries on it (Inquiry::scopeVisibleTo()).
     */
    public function storeDirect(StoreDirectClientRequest $request): JsonResponse
    {
        $result = $this->clientService->createDirect($request->clientInput(), $request->user());

        return response()->json([
            'client' => $this->summary($result['client']),
            'existing' => $result['existing'],
            'matched' => $result['matched'],
        ], $result['existing'] ? 200 : 201);
    }

    /**
     * The record as the Inquire page names it. `company` is always null: a
     * client record keeps no company (`clients` has no such column).
     *
     * @return array{id: int, name: string, fullName: string, company: null, kind: string}
     */
    private function summary(Client $client): array
    {
        return [
            'id' => $client->id,
            'name' => $client->full_name,
            'fullName' => $client->full_name,
            'company' => null,
            'kind' => $client->kind->value,
        ];
    }
}
