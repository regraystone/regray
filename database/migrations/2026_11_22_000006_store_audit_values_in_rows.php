<?php

use App\Models\AuditLogValue;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * An audit entry's values move from two JSON columns (`old_values`,
 * `new_values`) to rows of their own, `audit_log_values`: one a value, by the
 * entry, its side (old or new) and its path. A nested value is one row a
 * leaf: a gallery's second photo is `gallery.1`, an answer's value
 * `answers.0.value`. Each row keeps the value's type, so it reads back as it
 * was written (AuditLogValue explains how).
 *
 * Every existing entry's values are copied across, then the JSON columns go.
 */
return new class extends Migration
{
    private const SIDES = ['old' => 'old_values', 'new' => 'new_values'];

    public function up(): void
    {
        Schema::create('audit_log_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_log_id')->constrained()->cascadeOnDelete();
            $table->string('side', 3);
            $table->string('path', 255);
            $table->string('value_type', 10);
            $table->text('value')->nullable();

            $table->unique(['audit_log_id', 'side', 'path'], 'idx_audit_log_values_path');
        });

        DB::statement("ALTER TABLE audit_log_values ADD CONSTRAINT chk_audit_log_values_side CHECK (side IN ('old','new'))");
        DB::statement("ALTER TABLE audit_log_values ADD CONSTRAINT chk_audit_log_values_type CHECK (value_type IN ('string','integer','float','boolean','null','array'))");
        // Only a string, a number or a boolean has a value to keep.
        DB::statement("ALTER TABLE audit_log_values ADD CONSTRAINT chk_audit_log_values_value CHECK ((value IS NULL) = (value_type IN ('null','array')))");

        DB::table('audit_logs')
            ->where(fn ($query) => $query->whereNotNull('old_values')->orWhereNotNull('new_values'))
            ->orderBy('id')
            ->each(function (object $log): void {
                $rows = [];

                foreach (self::SIDES as $side => $column) {
                    if ($log->{$column} === null) {
                        continue;
                    }

                    /** @var array<array-key, mixed> $values */
                    $values = json_decode((string) $log->{$column}, true, flags: JSON_THROW_ON_ERROR);

                    foreach (AuditLogValue::rowsFor($side, $values) as $row) {
                        $rows[] = ['audit_log_id' => $log->id, ...$row];
                    }
                }

                DB::table('audit_log_values')->insert($rows);
            });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn(['old_values', 'new_values']);
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->jsonb('old_values')->nullable()->after('auditable_id');
            $table->jsonb('new_values')->nullable()->after('old_values');
        });

        DB::table('audit_log_values')->orderBy('id')->get()->groupBy('audit_log_id')->each(function ($rows, $logId): void {
            $columns = [];

            foreach (self::SIDES as $side => $column) {
                $values = AuditLogValue::restore($rows->where('side', $side)->all());
                $columns[$column] = $values === null ? null : json_encode($values, JSON_THROW_ON_ERROR);
            }

            DB::table('audit_logs')->where('id', $logId)->update($columns);
        });

        Schema::dropIfExists('audit_log_values');
    }
};
