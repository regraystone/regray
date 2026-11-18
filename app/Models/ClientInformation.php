<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A client's own saved answer to "How would you like to get it?", with the site
 * that answer needs: one row per account, edited from "Client Information" in
 * the Profile hub and used as the starting point for a new quotation request.
 *
 * This is deliberately not `Client` (the CRM record attached to an inquiry)
 * and not `ClientContact` (one of that record's phone numbers or emails). It is
 * private prefill data owned by a user account, and holds no contact details.
 *
 * @property int $user_id
 * @property string $delivery_method
 * @property string|null $location
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['delivery_method', 'location'])]
class ClientInformation extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'user_id';

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
