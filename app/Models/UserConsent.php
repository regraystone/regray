<?php

namespace App\Models;

use App\Enums\ConsentSource;
use App\Enums\ConsentType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One agreement or withdrawal, as it was given. Never changed or removed once
 * written: a later change of mind is a new row.
 *
 * @property int $id
 * @property int $user_id
 * @property ConsentType $type
 * @property bool $granted
 * @property string|null $version
 * @property ConsentSource $source
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon $created_at
 */
#[Fillable(['user_id', 'type', 'granted', 'version', 'source', 'ip_address', 'user_agent'])]
class UserConsent extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'type' => ConsentType::class,
            'source' => ConsentSource::class,
            'granted' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new \LogicException('A consent record cannot be changed.');
        });

        static::deleting(function () {
            throw new \LogicException('A consent record cannot be deleted.');
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
