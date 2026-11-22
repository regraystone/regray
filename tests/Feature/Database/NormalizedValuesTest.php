<?php

use App\Enums\AuditEvent;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\AuditLogValue;
use App\Models\User;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use Illuminate\Support\Facades\DB;

/*
 * No table of Regray's own keeps JSON: an audit entry's values are rows of
 * their own (audit_log_values), and a quotation card's snapshot is columns
 * on the message (QuotationCardTest covers those). The audit values read
 * back as they were written, and the migration carries them across, either
 * way.
 */

/** Values with every kind an audit records: text, numbers, a flag, nothing, lists, nesting, emptiness. */
function auditSample(): array
{
    return [
        'name' => 'Ginza One',
        'completed_year' => 2025,
        'ratio' => 2.5,
        'is_published' => true,
        'published_at' => null,
        'gallery' => ['projects/a.jpg', 'projects/b.jpg'],
        'answers' => [['field' => 'area_sqm', 'value' => '3.75'], ['field' => 'fascia', 'value' => true]],
        'categories' => [],
        'event' => AuditEvent::Updated,
    ];
}

function recordSample(): AuditLog
{
    $user = User::factory()->role(Role::Admin)->create();

    return app(AuditLogRepositoryInterface::class)->record($user->id, AuditEvent::Updated, $user, [], auditSample(), '127.0.0.1', 'Pest');
}

it('keeps no JSON column in any table of Regray\'s own', function (): void {
    $framework = ['cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'sessions'];

    $json = DB::table('information_schema.columns')
        ->where('table_schema', DB::raw('current_schema()'))
        ->whereIn('data_type', ['json', 'jsonb'])
        ->whereNotIn('table_name', $framework)
        ->get(['table_name', 'column_name'])
        ->map(fn (object $column): string => "{$column->table_name}.{$column->column_name}")
        ->all();

    expect($json)->toBe([]);
});

it('reads an audit entry\'s values back as they were written, one row a value', function (): void {
    $log = recordSample()->fresh();

    expect($log->old_values)->toBe([])
        ->and($log->new_values)->toBe([...auditSample(), 'event' => 'updated'])
        ->and(AuditLogValue::query()->where('audit_log_id', $log->id)->where('side', 'new')->orderBy('id')->pluck('path')->all())->toBe([
            'name', 'completed_year', 'ratio', 'is_published', 'published_at',
            'gallery.0', 'gallery.1',
            'answers.0.field', 'answers.0.value', 'answers.1.field', 'answers.1.value',
            'categories', 'event',
        ]);
});

it('moves audit values between JSON and rows, both ways, losing nothing', function (): void {
    $log = recordSample();
    $migration = require database_path('migrations/2026_11_22_000006_store_audit_values_in_rows.php');

    $migration->down();

    expect(json_decode((string) DB::table('audit_logs')->where('id', $log->id)->value('new_values'), true))->toEqual([...auditSample(), 'event' => 'updated'])
        ->and(json_decode((string) DB::table('audit_logs')->where('id', $log->id)->value('old_values'), true))->toBe([]);

    $migration->up();

    $fresh = AuditLog::query()->findOrFail($log->id);

    expect($fresh->new_values)->toEqual([...auditSample(), 'event' => 'updated'])
        ->and($fresh->old_values)->toBe([]);
});
