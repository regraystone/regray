<?php

namespace App\Models;

use App\Enums\AuditEvent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property AuditEvent $event
 * @property string $auditable_type
 * @property int $auditable_id
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon $created_at
 */
#[Fillable([])]
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    public $timestamps = true;

    protected function casts(): array
    {
        return [
            'event' => AuditEvent::class,
            'old_values' => 'json',
            'new_values' => 'json',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new \Exception('AuditLog is immutable');
        });

        static::deleting(function () {
            throw new \Exception('AuditLog cannot be deleted');
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
