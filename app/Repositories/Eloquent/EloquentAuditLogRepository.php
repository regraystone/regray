<?php

namespace App\Repositories\Eloquent;

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class EloquentAuditLogRepository implements AuditLogRepositoryInterface
{
    /** AuditLog has no fillable columns, so the row is filled by hand. */
    public function record(?int $userId, AuditEvent $event, Model $auditable, ?array $oldValues, ?array $newValues, ?string $ipAddress, ?string $userAgent): AuditLog
    {
        $log = new AuditLog;
        $log->forceFill([
            'user_id' => $userId,
            'event' => $event,
            'auditable_type' => $auditable->getMorphClass(),
            'auditable_id' => $auditable->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent === null ? null : Str::limit($userAgent, 255, ''),
        ])->save();

        return $log;
    }
}
