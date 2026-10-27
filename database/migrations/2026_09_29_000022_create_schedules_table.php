<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('booked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('booked_by_type', 10);
            $table->foreignId('inquiry_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 150);
            $table->timestampTz('arrival_at');
            $table->string('status', 20);
            $table->text('notes')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->string('cancel_reason', 255)->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');

            $table->index(['client_id', 'arrival_at']);
            $table->index(['status', 'arrival_at']);
        });

        // One inquiry has one deadline visit. A booking has no inquiry.
        DB::statement('CREATE UNIQUE INDEX idx_schedules_inquiry_once ON schedules (inquiry_id) WHERE inquiry_id IS NOT NULL');

        DB::statement("ALTER TABLE schedules ADD CONSTRAINT chk_schedules_status CHECK (status IN ('pending','confirmed','completed','cancelled'))");
        DB::statement("ALTER TABLE schedules ADD CONSTRAINT chk_schedules_booked_by_type CHECK (booked_by_type IN ('client','staff'))");
        DB::statement('ALTER TABLE schedules ADD CONSTRAINT chk_schedules_approved CHECK (approved_by IS NULL OR approved_at IS NOT NULL)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE schedules DROP CONSTRAINT IF EXISTS chk_schedules_status');
        DB::statement('ALTER TABLE schedules DROP CONSTRAINT IF EXISTS chk_schedules_booked_by_type');
        DB::statement('ALTER TABLE schedules DROP CONSTRAINT IF EXISTS chk_schedules_approved');
        Schema::dropIfExists('schedules');
    }
};
