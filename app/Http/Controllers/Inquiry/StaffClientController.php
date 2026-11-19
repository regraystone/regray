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
     * Take down a direct client. When one of the contacts is already another
     * client's, nothing is created and that client is sent back with
     * `existing` true (200), for staff to use instead; a new one is 201.
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

    /** @return array{id: int, fullName: string, kind: string} */
    private function summary(Client $client): array
    {
        return [
            'id' => $client->id,
            'fullName' => $client->full_name,
            'kind' => $client->kind->value,
        ];
    }
}
