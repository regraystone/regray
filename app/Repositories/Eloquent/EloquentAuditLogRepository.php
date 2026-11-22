<?php

namespace App\Repositories\Eloquent;

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\AuditLogValue;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EloquentAuditLogRepository implements AuditLogRepositoryInterface
{
    /**
     * AuditLog has no fillable columns, so the row is filled by hand. Its
     * values are rows of their own (AuditLogValue), written with it.
     */
    public function record(?int $userId, AuditEvent $event, Model $auditable, ?array $oldValues, ?array $newValues, ?string $ipAddress, ?string $userAgent): AuditLog
    {
        return DB::transaction(function () use ($userId, $event, $auditable, $oldValues, $newValues, $ipAddress, $userAgent): AuditLog {
            $log = new AuditLog;
            $log->forceFill([
                'user_id' => $userId,
                'event' => $event,
                'auditable_type' => $auditable->getMorphClass(),
                'auditable_id' => $auditable->getKey(),
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent === null ? null : Str::limit($userAgent, 255, ''),
            ])->save();

            $rows = [
                ...($oldValues === null ? [] : AuditLogValue::rowsFor('old', $oldValues)),
                ...($newValues === null ? [] : AuditLogValue::rowsFor('new', $newValues)),
            ];

            if ($rows !== []) {
                AuditLogValue::query()->insert(array_map(fn (array $row): array => ['audit_log_id' => $log->id, ...$row], $rows));
            }

            return $log;
        });
    }
}
