<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agendas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->restrictOnDelete();
            $table->foreignId('booked_by')->nullable()->setOnDelete('set null');
            $table->string('booked_by_type', 10);
            $table->foreignId('inquiry_id')->nullable()->setOnDelete('set null');
            $table->string('title', 150);
            $table->timestampTz('arrival_at');
            $table->string('status', 20);
            $table->text('notes')->nullable();
            $table->foreignId('approved_by')->nullable()->setOnDelete('set null');
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->string('cancel_reason', 255)->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');

            $table->index(['client_id', 'arrival_at']);
            $table->index(['status', 'arrival_at']);
            $table->index('inquiry_id');
        });

        DB::statement("ALTER TABLE agendas ADD CONSTRAINT chk_agendas_status CHECK (status IN ('requested','confirmed','completed','cancelled'))");
        DB::statement("ALTER TABLE agendas ADD CONSTRAINT chk_agendas_booked_by_type CHECK (booked_by_type IN ('client','staff'))");
        DB::statement('ALTER TABLE agendas ADD CONSTRAINT chk_agendas_approved CHECK (approved_by IS NULL OR approved_at IS NOT NULL)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE agendas DROP CONSTRAINT IF EXISTS chk_agendas_status');
        DB::statement('ALTER TABLE agendas DROP CONSTRAINT IF EXISTS chk_agendas_booked_by_type');
        DB::statement('ALTER TABLE agendas DROP CONSTRAINT IF EXISTS chk_agendas_approved');
        Schema::dropIfExists('agendas');
    }
};
