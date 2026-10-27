<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The work a project's accepted quotation is carried out as: fabrication, then
 * installation, delivery or pickup. A job order only exists against a
 * quotation that is closeable or closed.
 *
 * The reference comes from `job_order_reference_seq`, the way an inquiry's
 * comes from `inquiry_reference_seq`, so two job orders written at once never
 * get the same number.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SEQUENCE IF NOT EXISTS job_order_reference_seq START 1');

        Schema::create('job_orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->foreignId('inquiry_project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quotation_id')->constrained()->restrictOnDelete();
            $table->string('status', 20);
            $table->timestampTz('status_changed_at');
            $table->string('notes', 1000)->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');

            $table->index('inquiry_project_id');
            $table->index('quotation_id');
            $table->index('status');
        });

        DB::statement("ALTER TABLE job_orders ADD CONSTRAINT chk_job_orders_status CHECK (status IN ('pending','fabrication','for_install','for_delivery','for_pickup','delivered','installed','completed','on_hold','cancelled'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('job_orders');
        DB::statement('DROP SEQUENCE IF EXISTS job_order_reference_seq');
    }
};
