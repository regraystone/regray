<?php

namespace App\Http\Controllers\Account;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\AccountStatusRequest;
use App\Http\Requests\Account\StoreAccountRequest;
use App\Http\Requests\Account\UpdateAccountRequest;
use App\Models\User;
use App\Services\AccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * The admin's Accounts page writes: add a staff account, change one, turn one
 * on or off, archive one. The list itself is ViewController::accounts().
 *
 * `{user}` stays a plain id, looked up after the route's permission, so
 * nothing says whether an account exists to someone who may not manage them.
 * Only a staff account is found: an admin's or a client's is not this page's,
 * and an archived one is gone from it, so either is a 404.
 */
class AccountController extends Controller
{
    public function __construct(private readonly AccountService $accounts) {}

    public function store(StoreAccountRequest $request): RedirectResponse
    {
        $user = $this->accounts->create($request->accountData(), self::actor($request), $request->ip(), $request->userAgent());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name can now sign in with the temporary password.', ['name' => $user->name])]);

        return back();
    }

    public function update(UpdateAccountRequest $request, string $user): RedirectResponse
    {
        $account = self::find($user);
        $this->accounts->update($account, $request->accountData(), self::actor($request), $request->ip(), $request->userAgent());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name\'s account is updated.', ['name' => $account->name])]);

        return back();
    }

    public function status(AccountStatusRequest $request, string $user): RedirectResponse
    {
        $account = self::find($user);
        $active = $request->boolean('active');
        $this->accounts->setActive($account, $active, self::actor($request), $request->ip(), $request->userAgent());

        Inertia::flash('toast', ['type' => 'success', 'message' => $active
            ? __(':name can sign in again.', ['name' => $account->name])
            : __(':name is deactivated and can no longer sign in.', ['name' => $account->name])]);

        return back();
    }

    public function archive(Request $request, string $user): RedirectResponse
    {
        $account = self::find($user);
        $this->accounts->archive($account, self::actor($request), $request->ip(), $request->userAgent());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name is archived.', ['name' => $account->name])]);

        return back();
    }

    /** A staff account still on the list, or a 404. */
    public static function find(string $id): User
    {
        return ctype_digit($id)
            ? User::query()
                ->role(array_map(fn (Role $role): string => $role->value, Role::staffRoles()))
                ->whereNull('archived_at')
                ->findOrFail((int) $id)
            : abort(404);
    }

    private static function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
