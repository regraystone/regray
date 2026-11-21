<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * A client's own profile: their phone, company, address and photo. Staff have
 * none: they are reached through Regray, not at an address of their own, so a
 * profile is only ever made for a client (booted() refuses any other).
 *
 * @property int $user_id
 * @property string $phone
 * @property string|null $company
 * @property string $address
 * @property string|null $avatar_path
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['phone', 'company', 'address', 'avatar_path'])]
class UserProfile extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'user_id';

    /**
     * Refuse a profile for anyone but a client, wherever it is made from: the
     * Accounts page, a lead's phone, a seeder.
     */
    protected static function booted(): void
    {
        static::creating(function (self $profile): void {
            if (! $profile->user?->isClient()) {
                throw new LogicException('A profile is a client\'s own; staff have none.');
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
