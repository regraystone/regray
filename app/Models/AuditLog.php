<?php

namespace App\Models;

use App\Enums\AuditEvent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One audited change. Its old and new values are rows of their own
 * (AuditLogValue), read back here as the arrays they were written as.
 *
 * @property int $id
 * @property int|null $user_id
 * @property AuditEvent $event
 * @property string $auditable_type
 * @property int $auditable_id
 * @property-read array<array-key, mixed>|null $old_values
 * @property-read array<array-key, mixed>|null $new_values
 * @property-read Collection<int, AuditLogValue> $auditValues
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon $created_at
 */
#[Fillable([])]
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    public $timestamps = true;

    /** Its values are read with it: an entry is little without them. */
    protected $with = ['auditValues'];

    protected function casts(): array
    {
        return [
            'event' => AuditEvent::class,
        ];
    }

    /** @return HasMany<AuditLogValue, $this> */
    public function auditValues(): HasMany
    {
        return $this->hasMany(AuditLogValue::class)->orderBy('id');
    }

    /** @return Attribute<array<array-key, mixed>|null, never> */
    protected function oldValues(): Attribute
    {
        return Attribute::get(fn (): ?array => AuditLogValue::restore($this->valueRows()->where('side', 'old')));
    }

    /** @return Attribute<array<array-key, mixed>|null, never> */
    protected function newValues(): Attribute
    {
        return Attribute::get(fn (): ?array => AuditLogValue::restore($this->valueRows()->where('side', 'new')));
    }

    /**
     * Its value rows, read now if they were not loaded with it (as for an
     * entry just written), since lazy loading is off outside production.
     *
     * @return Collection<int, AuditLogValue>
     */
    private function valueRows(): Collection
    {
        if (! $this->relationLoaded('auditValues')) {
            $this->setRelation('auditValues', $this->auditValues()->get());
        }

        return $this->auditValues;
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
