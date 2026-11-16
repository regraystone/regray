<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * An appointment booked outside an inquiry is confirmed by the sales member
 * marketing assigns it to.
 *
 * - `awaiting_sales` is a new status: assigned to sales, not yet confirmed. A
 *   client reads it as `pending` (ScheduleStatus::forClient()).
 * - `assigned_to` is the sales member it is assigned to, `assigned_by` the
 *   marketer (or admin) who assigned it, `assigned_at` when. A decline clears
 *   `assigned_to` and `assigned_at` and keeps `assigned_by`, so the marketer
 *   who assigned it still reads it to assign it again.
 * - `declined_by`, `declined_at` and `decline_reason` are the last decline,
 *   kept until it is assigned again.
 * - `sales_reminded_at` is when its sales member was last reminded to answer
 *   (`schedules:remind`), cleared whenever it is assigned again or sent back
 *   to them by a reschedule.
 * - `marketing_reminded_at` is when marketing was last reminded that it still
 *   has no sales member (`schedules:remind`), cleared when it is declined.
 * - `client_record_id` is the client record it is for: a direct (walk-in)
 *   client marketing booked for, who has no account (`client_id` stays null),
 *   and an inquiry's visit's client. A booking outside an inquiry is always for
 *   an account or a record (`chk_schedules_client`).
 *
 * No CHECK ties `awaiting_sales` to `assigned_to`: the foreign keys null on
 * delete, and a deleted sales account must not block the delete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->foreignId('assigned_to')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->after('assigned_to')->constrained('users')->nullOnDelete();
            $table->timestampTz('assigned_at')->nullable()->after('assigned_by');
            $table->foreignId('declined_by')->nullable()->after('assigned_at')->constrained('users')->nullOnDelete();
            $table->timestampTz('declined_at')->nullable()->after('declined_by');
            $table->string('decline_reason', 255)->nullable()->after('declined_at');
            $table->timestampTz('sales_reminded_at')->nullable()->after('decline_reason');
            $table->timestampTz('marketing_reminded_at')->nullable()->after('sales_reminded_at');
            $table->foreignId('client_record_id')->nullable()->after('client_id')->constrained('clients')->restrictOnDelete();

            $table->index('assigned_to');
            $table->index('assigned_by');
            $table->index('client_record_id');
        });

        DB::statement('ALTER TABLE schedules DROP CONSTRAINT IF EXISTS chk_schedules_status');
        DB::statement("ALTER TABLE schedules ADD CONSTRAINT chk_schedules_status CHECK (status IN ('pending','awaiting_sales','confirmed','served','cancelled','expired'))");
        DB::statement('ALTER TABLE schedules ADD CONSTRAINT chk_schedules_declined CHECK ((declined_at IS NULL) = (decline_reason IS NULL))');
        DB::statement('ALTER TABLE schedules ADD CONSTRAINT chk_schedules_client CHECK (inquiry_id IS NOT NULL OR client_id IS NOT NULL OR client_record_id IS NOT NULL)');
    }

    public function down(): void
    {
        DB::statement("UPDATE schedules SET status = 'pending' WHERE status = 'awaiting_sales'");
        DB::statement('ALTER TABLE schedules DROP CONSTRAINT IF EXISTS chk_schedules_client');
        DB::statement('ALTER TABLE schedules DROP CONSTRAINT IF EXISTS chk_schedules_declined');
        DB::statement('ALTER TABLE schedules DROP CONSTRAINT IF EXISTS chk_schedules_status');
        DB::statement("ALTER TABLE schedules ADD CONSTRAINT chk_schedules_status CHECK (status IN ('pending','confirmed','served','cancelled','expired'))");

        Schema::table('schedules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_to');
            $table->dropConstrainedForeignId('assigned_by');
            $table->dropColumn('assigned_at');
            $table->dropConstrainedForeignId('declined_by');
            $table->dropColumn(['declined_at', 'decline_reason', 'sales_reminded_at', 'marketing_reminded_at']);
            $table->dropConstrainedForeignId('client_record_id');
        });
    }
};
