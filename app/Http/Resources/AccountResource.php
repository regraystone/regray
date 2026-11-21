<?php

namespace App\Http\Resources;

use App\Enums\Permission;
use App\Enums\Role;
use App\Enums\SocialProvider;
use App\Models\User;
use App\Services\AccountService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * A staff account as a row of the admin's Accounts page, and what its View
 * drawer shows. Staff have no profile (phone, company, address): that is a
 * client's own.
 *
 * `actions` and `canToggleStatus` are what this reader may do to this row: the
 * permission each route checks, and AccountService's guards on top (no one
 * turns off or archives themselves or the last active admin), so the page
 * never decides by role.
 *
 * Expects the roles and social accounts loaded
 * (UserRepositoryInterface::paginateAccounts()).
 *
 * @mixin User
 */
class AccountResource extends JsonResource
{
    /**
     * @return array{id: int, name: string, email: string, role: string|null, roleLabel: string, isActive: bool, createdAt: string|null, emailVerified: bool, twoFactorEnabled: bool, googleEmail: string|null, actions: list<string>, canToggleStatus: bool}
     */
    public function toArray(Request $request): array
    {
        /** @var User $viewer */
        $viewer = $request->user();
        $gate = Gate::forUser($viewer);
        $role = $this->primaryRole();
        $refusal = app(AccountService::class)->refusalToTurnOff($this->resource, $viewer);
        $google = $this->socialAccounts->firstWhere('provider', SocialProvider::Google);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $role?->value,
            'roleLabel' => self::roleLabel($role),
            'isActive' => $this->is_active,
            'createdAt' => $this->created_at?->toIso8601String(),
            'emailVerified' => $this->email_verified_at !== null,
            'twoFactorEnabled' => $this->two_factor_confirmed_at !== null,
            'googleEmail' => $google?->email,
            'actions' => array_values(array_filter([
                'view',
                $gate->allows(Permission::AccountsUpdate->value) ? 'edit' : null,
                $gate->allows(Permission::AccountsArchive->value) && $refusal === null ? 'archive' : null,
            ])),
            // Turning one on is never refused; turning one off may be.
            'canToggleStatus' => $gate->allows(Permission::AccountsUpdate->value) && (! $this->is_active || $refusal === null),
        ];
    }

    /**
     * The role's name on the Accounts page: short, as the admin reads it
     * ("Sales", "Technical"), rather than the title a client reads beside a
     * handler's name (Role::label()).
     */
    public static function roleLabel(?Role $role): string
    {
        return match ($role) {
            null => '—',
            Role::Admin => 'Admin',
            Role::Client => 'Client',
            Role::Sales => 'Sales',
            Role::Technical => 'Technical',
            default => (string) $role->label(),
        };
    }
}
