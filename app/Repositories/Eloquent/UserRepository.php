<?php

namespace App\Repositories\Eloquent;

use App\Enums\Role;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;

class UserRepository implements UserRepositoryInterface
{
    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    public function create(array $data): User
    {
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);
    }

    public function paginateAccounts(?string $escapedSearch, ?bool $active, int $perPage, int $page): LengthAwarePaginator
    {
        return User::query()
            ->role(array_map(fn (Role $role): string => $role->value, Role::staffRoles()))
            ->whereNull('archived_at')
            ->when($active !== null, fn (Builder $query) => $query->where('is_active', $active))
            ->when($escapedSearch !== null, function (Builder $query) use ($escapedSearch): void {
                $pattern = "%{$escapedSearch}%";

                $query->where(fn (Builder $query) => $query
                    ->where('name', 'ILIKE', $pattern)
                    ->orWhere('email', 'ILIKE', $pattern));
            })
            ->with(['roles:id,name', 'socialAccounts:id,user_id,provider,email'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage, page: $page);
    }

    public function countActiveAdminsExcept(User $user): int
    {
        return User::role(Role::Admin->value)
            ->whereKeyNot($user->getKey())
            ->where('is_active', true)
            ->whereNull('archived_at')
            ->count();
    }
}
