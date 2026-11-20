<?php

namespace App\Http\Requests\Account;

use App\Http\Controllers\Account\AccountController;
use App\Models\User;

/**
 * Edit account: a staff account's name, email and role, the role among the
 * staff roles only. An admin's or a client's account is not found here
 * (AccountController::find()).
 */
class UpdateAccountRequest extends AccountRequest
{
    private ?User $account = null;

    /** The account in the URL, a staff one still on the list, or a 404. */
    protected function account(): ?User
    {
        return $this->account ??= AccountController::find((string) $this->route('user'));
    }
}
