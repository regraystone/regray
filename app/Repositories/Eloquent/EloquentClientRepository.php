<?php

namespace App\Repositories\Eloquent;

use App\Enums\ClientKind;
use App\Enums\Role;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\User;
use App\Repositories\Contracts\ClientRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class EloquentClientRepository implements ClientRepositoryInterface
{
    public function find(int $id): ?Client
    {
        return Client::query()->find($id);
    }

    public function findForUser(User $user): ?Client
    {
        return Client::query()->whereBelongsTo($user)->first();
    }

    public function createForUser(User $user): Client
    {
        return Client::query()->create([
            'kind' => ClientKind::Platform,
            'user_id' => $user->id,
            'full_name' => $user->name,
        ]);
    }

    public function createDirect(array $attributes, array $contacts, ?int $createdBy): Client
    {
        $client = Client::query()->create([
            ...$attributes,
            'kind' => ClientKind::Direct,
            'user_id' => null,
            'created_by' => $createdBy,
        ]);

        foreach ($contacts as $contact) {
            $client->contacts()->create(['kind' => $contact['kind'], 'value' => $contact['value']]);
        }

        return $client;
    }

    public function findByContacts(array $contacts): ?array
    {
        if ($contacts === []) {
            return null;
        }

        $match = ClientContact::query()
            ->where(function (Builder $query) use ($contacts): void {
                foreach ($contacts as $contact) {
                    $query->orWhere(fn (Builder $one) => $one->where('kind', $contact['kind']->value)->where('value', $contact['value']));
                }
            })
            ->with('client')
            ->orderBy('id')
            ->first();

        return $match === null ? null : ['client' => $match->client, 'kind' => $match->kind, 'value' => $match->value];
    }

    public function searchAccounts(string $escapedSearch, int $limit): Collection
    {
        $pattern = "%{$escapedSearch}%";

        return User::query()
            ->whereHas('roles', fn (Builder $role) => $role->where('name', Role::Client->value))
            ->when($escapedSearch !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('name', 'ILIKE', $pattern)
                ->orWhere('email', 'ILIKE', $pattern)))
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'email']);
    }
}
