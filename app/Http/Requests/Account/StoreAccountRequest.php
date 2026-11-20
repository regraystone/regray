<?php

namespace App\Http\Requests\Account;

use App\Models\User;

/** Add account: a new staff account. */
class StoreAccountRequest extends AccountRequest
{
    protected function account(): ?User
    {
        return null;
    }
}
