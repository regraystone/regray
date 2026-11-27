<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What a client books from the Schedule page: a showroom visit, as before, or
 * the installation or the delivery of one of their accepted orders.
 *
 * `schedules.kind`: `showroom_visit`, `installation` or `delivery`
 * (App\Enums\ScheduleKind). Null for an inquiry's own target date, which is
 * not a booking. Every appointment already booked is a showroom visit.
 * `schedules.order_id`: the accepted inquiry an installation or a delivery is
 * for. It is not `inquiry_id`, which stays the inquiry's one target date.
 *
 * An order has at most one open installation and one open delivery at a time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->string('kind', 20)->nullable();
            $table->foreignId('order_id')->nullable()->constrained('inquiries')->nullOnDelete();
        });

        DB::table('schedules')->whereNull('inquiry_id')->update(['kind' => 'showroom_visit']);

        DB::statement("ALTER TABLE schedules ADD CONSTRAINT chk_schedules_kind CHECK (kind IS NULL OR kind IN ('showroom_visit','installation','delivery'))");
        DB::statement("ALTER TABLE schedules ADD CONSTRAINT chk_schedules_order_kind CHECK (order_id IS NULL OR kind IN ('installation','delivery'))");
        DB::statement("CREATE UNIQUE INDEX idx_schedules_order_open ON schedules (order_id, kind) WHERE order_id IS NOT NULL AND status IN ('pending','awaiting_sales','confirmed')");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_schedules_order_open');
        DB::statement('ALTER TABLE schedules DROP CONSTRAINT IF EXISTS chk_schedules_order_kind');
        DB::statement('ALTER TABLE schedules DROP CONSTRAINT IF EXISTS chk_schedules_kind');

        Schema::table('schedules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('order_id');
            $table->dropColumn('kind');
        });
    }
};
