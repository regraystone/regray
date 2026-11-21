<?php

namespace App\Repositories\Eloquent;

use App\Models\ClientAddress;
use App\Models\User;
use App\Repositories\Contracts\ClientAddressRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class EloquentClientAddressRepository implements ClientAddressRepositoryInterface
{
    public function forUser(User $user): Collection
    {
        return $user->addresses()->get();
    }

    public function findForUser(User $user, int $id): ?ClientAddress
    {
        return ClientAddress::query()->where('user_id', $user->id)->whereKey($id)->first();
    }

    public function lockBook(User $user): void
    {
        User::query()->whereKey($user->id)->lockForUpdate()->value('id');
    }

    public function countForUser(User $user): int
    {
        return ClientAddress::query()->where('user_id', $user->id)->count();
    }

    public function create(User $user, array $attributes): ClientAddress
    {
        return ClientAddress::query()->create([...$attributes, 'user_id' => $user->id]);
    }

    public function update(ClientAddress $address, array $attributes): ClientAddress
    {
        $address->fill($attributes)->save();

        return $address;
    }

    public function delete(ClientAddress $address): void
    {
        $address->delete();
    }

    public function makeDefault(ClientAddress $address): void
    {
        // The old default goes first: the partial unique index allows one at a time.
        ClientAddress::query()->where('user_id', $address->user_id)->whereKeyNot($address->id)->where('is_default', true)->update(['is_default' => false]);
        ClientAddress::query()->whereKey($address->id)->update(['is_default' => true]);
        $address->is_default = true;
    }

    public function mostRecentlyUpdated(User $user): ?ClientAddress
    {
        return ClientAddress::query()->where('user_id', $user->id)->orderByDesc('updated_at')->orderByDesc('id')->first();
    }
}
